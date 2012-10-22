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
     * @param   ContainMapper\Driver\DriverInterface
     * @param   Full classname of the entity this mapper is responsible for hydrating
     * @return  $this
     */
    public function __construct(Driver\DriverInterface $driver, $entity)
    {
        $this->driver = $driver;
        $this->entity = $entiy;
    }

    /**
     * Sets the data source driver.
     *
     * @param   ContainMapper\Driver\DriverInterface
     * @return  $this
     */
    public function setDriver(ContainMapper\Driver\DriverInterface $driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Gets the data source driver.
     *
     * @return  ContainMapper\Driver\DriverInterface
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Hydrates a driver's result data into an entity.
     *
     * @param   array|Traversable       Raw Database Source Data
     * @return  Contain\Entity\EntityInterface
     */
    public function hydrate($data = array())
    {
        if ($data instanceof Traversable) {
            $data = iterator_to_array($data);
        }

        $entity = new $this->entity($data);
        $entity->persisted()->clean();

        $this->getEventManager()->trigger('hydrate.post', $entity);
        $this->clean();

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
        if ($entity = $this->prepare($this->driver)->findOne($criteria)) {
            return $this->hydrate($entity);
        }

        return false;
    }

    /**
     * Locates multiple data source objects by some criteria and returns an
     * array of hydrated data entities.
     *
     * @param   mixed                                   Query
     * @return  Contain\Entity\EntityInterface[]        Hydrated data entities
     */
    public function find($criteria = array())
    {
        if ($entities = $this->prepare($this->driver)->find($criteria)) {
            foreach ($entities as $index => $entity) {
                $entities[$index] = $this->hydrate($entity);
            }
        } else {
            $entities = array();
        }

        return $entities;
    }

    /**
     * Deletes an entity from the data source driver by some criteria.
     *
     * @param   Contain\Entity\EntityInterface
     * @return  $this
     */
    public function delete($entity)
    {
        $this->getEventManager()->trigger('delete.pre', $entity);

        $this->prepare($this->driver)->delete($entity);
        $entity->persisted()->clean();

        $this->getEventManager()->trigger('delete.post', $entity);

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
        if ($whenNotPersisted == true && !$entity->dirty() && $entity->isPersisted()) {
            return $this;
        }

        $mode = $entity->isPersisted() ? 'update' : 'insert';

        $this->getEventManager()->trigger($mode . '.pre', $entity);

        $this->prepare($this->driver)->persist($entity);
        $entity->persisted()->clean();

        $this->getEventManager()->trigger($mode . '.post', $entity);

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


        $property = $resolver->getEntity()->property($resolver->getProperty());
        $this->getEventManager()->trigger('update.pre', $resolver->getEntity());
        $property->setValue($property->getValue() + $inc);

        $this->prepare($this->driver)->increment($entity, $query, $inc);
        $this->getEventManager()->trigger('update.post', $resolver->getEntity());
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

        if (count($value = $resolver->getType()->export($value)) != 1) {
            throw new InvalidArgumentException('Multiple values passed to ' . __METHOD__ . ' not allowed.');
        }

        $this->prepare($this->driver)->push($entity, $query, $value, $ifNotExists);
        $this->getEventManager()->trigger('update.post', $resolver->getEntity());
        $resolver->getEntity()->clean($resolver->getProperty());

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

        $this->prepare($this->driver)->pull($entity, $query, $value);
        $this->getEventManager()->trigger('update.post', $resolver->getEntity());
        $resolver->getEntity()->clean($resolver->getProperty());

        return $this;
    }
}
