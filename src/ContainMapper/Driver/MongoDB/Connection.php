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

namespace ContainMapper\Driver\MongoDB;

use ContainMapper\Driver\ConnectionInterface;
use ContainMapper\Exception;
use Mongo;
use MongoClient;

/**
 * MongoDB Connection
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class Connection implements ConnectionInterface
{
    /**
     * @var Mongo
     */
    protected $connection;

    /**
     * @var MongoCollection
     */
    protected $collection;

    /**
     * @var string
     */
    protected $collectionName;

    /**
     * @var string
     */
    protected $databaseName;

    /**
     * @var MongoDB
     */
    protected $database;

    /**
     * Constructor
     *
     * @param   Mongo                       Mongo Database Connection Instance
     * @param   string                      Name of the MongoDB database
     * @param   string                      Name of the MongoDB collection
     * @return self
     */
    public function __construct($connection, $databaseName, $collectionName)
    {
        $this->databaseName = $databaseName;
        $this->collectionName = $collectionName;
        $this->connection = $connection;

        if (!$this->connection instanceof Mongo &&
            !$this->connection instanceof MongoClient) {
            throw new RuntimeException('$connection must be an instance of Mongo or '
                . 'MongoClient'
            );
        }
    }

    /**
     * Return the Mongo database.
     *
     * @return  MongoDB
     */
    public function getDatabase()
    {
        if ($this->database) {
            return $this->database;
        }

        $database = $this->databaseName;
        $this->database = $this->getConnection()->$database;

        return $this->database;
    }

    /**
     * Return the MongoDB collection.
     *
     * @return  MongoCollection
     */
    public function getCollection()
    {
        if ($this->collection) {
            return $this->collection;
        }

        $collection = $this->collectionName;
        $this->collection = $this->getDatabase()->$collection;

        return $this->collection;
    }

    /**
     * Builds a connection to MongoDB and returns the Mongo object.
     *
     * @return  Mongo
     */
    public function getConnection()
    {
        return $this->connection;
    }

}
