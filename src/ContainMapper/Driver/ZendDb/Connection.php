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

use ContainMapper\Driver\ConnectionInterface;
use Zend\Db\Adapter\AdapterInterface;

/**
 * ZendDb Connection
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class Connection implements ConnectionInterface
{
    /**
     * @var Zend\Db\Adapter
     */
    protected $connection;

    /**
     * Constructor
     *
     * @param   Zend\Db\Adapter\AdapterInterface Zend Db Adapter
     * @return  Connection
     */
    public function __construct(AdapterInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns the Zend Db Adapter
     *
     * @return  Zend\Db\Adapter\AdapterInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

}
