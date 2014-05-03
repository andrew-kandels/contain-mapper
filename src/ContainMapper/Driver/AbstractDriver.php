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

namespace ContainMapper\Driver;

use Contain\Entity\EntityInterface;
use Contain\Entity\Property\Type;
use ContainMapper\Exception;
use ContainMapper;

/**
 * MongoDB Driver
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
abstract class AbstractDriver
       extends ContainMapper\AbstractQuery
    implements DriverInterface
{
    /**
     * @var Contain\Mapper\Driver\ConnectionInterface
     */
    protected $connection;

    /**
     * Constructor
     *
     * @param   ContainMapper\ConnectionInterface
     * @return  $this
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;

        if (method_exists($this, 'init')) {
            $this->init();
        }
    }

    /**
     * Gets the connection object.
     *
     * @return  ContainMapper\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Post-hydration callback.
     *
     * @param   Contain\Entity\EntityInterface
     * @param   Values we returned
     * @return  $this
     */
    public function hydrate(EntityInterface $entity, $values)
    {
        return $this;
    }

    /**
     * Returns true if the data entity has been persisted to the data store
     * this driver is responsible for.
     *
     * @param   EntityInterface                 Entity to persist
     * @return  boolean
     */
    public function isPersisted(EntityInterface $entity)
    {
        return (boolean) $entity->isPersisted(); // defaults to Contain internal flag
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
    public function increment(EntityInterface $entity, $query, $inc)
    {
        // by default, disabled and instead pass to persist
        return $this->persist($entity);
    }

    /**
     * May be invoked by the mapper if the driver supports an atomic, or
     * more efficient way of appending items to the end of an array property.
     *
     * @param   Contain\Entity\EntityInterface  Contain Data Entity
     * @param   string                          Path to the property
     * @param   mixed|array|Traversable         Value(s) to push
     * @param   boolean                         Only add if it doesn't exist (if supported)
     * @return  $this
     */
    public function push(EntityInterface $entity, $query, $value, $ifNotExists = false)
    {
        // by default, disabled and instead pass to persist
        return $this->persist($entity);
    }

    /**
     * Returns the primary key or null if an entity has not been persisted.
     *
     * @param   Contain\Entity\EntityInterface
     * @return  string|null
     */
    public function getPrimaryScalarId(EntityInterface $entity)
    {
        if (!$primary = $entity->primary()) {
            $primary = $entity->getExtendedProperty('_id');
        }

        if (is_array($primary)) {
            foreach ($primary as $key => $value) {
                if (!is_scalar($value)) {
                    throw new Exception\InvalidArgumentException('$entity has a non-scalar primary()');
                }
            }

            $primary = implode('', array_values($primary));
        }

        return ($primary ?: null);
    }
}
