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

namespace ContainMapper;

use Contain\Entity\EntityInterface;
use ContainMapper\Driver;

/**
 * Mapper which links a data source to Contain entities.
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class Mapper extends Service\AbstractService
{
    /**
     * Driver to the data source
     * @var ContainMapper\Driver\DriverInterface
     */
    protected $driver;

    /**
     * Entity namespace
     * @var string
     */
    protected $entity;

    /**
     * Constructor
     *
     * @param   string                                  Entity Classname this mapper hydrates
     * @param   ContainMapper\Driver\DriverInterface
     * @return  void
     */
    public function __construct($entity, Driver\AbstractDriver $driver = null)
    {
        $this->entity = $entity;
        $this->driver = $driver;
    }

    /**
     * Sets the entity class this mapper hydrates.
     *
     * @param   string                                  Entity Classname this mapper hydrates
     * @return  $this
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     * Sets the data source driver.
     *
     * @param   ContainMapper\Driver\AbstractDriver
     * @return  $this
     */
    public function setDriver(Driver\AbstractDriver $driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Gets the data source driver.
     *
     * @return  ContainMapper\Driver\AbstractDriver
     */
    public function getDriver()
    {
        if (!$this->driver) {
            throw new Exception\InvalidArgumentException('Cannot perform action requiring a driver without '
                . 'a driver being injected.'
            );
        }

        return $this->driver;
    }

    /**
     * Gets the connection for the driver.
     *
     * @return  ContainMapper\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->driver->getConnection();
    }

    /**
     * Hydrates a driver's result data into an entity.
     *
     * @param   array|Traversable                   Raw Database Source Data
     * @param   Contain\Entity\EntityInterface      Entity to hydrate the data into (as opposed to creating a new object)
     * @return  Contain\Entity\EntityInterface
     */
    public function hydrate($data = array(), EntityInterface $entity = null)
    {
        if ($data instanceof Traversable) {
            $data = iterator_to_array($data);
        }

        if ($entity) {
            $entity->reset()->fromArray($data);
        } else {
            $entity = new $this->entity($data);
        }

        $entity->persisted()->clean();

        // you can hydrate without a driver
        if ($this->driver) {
            $this->getDriver()->hydrate($entity, $data);
        }

        $entity->trigger('hydrate.post');

        return $entity;
    }

    /**
     * Locates a single data source object by some criteria and returns a
     * hydrated data entity.
     *
     * @param   mixed                                   Query
     * @return  Contain\Entity\EntityInterface|false    Hydrated data entity
     */
    public function findOne($criteria = array())
    {
        if ($entity = $this->prepare($this->getDriver())->findOne($criteria)) {
            return $this->hydrate($entity);
        }

        return false;
    }

    /**
     * Locates multiple data source objects by some criteria and returns an
     * array of hydrated data entities.
     *
     * @param   mixed                                   Query
     * @return  ContainMapper\Cursor
     */
    public function find($criteria = array())
    {
        if (!$cursor = $this->prepare($this->getDriver())->find($criteria)) {
            $cursor = array();
        }

        return new Cursor($this, $cursor);
    }

    /**
     * Deletes an entity from the data source driver by some criteria.
     *
     * @param   Contain\Entity\EntityInterface
     * @return  $this
     */
    public function delete($entity)
    {
        $entity->trigger('delete.pre');

        $this->prepare($this->getDriver())->delete($entity);
        $entity->clean();

        $entity->trigger('delete.post');

        return $this;
    }

    /**
     * Persist an entity to the data source driver.
     *
     * @param   Contain\Entity\EntityInterface  Entity to persist
     * @param   boolean                         Only persist if dirty() or not previously persisted
     * @return  $this
     */
    public function persist(EntityInterface $entity, $whenNotPersisted = true)
    {
        $mode = $entity->isPersisted() ? 'update' : 'insert';

        $entity->trigger($mode . '.pre');

        if ($whenNotPersisted == true && !$entity->dirty() && $entity->isPersisted()) {
            return $this;
        }

        $this->prepare($this->getDriver())->persist($entity);
        $entity->persisted()->clean();

        $entity->trigger($mode . '.post');

        return $this;
    }

    /**
     * Converts a property query to something the driver can use to work with
     * various levels of sub-properties and further descendents using the
     * dot notation.
     *
     * @param   Contain\Entity\EntityInterface  Entity to persist
     * @param   string                          Query
     * @return  ContainMapper\Resolver
     */
    public function resolve(EntityInterface $entity, $query)
    {
        $resolver = new Resolver($query);
        return $resolver->scan($entity);
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
        if (!$entity->isPersisted()) {
            throw new Exception\InvalidArgumentException('Cannot increment properties as $entity '
                . 'has not been persisted.'
            );
        }

        $resolver = $this->resolve($entity, $query)
                         ->assertType('Contain\Entity\Property\Type\IntegerType');

        $resolverEntity = $resolver->getEntity();
        $entity->trigger('update.pre');
        $resolverEntity->property($resolver->getProperty())
            ->setValue($resolverEntity->property($resolver->getProperty())->getValue() + $inc);

        $this->prepare($this->getDriver())->increment($entity, $query, $inc);
        $entity->trigger('update.post');
        $resolver->getEntity()->clean($resolver->getProperty());

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
        if (!$entity->isPersisted()) {
            throw new Exception\InvalidArgumentException('Cannot push to $entity as this is an update operation '
                . 'and $entity has not been persisted.'
            );
        }

        $resolver = $this->resolve($entity, $query)
                 ->assertType('Contain\Entity\Property\Type\ListType');

        if (count($value = $resolver->getType()->export(array($value))) != 1) {
            throw new InvalidArgumentException('Multiple values passed to ' . __METHOD__ . ' not allowed.');
        }

        list($value) = $value; // remove outer array

        $this->prepare($this->getDriver())->push($entity, $query, $value, $ifNotExists);

        $entity->trigger('update.pre');
        $property = $resolver->getEntity()->property($resolver->getProperty());
        $arr = $property->getValue() ?: array();

        if ($arr instanceof \ContainMapper\Cursor) {
            $arr = $arr->export();
        }

        $arr[] = $value;
        $property->setValue($arr);

        $resolver->getEntity()->clean($resolver->getProperty());
        $entity->trigger('update.post');

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
        if (!$entity->isPersisted()) {
            throw new Exception\InvalidArgumentException('Cannot push to $entity as this is an update operation '
                . 'and $entity has not been persisted.'
            );
        }

        $resolver = $this->resolve($entity, $query)
                 ->assertType('Contain\Entity\Property\Type\ListType');

        if (count($value = $resolver->getType()->export($value)) != 1) {
            throw new InvalidArgumentException('Multiple values passed to ' . __METHOD__ . ' not allowed.');
        }
        list($value) = $value; // remove outer array

        $this->prepare($this->getDriver())->pull($entity, $query, $value);

        $entity->trigger('update.pre');
        $arr = $resolver->getEntity()->property($resolver->getProperty())->getValue();
        foreach ($arr as $index => $val) {
            if ($val === $value) {
                unset($arr[$index]);
                break;
            }
        }
        $resolver->getEntity()->property($resolver->getProperty())->setValue(array_merge(array(), $arr));

        $entity->trigger('update.post');
        $resolver->getEntity()->clean($resolver->getProperty());

        return $this;
    }
}
