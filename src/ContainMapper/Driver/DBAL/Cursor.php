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

namespace ContainMapper\Driver\DBAL;

use Contain\Entity\EntityInterface;
use ContainMapper\Exception;
use ContainMapper;
use Iterator;

/**
 * PDO Cursor
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class Cursor implements Iterator
{
    /**
     * @var Doctrine\DBAL\Statement
     */
    protected $stmt;

    /**
     * @var ContainMapper\Driver\PDO\Driver
     */
    protected $driver;

    /**
     * @var integer
     */
    protected $iterator = 0;

    /**
     * @var array
     */
    protected $cache = array();

    /**
     * @var boolean
     */
    protected $eod = false;

    /**
     * Constructor
     *
     * @param   Doctrine\DBAL\Statement
     * @return  void
     */
    public function __construct($stmt, Driver $driver)
    {
        $this->driver   = $driver;
        $this->stmt     = $stmt;
        $this->iterator = 0;

        $this->stmt->setFetchMode(\PDO::FETCH_ASSOC);
    }

    /**
     * Rewind iterator
     *
     * @return  void
     */
    public function rewind()
    {
        $this->iterator = 0;
        $this->populate();
    }

    /**
     * Populates a cached index entry.
     *
     * @return  void
     */
    protected function populate()
    {
        if (isset($this->cache[$this->iterator])) {
            return;
        }

        if ($this->eod) {
            return;
        }

        if (!$row = $this->stmt->fetch()) {
            $this->eod = true;
            return;
        }

        $this->cache[$this->iterator] = $row;
    }

    /**
     * Get current item
     *
     * @return  array|null
     */
    public function current()
    {
        return $this->driver->convertResultRow($this->cache[$this->iterator]);
    }

    /**
     * Get current position
     *
     * @return  integer
     */
    public function key()
    {
        return $this->iterator;
    }

    /**
     * Advances the iterator
     *
     * @return  void
     */
    public function next()
    {
        $this->iterator++;
        $this->populate();
    }

    /**
     * Checks if the current position is valid
     *
     * @return  boolean
     */
    public function valid()
    {
        return isset($this->cache[$this->iterator]);
    }
}
