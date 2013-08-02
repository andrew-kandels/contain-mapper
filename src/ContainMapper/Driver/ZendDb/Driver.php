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

namespace ContainMapper\Driver\ZendDb;

use Contain\Entity\EntityInterface;
use Contain\Entity\Property\Type;
use ContainMapper\Driver\ConnectionInterface;
use ContainMapper\Driver\AbstractDriver;
use ContainMapper\Exception;
use ContainMapper\Resolver;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;

/**
 * Zend Db Driver
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class Driver extends AbstractDriver
{
    /**
     * @var Zend\Db\TableGateway\TableGateway
     */
    protected $tableGateway;


    /**
     * @var array
     */
    protected $selectCriteria = array(
        'columns',
        'from',
        'having',
        'join',
        'limit',
        'offset',
        'order',
        'where',
    );

    /**
     * Constructor
     *
     * @param   ContainMapper\ConnectionInterface $connection
     * @param   string                            $table
     * @return  Driver
     */
    public function __construct(ConnectionInterface $connection, $table)
    {
        $this->tableGateway = new TableGateway($table, $connection->getConnection());

        parent::__construct($connection);
    }

    /**
     * May be invoked by the mapper if the driver supports an atomic, or
     * more efficient incrementor method as opposed to the typical
     * persist().
     *
     * @param   Contain\Entity\EntityInterface  Contain Data Entity
     * @param   string                          Path to the property
     * @param   integer                         Amount to increment by (+|-)
     * @return  $this
     */
    public function increment(EntityInterface $entity, $column, $inc)
    {
        $entity->setProperty($column, $entity->getProperty($column) + $inc);
        return $this->persist($entity);
    }

    /**
     * Rewrites the export() output of an entity into an array
     * for TableGateway.
     *
     * @param   Contain\Entity\EntityInterface
     * @return  array
     */
    public function getInsertCriteria(EntityInterface $entity)
    {
        $properties      = $entity->properties();
        $data            = $entity->export();
        $return          = array();

        foreach ($properties as $name) {
            $type  = $entity->type($name);
            $value = $data[$name];

            if ($type instanceof Type\EntityType) {
                continue;
            } elseif ($type instanceof Type\ListType) {
                continue;
            } else {
                $return[$name] = $value;
            }
        }

        return $return;
    }

    /**
     * Rewrites the dirty() output from an entity into something
     * TableGateway can use in an update statement.
     *
     * @param   EntityInterface     Reference entity
     * @param   boolean                                 Is a sub-document (recursive)
     * @return  array
     */
    public function getUpdateCriteria(EntityInterface $entity, $isSubDocument = false)
    {
        $dirty = array();
        $return = array();

        $primary = array_keys($entity->primary());
        if ($properties = $entity->dirty()) {
            $dirty = $entity->export($properties);
        }

        foreach ($dirty as $property => $value) {
            if (in_array($property, $primary)) {
                continue;
            }

            $type = $entity->type($property);

            if ($type instanceof Type\EntityType) {
                continue;
            } elseif ($type instanceof Type\ListType) {
                continue;
            } else {
                $return[$property] = $value;
            }
        }

        return $return;
    }

    /**
     * Persists an entity in Zend Db Table Gateway.
     *
     * @param   EntityInterface                 Entity to persist
     * @return  Driver
     */
    public function persist(EntityInterface $entity)
    {
        $primary = $entity->primary();

        if (!$entity->isPersisted()) {
            $this->tableGateway->insert(
                $data = $this->getInsertCriteria($entity)
            );

            if (count($primary) == 1) {
                $column = array_keys($primary);
                $column = array_shift($column);
                $entity->set($column, $this->tableGateway->getLastInsertValue());
            }
        } else {
            if (!$primary) {
                return false;
            }
            $this->tableGateway->update(
                $data = $this->getUpdateCriteria($entity),
                $primary
            );
        }

        return $this;
    }

    /**
     * Runs a count query to determine the number of rows
     * that match the given criteria.
     *
     * @param   array               Criteria
     * @return  integer
     */
    public function count(array $criteria)
    {
        $resultSet = $this->tableGateway->selectWith($this->criteria($criteria));
        return $resultSet->count();
    }

    /**
     * Deletes an entity.
     *
     * @param   Contain\Entity\EntityInterface
     * @return  Driver
     */
    public function delete(EntityInterface $entity)
    {
        if (!$primary = $entity->primary()) {
            return $this;
        }

        $this->tableGateway->delete($primary);

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
        $select = $this->criteria($criteria);
        $select->limit(1);

        $result = $this->tableGateway->selectWith($select);

        if (!$result) {
            return false;
        }
        return $result->current();
    }

    /**
     * Finds multiple entities and returns their data.
     *
     * @param   array               Criteria
     * @return  Traversable
     */
    public function find($criteria = null)
    {
        $select = $this->criteria($criteria);
        $result = $this->tableGateway->selectWith($select);

        if (!$result) {
            return false;
        }

        return $result;
    }

    /**
     * Handle Criteria
     *
     * @param array Criteria
     * @return Select
     */
    protected function criteria(array $criteria)
    {
        $select = $this->tableGateway->getSql()->select();
        if (empty($criteria)) {
            return $select;
        }

        $isSpecial = false;
        foreach ($criteria as $k => $v) {
            if (in_array($k, $this->selectCriteria)) {
                $isSpecial = true;
                break;
            }
        }
        $criteria = $isSpecial ? $criteria : array('where' => $criteria);

        foreach ($criteria as $k => $v) {
            $select->$k($v);
        }
        return $select;
    }

    /**
     * Post Hydration Callback
     *
     * @param EntityInterface $entity
     * @param array $values
     * @return self
     */
    public function hydrate(EntityInterface $entity, $values)
    {
        $properties = $entity->properties(true);
        foreach ($properties as $name) {
            $property = $entity->property($name);
            if ($property->getType() instanceof \Contain\Entity\Property\Type\EntityType) {
                $method = 'findOne';
            } else if ($property->getType() instanceof \Contain\Entity\Property\Type\ListEntityType) {
                $method = 'find';
            } else {
                continue;
            }
            $options = $property->getOptions();
            foreach (array('table', 'criteria', 'className') as $key) {
                if (!array_key_exists($key, $options)) {
                    continue 2;
                }
            }

            // create the mapper
            $mapper = new \ContainMapper\Mapper(
                $options['className'],
                new self($this->getConnection(), $options['table'])
            );

            // fill in dynamic properties
            foreach($options['criteria'] as $k => $v) {
                if (!is_null($entity->get($v))) {
                    $options['criteria'][$k] = $entity->get($v);
                }
            }
            $results = $mapper->$method($options['criteria']);
            $entity->set($name, $results);
        }
    }
}
