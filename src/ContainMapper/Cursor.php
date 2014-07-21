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

use Iterator;
use Traversable;

/**
 * Cursor for slow hydration of iterable resultsets.
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class Cursor extends Service\AbstractService implements Iterator
{
    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * Internal cursor to iterate
     *
     * @var array|Iterator|Traversable
     */
    protected $cursor;

    /**
     * @var integer
     */
    protected $position;

    /**
     * @var \Contain\Entity\EntityInterface
     */
    protected $entity;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * Constructor
     *
     * @param Mapper          $mapper
     * @param array|\Iterator $cursor
     */
    public function __construct(Mapper $mapper, $cursor)
    {
        if (! ($cursor instanceof Iterator || $cursor instanceof Traversable || is_array($cursor))) {
            throw new Exception\InvalidArgumentException('Cursor expects $cursor argument to be iterable/traversable');
        }

        $this->mapper = $mapper;
        $this->cursor = $cursor;
    }

    /**
     * Exports the cursor as a plain array.
     *
     * @param  array   $includeProperties
     * @param  boolean $includeUnset
     * @param  boolean $includeExtended
     * @return array
     */
    public function export($includeProperties = null, $includeUnset = false, $includeExtended = false)
    {
        $return = array();

        /* @var $item \Contain\Entity\EntityInterface */
        foreach ($this as $item) {
            $return[] = $item->export($includeProperties, $includeUnset, $includeExtended);
        }

        return $return;
    }

    /**
     * Exports the cursor as a hydrated array.
     *
     * @return \Contain\Entity\EntityInterface[]
     */
    public function toArray()
    {
        $return = array();

        // disable object re-use
        $this->entity = false;

        foreach ($this as $item) {
            $return[] = $item;
        }

        // re-enable it
        $this->entity = null;

        return $return;
    }

    /**
     * Rewind iterator
     *
     * @return  void
     */
    public function rewind()
    {
        if ($this->cursor instanceof Iterator) {
            $this->cursor->rewind();
        }

        $this->position = 0;
    }

    /**
     * Returns a count of items.
     *
     * @return  integer
     */
    public function count()
    {
        if (is_array($this->cursor)) {
            return count($this->cursor);
        }

        return iterator_count($this->cursor);
    }

    /**
     * Get current item
     *
     * @return  mixed
     */
    public function current()
    {
        if ($this->cursor instanceof Iterator) {
            $entity = $this->cursor->current();
        } elseif (!empty($this->options['reverse'])) {
            $entity = $this->cursor[$this->count() - $this->position - 1];
        } else {
            $entity = $this->cursor[$this->position];
        }

        $return = $this->mapper->hydrate($entity, $this->entity !== false ? $this->entity : null);

        $this->getEventManager()->trigger('hydrate', $return, array(
            'index' => $this->position,
        ));

        // be memory efficient and reuse this entity for the next iteration
        if ($this->entity === null) {
            $this->entity = $return;
        }

        return $return;
    }

    /**
     * Get current position
     *
     * @return  integer
     */
    public function key()
    {
        if ($this->cursor instanceof Iterator) {
            return $this->cursor->key();
        }

        return $this->position;
    }

    /**
     * Advances the iterator
     *
     * @return  void
     */
    public function next()
    {
        if ($this->cursor instanceof Iterator) {
            $this->cursor->next();

            return;
        }

        $this->position += 1;
    }

    /**
     * Checks if the current position is valid
     *
     * @return  boolean
     */
    public function valid()
    {
        if ($this->cursor instanceof Iterator) {
            return $this->cursor->valid();
        }

        return isset($this->cursor[$this->position]);
    }
}
