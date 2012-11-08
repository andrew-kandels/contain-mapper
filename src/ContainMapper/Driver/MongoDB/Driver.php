<?php
/**
 * Contain Project
 *
 * This source file is subject to the BSD license bundled with
 * this package in the LICENSE.txt file. It is also available
 * on the world-wide-web at http://www.opensource.org/licenses/bsd-license.php.
 * If you are unable to receive a copy of the license or have
 * questions concerning the terms, please send an email to
 * me@andrewkandels.com.
 *
 * @category    akandels
 * @package     contain
 * @author      Andrew Kandels (me@andrewkandels.com)
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link        http://andrewkandels.com/contain
 */

namespace ContainMapper\Driver\MongoDB;

use ContainMapper\Driver\AbstractDriver;
use ContainMapper\Exception;
use Contain\Entity\EntityInterface;
use Contain\Entity\Property\Type\EntityType;
use Contain\Entity\Property\Type\ListType;
use MongoId;
use ContainMapper\Resolver;

/**
 * MongoDB Driver
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class Driver extends AbstractDriver
{
    /**
     * Rewrites the dirty() output from an entity into something
     * MongoDb can use in an update statement.
     *
     * @param   EntityInterface     Reference entity
     * @return  array
     */
    public function getUpdateCriteria(EntityInterface $entity)
    {
        $dirty = $result = array();
        
        if ($properties = $entity->dirty()) {
            $dirty = $entity->export($properties);
        }

        foreach ($dirty as $property => $value) {
            $type = $entity->type($property);

            // child entity
            if ($type instanceof EntityType) {
                $child = $entity->property($property)->getValue();
                $sub   = $this->getUpdateCriteria($child);

                foreach ($sub as $subProperty => $subValue) {
                    $result[$property . '.' . $subProperty] = $subValue;
                }
            } else {
                $result[$property] = $value;
            }
        }

        return $result;
    }

    /**
     * Persists an entity in MongoDB.
     *
     * @param   EntityInterface                 Entity to persist
     * @return  $this
     */
    public function persist(EntityInterface $entity)
    {
        if (!$primary = $this->getPrimaryScalarId($entity)) {
            if ($entity->primary()) {
                throw new Exception\InvalidArgumentException('$entity has primary properties '
                    . 'defined; but has no values assigned'
                );
            }

            $primary = new MongoId();
        }

        $data = $entity->export();
        $data['_id'] = $primary;

        if (!$entity->isPersisted()) {
            $this->getConnection()->getCollection()->insert(
                $data,
                $this->getOptions(array(
                    'safe'    => false,
                    'fsync'   => false,
                    'timeout' => 60000, // 1 minute
                ))
            );
        } else {
            $this->getConnection()->getCollection()->update(
                array('_id' => $primary),
                array('$set' => $this->getUpdateCriteria($entity)),
                $this->getOptions(array(
                    'upsert' => false,
                    'multiple' => false,
                    'safe' => false,
                    'fsync' => false,
                    'timeout' => 60000, // 1 minute
                ))
            );
        }

        $entity->setExtendedProperty('_id', $primary);

        return $this;
    }

    /**
     * Runs a MongoDB count query to determine the number of documents
     * that match the given criteria.
     *
     * @param   array               Criteria
     * @return  integer
     */
    public function count($criteria)
    {
        return $this->getConnection()->getCollection()->count($criteria);
    }

    /**
     * Deletes an entity.
     *
     * @param   Contain\Entity\EntityInterface
     * @return  $this
     */
    public function delete(EntityInterface $entity)
    {
        // anything to do?
        if (!$id = $this->getPrimaryScalarId($entity)) {
            return $this;
        }

        $options = $this->getOptions(array(
            'justOne' => true,
            'safe'    => false,
            'fsync'   => false,
            'timeout' => 60000, // 1 minute
        ));

        $this->getConnection()->getCollection()->remove(
            $criteria = array('_id' => $id),
            $options
        );

        return $this;
    }

    /**
     * Finds a single entity and returns its data.
     *
     * @param   array               Criteria
     * @return  array
     */
    public function findOne($criteria = null)
    {
        $result = $this->getConnection()->getCollection()->findOne(
            $criteria ?: array(),
            $this->getProperties()
        );

        if (!$result) {
            return false;
        }

        return $result;
    }

    /**
     * Finds multiple entities and returns their data.
     *
     * @param   array               Criteria
     * @return  Traversable
     */
    public function find($criteria = null)
    {
        $cursor = $this->getConnection()->getCollection()->find(
            $criteria ?: array(),
            $this->getProperties()
        );

        if ($this->sort !== null) {
            $cursor->sort($this->sort);
        }

        if ($this->limit !== null) {
            $cursor->limit($this->limit);
        }

        if ($this->skip !== null) {
            $cursor->skip($this->skip);
        }

        return $cursor;
    }

    /**
     * Increments a numerical property.
     *
     * @param   Contain\Entity\EntityInterface  Entity to persist
     * @param   string                          Query to resolve path to numeric property
     * @param   integer                         Amount to increment by
     * @return  $this
     */
    public function increment(EntityInterface $entity, $query, $inc)
    {
        if (!$id = $this->getPrimaryScalarId($entity)) {
            return $this;
        }

        $this->getConnection()->getCollection()->update(
            array('_id' => $id),
            array('$inc' => array($query => $inc)),
            $this->getOptions(array(
                'upsert' => false,
                'multiple' => false,
                'safe' => false,
                'fsync' => false,
                'timeout' => 60000, // 1 minute
            ))
        );

        return $this;
    }

    /**
     * Appends one value to the end of a ListType, optionally if it doesn't
     * exist only. In MongoDB this is an atomic operation.
     *
     * @param   Contain\Entity\EntityInterface  Entity to persist
     * @param   string                          Query to resolve which should point to a ListType
     * @param   mixed|array                     Value to append
     * @param   boolean                         Only add if it doesn't exist
     * @return  $this
     */
    public function push(EntityInterface $entity, $query, $value, $ifNotExists = false)
    {
        if (!$id = $this->getPrimaryScalarId($entity)) {
            return $this;
        }

        $method = $ifNotExists ? '$addToSet' : '$push';

        $this->getConnection()->getCollection()->update(
            array('_id' => $id),
            array($method => array($query => $value)),
            $this->getOptions(array(
                'upsert' => false,
                'multiple' => false,
                'safe' => false,
                'fsync' => false,
                'timeout' => 60000, // 1 minute
            ))
        );

        return $this;
    }

    /**
     * Removes a value from a ListType. In MongoDB this is an atomic operation.
     *
     * @param   Contain\Entity\EntityInterface  Entity to persist
     * @param   string                          Query to resolve which should point to a ListType
     * @param   mixed|array                     Value to remove
     * @return  $this
     */
    public function pull(EntityInterface $entity, $query, $value)
    {
        if (!$id = $this->getPrimaryScalarId($entity)) {
            return $this;
        }

        $this->getConnection()->getCollection()->update(
            array('_id' => $id),
            array('$pull' => array($query => $value)),
            $this->getOptions(array(
                'upsert' => false,
                'multiple' => false,
                'safe' => false,
                'fsync' => false,
                'timeout' => 60000, // 1 minute
            ))
        );

        return $this;
    }

    /**
     * Post-hydration callback. Attempts to set the internal _id property 
     * Mongo uses to identity the primary unique id of the entity, either 
     * from the Mongo driver if this is an internal invokation or by 
     * extrapolating it from the primary scalar id.
     *
     * @param   Contain\Entity\EntityInterface
     * @param   Values we returned
     * @return  $this
     */
    public function hydrate(EntityInterface $entity, $values)
    {
        if (!empty($values['_id'])) {
            $entity->setExtendedProperty('_id', $values['_id']);
            return $this;
        }

        if ($primary = $this->getPrimaryScalarId($entity)) {
            $entity->setExtendedProperty('_id', $primary);
            return $this;
        }

        return $this;
    }
}
