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

use Contain\Entity\EntityInterface;
use Contain\Entity\Property\Type\DateTimeType;
use Contain\Entity\Property\Type\EntityType;
use Contain\Entity\Property\Type\ListType;
use Contain\Entity\Property\Type\MongoDateType;
use Contain\Entity\Property\Type\MongoIdType;
use ContainMapper\Driver\AbstractDriver;
use ContainMapper\Exception;
use MongoId;

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
     * @var MongoDateType
     */
    protected $dateType;

    /**
     * @var MongoIdType
     */
    protected $idType;

    /**
     * Initializes the MongoDate type for converting DateTime types.
     *
     * @return  void
     */
    public function init()
    {
        $this->dateType = new MongoDateType();
        $this->idType   = new MongoIdType();
    }

    /**
     * Rewrites the export() output of an entity into a Mongo array.
     *
     * @param EntityInterface $entity
     * @param boolean         $isSubDocument Is a sub-document (recursive)
     *
     * @return array
     */
    public function getInsertCriteria(EntityInterface $entity, $isSubDocument = false)
    {
        $properties      = $entity->properties();
        $data            = $entity->export();
        $return          = array();
        $primaryProperty = null;
        $primaryValue    = null;

        if (!$isSubDocument) {
            $primaryValue = $this->extractId($entity);

            if ($primary = array_keys($entity->primary())) {
                list($primaryProperty) = $primary;
            }
        }

        foreach ($properties as $name) {
            $type  = $entity->type($name);
            $value = $data[$name];

            if ($type instanceof DateTimeType || $type instanceof MongoDateType) {
                $return[$name] = $this->dateType->parse($value);
            } elseif ($type instanceof MongoIdType) {
                $return[$name] = $this->idType->export($value);
            } elseif ($type instanceof EntityType) {
                $value = $this->getInsertCriteria($entity->get($name), true);
                if ($value) {
                    $return[$name] = $value;
                }
            } elseif ($type instanceof ListType) {
                if ($value) {
                    $return[$name] = $value;
                }
            } else {
                $return[$name] = $value;
            }

            // rename the primary property to _id to utilize Mongo's primary and index
            if (!$isSubDocument && $primaryProperty == $name) {
                $return['_id'] = $primaryValue;
                unset($return[$name]);
            }
        }

        if (!$isSubDocument && !$primaryProperty) {
            $return['_id'] = $primaryValue;
        }

        return $return;
    }

    /**
     * Rewrites the dirty() output from an entity into something
     * MongoDb can use in an update statement.
     *
     * @param EntityInterface $entity        Reference entity
     * @param boolean         $isSubDocument Is a sub-document (recursive)
     *
     * @return array
     */
    public function getUpdateCriteria(EntityInterface $entity, $isSubDocument = false)
    {
        $result          = array('$set' => array(), '$unset' => array());
        $primaryProperty = null;

        // primary _id property
        if ($primary = array_keys($entity->primary())) {
            list($primaryProperty) = $primary;
        }

        // if nothing is dirty, there's nothing to do, exit early
        if (!$properties = $entity->dirty()) {
            return array();
        }

        // the dirty properties should return their values with the second argument as true...
        if (!$dirty = $entity->export($properties, true)) {
            throw new Exception\InvalidArgumentException('$entity is dirty but export() returns no values');
        }

        foreach ($dirty as $property => $value) {
            // never update the primary property (_id), won't work anyway
            if (!$isSubDocument && $primaryProperty == $property) {
                continue;
            }

            $type = $entity->type($property);

            // child entity, use recursion to populate
            if ($type instanceof EntityType) {
                $child = $entity->property($property)->getValue();

                // if an entity is cleared out and marked as dirty, unset the mongo property
                if ($this->isDirtyEntityEmpty($child)) {
                    $result['$unset'][$property] = true;
                    continue;
                }

                // combine the sub-document $sets/$unsets with ours, using dot notation to drill in
                $sub = $this->getUpdateCriteria($child, true);
                foreach ($sub as $action => $actions) {
                    foreach ($actions as $subProperty => $subValue) {
                        $result[$action][$property . '.' . $subProperty] = $subValue;
                    }
                }
                continue;
            }

            // is the property being unset?
            if ($type->export($value) === $type->getUnsetValue()) {
                $result['$unset'][$property] = true;
                continue;
            }

            // return the date object for MongoDate
            if ($type instanceof DateTimeType) {
                $value = $this->dateType->parse($value);
            }

            $result['$set'][$property] = $value;
        }

        // mongo will panic with an empty $set or $unset
        foreach ($result as $action => $actions) {
            if (empty($result[$action])) {
                unset($result[$action]);
            }
        }

        return $result;
    }

    /**
     * Recursively checks to see if a dirty entity is empty and contains only
     * other likewise empty entities to qualify it for being $unset.
     *
     * @param EntityInterface $entity
     *
     * @return boolean
     */
    protected function isDirtyEntityEmpty(EntityInterface $entity)
    {
        if (!$entity->dirty()) {
            return false;
        }

        if (!$props = $entity->export($entity->dirty(), true)) {
            return false;
        }

        foreach ($props as $property => $value) {
            if (!$entity->type($property) instanceof EntityType) {
                return false;
            }

            return $this->isDirtyEntityEmpty($entity->get($property));
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(EntityInterface $entity)
    {
        $primary = $this->extractId($entity);

        if (!$entity->isPersisted()) {
            $this->getConnection()->getCollection()->insert(
                $data = $this->getInsertCriteria($entity),
                $this->getOptions(array(
                    'w' => 1,
                    'fsync'   => false,
                    'timeout' => 60000, // 1 minute
                ))
            );
        } else {
            if (!$criteria = $this->getUpdateCriteria($entity)) {
                // nothing to do, saving with array() will wipe out the record and
                // $set => array() will throw an error
                return $this;
            }

            $this->getConnection()->getCollection()->update(
                array('_id' => $primary),
                $criteria,
                $this->getOptions(array(
                    'upsert' => false,
                    'multiple' => false,
                    'w' => 1,
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
     * @param array $criteria Criteria
     *
     * @return int
     */
    public function count($criteria)
    {
        return $this->getConnection()->getCollection()->count($criteria);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(EntityInterface $entity)
    {
        // anything to do?
        if (!$id = $this->extractId($entity)) {
            return $this;
        }

        $options = $this->getOptions(array(
            'justOne' => true,
            'w'    => 1,
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
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function find($criteria = null)
    {
        $cursor = $this->getConnection()->getCollection()->find(
            $criteria ?: array()
        );

        if ($this->timeout !== null) {
            $cursor->timeout($this->timeout);
        }

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
     * {@inheritDoc}
     */
    public function increment(EntityInterface $entity, $query, $inc)
    {
        if (!$id = $this->extractId($entity)) {
            return $this;
        }

        $this->getConnection()->getCollection()->update(
            array('_id' => $id),
            array('$inc' => array($query => $inc)),
            $this->getOptions(array(
                'upsert' => false,
                'multiple' => false,
                'w' => 1,
                'fsync' => false,
                'timeout' => 60000, // 1 minute
            ))
        );

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function push(EntityInterface $entity, $query, $value, $ifNotExists = false)
    {
        if (!$id = $this->extractId($entity)) {
            return $this;
        }

        $method = $ifNotExists ? '$addToSet' : '$push';

        $this->getConnection()->getCollection()->update(
            array('_id' => $id),
            array($method => array($query => $value)),
            $this->getOptions(array(
                'upsert' => false,
                'multiple' => false,
                'w' => 1,
                'fsync' => false,
                'timeout' => 60000, // 1 minute
            ))
        );

        return $this;
    }

    /**
     * Removes a value from a ListType. In MongoDB this is an atomic operation.
     *
     * @param EntityInterface $entity Entity to persist
     * @param string          $query  Query to resolve which should point to a ListType
     * @param mixed|array     $value  Value to remove
     * @return self
     */
    public function pull(EntityInterface $entity, $query, $value)
    {
        if (!$id = $this->extractId($entity)) {
            return $this;
        }

        $this->getConnection()->getCollection()->update(
            array('_id' => $id),
            array('$pull' => array($query => $value)),
            $this->getOptions(array(
                'upsert' => false,
                'multiple' => false,
                'w' => 1,
                'fsync' => false,
                'timeout' => 60000, // 1 minute
            ))
        );

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hydrate(EntityInterface $entity, $values)
    {
        if (!empty($values['_id'])) {
            $entity->setExtendedProperty('_id', $values['_id']);

            if ($primary = array_keys($entity->primary())) {
                list($primaryProperty) = $primary;
                $entity->set($primaryProperty, $values['_id']);
            }

            return $this;
        }

        $this->extractId($entity);

        return $this;
    }

    /**
     * Pulls out the internal value to use as the primary id and persists it
     * to the entity's _id extended property.
     *
     * @param EntityInterface $entity
     *
     * @return mixed
     */
    public function extractId(EntityInterface $entity)
    {
        if ($entity->getExtendedProperty('_id')) {
            return $entity->getExtendedProperty('_id');
        }

        if (!$primary = $entity->primary()) {
            $entity->setExtendedProperty('_id', $id = new MongoId());
            return $id;
        }

        if (count($primary) > 1) {
            throw new Exception\InvalidArgumentException('MongoDB driver only allows for single '
                . 'property primary keys'
            );
        }

        list($value) = array_values($primary);

        if (!$value) {
            throw new Exception\InvalidArgumentException('Primary key column contains null or '
                . 'empty value'
            );
        }

        if ($value instanceof MongoId) {
            $entity->setExtendedProperty('_id', $value);
            $entity->fromArray($primary);
            return $value;
        }

        if (is_object($value)) {
            $value = (string) $value;
        }

        if (!is_scalar($value)) {
            throw new Exception\InvalidArgumentException('MongoDB driver requires that primary '
                . 'properties are scalar in value with exception to MongoId'
            );
        }

        $entity->setExtendedProperty('_id', $value);

        return $value;
    }
}
