<?php

namespace ContainMapper\Driver\DBAL;

use ContainMapper\Driver\ConnectionInterface;
use Doctrine\DBAL\Connection as DBALConnection;

class Connection implements ConnectionInterface
{
    /**
     * @var AdapterInterface
     */
    protected $connection;

    /**
     * Constructor
     *
     * @param \Doctrine\DBAL\Connection $connection DBAL Connection
     */
    public function __construct(DBALConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns the DBAL Connection
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

}
