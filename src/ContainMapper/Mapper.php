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
        if ($entity = $this->driver->findOne($criteria)) {
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
        if ($entities = $this->driver->find($criteria)) {
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
     * @param   mixed                                   Criteria
     * @return  $this
     */
    public function delete($criteria = array())
    {
        $this->driver->delete($criteria);
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

        $this->driver->persist($entity);

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
}
