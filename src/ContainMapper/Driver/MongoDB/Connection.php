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

use Traversable;
use ContainMapper\Exception;
use Mongo;
use ContainMapper\Driver\ConnectionInterface;
use MongoId;

/**
 * MongoDB Connection
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class Connection implements ConnectionInterface
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var MongoDB
     */
    protected $database;

    /**
     * @var MongoCollection
     */
    protected $collection;

    /**
     * @var string
     */
    protected $collectionName;

    /**
     * Constructor
     *
     * @param   array|Traversable           Configuration
     * @param   string                      Name of the MongoDB collection
     * @return  $this
     */
    public function __construct($config, $collection)
    {
        if ($config instanceof Traversable) {
            $config = iterator_to_array($config);
        }

        if (!is_array($config)) {
            throw new Exception\InvalidArgumentException('$config should be an array or an '
                . 'instance of Traversable.'
            );
        }

        $this->collection = $collection;
        $this->config = $config;
    }

    /**
     * Return the Mongo database.
     *
     * @return  MongoDB
     */
    public function getDatabase()
    {
        if ($this->database) {
            return $this->database;
        }

        $database = $this->config['database'];
        $this->database = $this->getConnection()->$database;

        return $this->database;
    }

    /**
     * Return the MongoDB collection.
     *
     * @return  MongoCollection
     */
    public function getCollection()
    {
        if ($this->collection) {
            return $this->collection;
        }

        $collection = $this->collectionName;
        $this->collection = $this->getDatabase()->$collection;

        return $this->collection;
    }

    /**
     * Builds a connection to MongoDB and returns the Mongo object.
     *
     * @return  Mongo
     */
    public function getConnection()
    {
        // already established?
        if ($this->connection) {
            return $this->connection;
        }

        $dsn = 'mongodb://';

        if (!empty($this->config['username']) && !empty($this->config['password'])) {
            $dsn .= sprintf('%s:%s@',
                $this->config['username'],
                $this->config['password']
            );
        }

        if (empty($this->config['host'])) {
            throw new InvalidArgumentException('$config must include a \'host\' key.');
        }

        $dsn .= $this->config['host'];

        if (!empty($this->config['port'])) {
            $dsn .= ':' . $this->config['port'];
        }

        $this->connection = new Mongo($dsn);

        return $this->connection;
    }

    /**
     * Rewrites the dirty() output from an entity into something
     * MongoDb can use in an update statement.
     *
     * @param   EntityInterface     Reference entity
     * @param   array               Dirty output
     * @return  array
     */
    protected function getUpdateCriteria(EntityInterface $entity)
    {
        $result = array();

        $dirty = $entity->export($entity->dirty(), true);

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
     * Returns the primary key or null if an entity has not been persisted.
     *
     * @param   Contain\Entity\EntityInterface
     * @return  string|null
     */
    protected function getId(EntityInterface $entity)
    {
        if (!$primary = $entity->primary()) {
            $primary = $entity->getExtendedProperty('_id');
        }

        if (is_array($primary)) {
            $primary = implode('', array_values($primary));
        }

        return $primary ? $primary : null;
    }

    /**
     * Persists an entity in MongoDB.
     *
     * @param   EntityInterface                 Entity to persist
     * @return  $this
     */
    public function persist(EntityInterface $entity)
    {
        if (!$primary = $this->getId($entity)) {
            if ($entity->primary()) {
                throw new Exception\InvalidArgumentException('$entity has primary properties '
                    . 'defined; but has no values assigned'
                );
            }

            $primary = new MongoId();
            $primary = $primary->{'$id'};
        }

        $data = $entity->export();
        $data['_id'] = $primary;

        if ($entity->isPersisted()) {
            $this->getCollection()->insert(
                $data,
                $this->getOptions(array(
                    'safe'    => false,
                    'fsync'   => false,
                    'timeout' => 60000, // 1 minute
                ))
            );
        } else {
            $this->getCollection()->update(
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
        return $this->getCollection()->count($criteria);
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
        if (!$id = $this->getId($entity)) {
            return $this;
        }

        $options = $this->getOptions(array(
            'justOne' => true,
            'safe'    => false,
            'fsync'   => false,
            'timeout' => 60000, // 1 minute
        ));

        $this->getCollection()->remove(
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
        $result = $this->getCollection()->findOne(
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
     * @return  array[]
     */
    public function find($criteria = null)
    {
        $cursor = $this->getCollection()->find(
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

        $result = array();
        foreach ($cursor as $data) {
            $result[] = $data;
        }

        return $result;
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
        if (!$id = $this->getId($entity)) {
            return $this;
        }

        $this->getCollection()->update(
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
        if (!$id = $this->getId($entity)) {
            return $this;
        }

        $method = $ifNotExists ? '$addToSet' : '$push';

        $this->getCollection()->update(
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
        if (!$id = $this->getId($entity)) {
            return $this;
        }

        $this->getCollection()->update(
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
}
