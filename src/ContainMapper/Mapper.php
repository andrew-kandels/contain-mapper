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
use ContainMapper\Driver\AbstractDriver;
use Zend\Stdlib\ArrayUtils;

/**
 * Mapper which links a data source to Contain entities.
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 *
 * @method Mapper prepare()
 */
class Mapper extends Service\AbstractService
{
    /**
     * Driver to the data source
     *
     * @var AbstractDriver|null
     */
    protected $driver;

    /**
     * Entity namespace
     *
     * @var string
     */
    protected $entity;

    /**
     * Constructor
     *
     * @param   string              $entity Entity Class name this mapper hydrates
     * @param   AbstractDriver|null $driver
     */
    public function __construct($entity, AbstractDriver $driver = null)
    {
        $this->entity = (string) $entity;
        $this->driver = $driver;
    }

    /**
     * Sets the entity class this mapper hydrates.
     *
     * @param  string $entity Entity Class name this mapper hydrates
     * @return self
     */
    public function setEntity($entity)
    {
        $this->entity = (string) $entity;

        return $this;
    }

    /**
     * Sets the data source driver.
     *
     * @param  AbstractDriver $driver
     *
     * @return self
     */
    public function setDriver(AbstractDriver $driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Gets the data source driver.
     *
     * @return AbstractDriver
     *
     * @throws Exception\InvalidArgumentException
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
     * @return \ContainMapper\Driver\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->driver->getConnection();
    }

    /**
     * Hydrates a driver's result data into an entity.
     *
     * @param array|\Traversable $data   Raw Database Source Data
     * @param EntityInterface    $entity Entity to hydrate the data into (as opposed to creating a new object)
     * @param String             $hydrationMode The type of hydration to use
     *
     * @return EntityInterface
     */
    public function hydrate($data = array(), EntityInterface $entity = null, $hydrationMode = AbstractDriver::HYDRATION_MODE_RECORD)
    {
        $data = ArrayUtils::iteratorToArray($data);

        if ($hydrationMode === AbstractDriver::HYDRATION_MODE_SCALAR) {
            return $data;
        }

        if ($entity) {
            $entity->reset()->fromArray($data);
        } else {
            $entity = new $this->entity($data);
        }

        if ($extendedProperties = array_diff_assoc($data, $entity->export())) {
            foreach ($extendedProperties as $name => $value) {
                $entity->setExtendedProperty($name, $value);
            }
        }

        $entity->persisted();

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
     * @param mixed|array $criteria
     *
     * @return EntityInterface|false    Hydrated data entity
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
     * @param mixed|array $criteria
     *
     * @return \ContainMapper\Cursor|EntityInterface[]
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
     * @param EntityInterface $entity
     *
     * @return self
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
     * @param EntityInterface $entity           Entity to persist
     * @param bool            $whenNotPersisted Only persist if dirty() or not previously persisted
     *
     * @return self
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
     * @param EntityInterface $entity Entity to persist
     * @param string          $query
     *
     * @return Resolver
     */
    public function resolve(EntityInterface $entity, $query)
    {
        $resolver = new Resolver($query);

        return $resolver->scan($entity);
    }

    /**
     * Increments a numerical property.
     *
     * @param EntityInterface $entity Entity to persist
     * @param string          $query  Query to resolve path to numeric property
     * @param int             $inc    Amount to increment by
     *
     * @return self
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
     * @param EntityInterface $entity      Entity to persist
     * @param string          $query       Query to resolve which should point to a ListType
     * @param mixed|array     $value       Value to append
     * @param bool            $ifNotExists Only add if it doesn't exist
     *
     * @return self
     *
     * @throws Exception\InvalidArgumentException
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
            throw new Exception\InvalidArgumentException('Multiple values passed to ' . __METHOD__ . ' not allowed.');
        }

        list($value) = $value; // remove outer array

        $this->prepare($this->getDriver())->push($entity, $query, $value, $ifNotExists);

        $entity->trigger('update.pre');
        $property = $resolver->getEntity()->property($resolver->getProperty());
        $arr = $property->getValue() ?: array();

        if ($arr instanceof Cursor) {
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
     * @param EntityInterface $entity Entity to persist
     * @param string          $query  Query to resolve which should point to a ListType
     * @param mixed|array     $value  Value to remove
     *
     * @return self
     *
     * @throws Exception\InvalidArgumentException
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
            throw new Exception\InvalidArgumentException('Multiple values passed to ' . __METHOD__ . ' not allowed.');
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
