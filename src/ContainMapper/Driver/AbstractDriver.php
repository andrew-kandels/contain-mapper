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
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * Constructor
     *
     * @param ConnectionInterface
     * @return self
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;

        $this->init();
    }

    /**
     * Gets the connection object.
     *
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * {@inheritDoc}
     */
    public function hydrate(EntityInterface $entity, $values)
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isPersisted(EntityInterface $entity)
    {
        return (boolean) $entity->isPersisted(); // defaults to Contain internal flag
    }

    /**
     * {@inheritDoc}
     */
    public function increment(EntityInterface $entity, $query, $inc)
    {
        // by default, disabled and instead pass to persist
        return $this->persist($entity);
    }

    /**
     * {@inheritDoc}
     */
    public function push(EntityInterface $entity, $query, $value, $ifNotExists = false)
    {
        // by default, disabled and instead pass to persist
        return $this->persist($entity);
    }

    /**
     * Returns the primary key or null if an entity has not been persisted.
     *
     * @param EntityInterface $entity
     *
     * @return string|null
     */
    public function getPrimaryScalarId(EntityInterface $entity)
    {
        if (!$primary = $entity->primary()) {
            $primary = $entity->getExtendedProperty('_id');
        }

        if (is_array($primary)) {
            foreach ($primary as $value) {
                if (!is_scalar($value)) {
                    throw new Exception\InvalidArgumentException('$entity has a non-scalar primary()');
                }
            }

            $primary = implode('', array_values($primary));
        }

        return ($primary ?: null);
    }

    /**
     * Empty initialization callback - used as a post-construction callback
     */
    protected function init()
    {
    }
}
