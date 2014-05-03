<?php
namespace ContainMapperTest\Driver\ZendDb;

use ContainMapper\Driver\ZendDb;

class ConnectionTest extends \PHPUnit_Framework_TestCase
{
    protected $adapter;

    public function setUp()
    {
        $mockConnection = $this->getMock('Zend\Db\Adapter\Driver\ConnectionInterface');
        $mockDriver = $this->getMock('Zend\Db\Adapter\Driver\DriverInterface');
        $mockDriver->expects($this->any())->method('getConnection')->will($this->returnValue($mockConnection));
        $this->adapter = $this->getMock('Zend\Db\Adapter\Adapter', null, array($mockDriver));
    }

    public function testConstruct()
    {
        $connection = new ZendDb\Connection($this->adapter);
        $this->assertInstanceOf('Zend\Db\Adapter\Adapter', $connection->getConnection());
    }
}
