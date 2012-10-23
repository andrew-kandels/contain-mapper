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

namespace ContainMapper\Driver\Memcached;

use ContainMapper\Exception;
use ContainMapper\Driver\ConnectionInterface;
use Memcached;

/**
 * Memcached Driver Connection
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
    protected $connection;

    /**
     * Constructor
     *
     * @param   Memcached               Memcached connection
     * @return  $this
     */
    public function __construct(Memcached $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Return the directory as the raw connection for clients.
     *
     * @return  string
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
