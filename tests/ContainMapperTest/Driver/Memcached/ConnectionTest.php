<?php
namespace ContainMapperTest\Driver\Memcached;

use ContainMapper\Driver\Memcached;
use Memcached as PHPMemcached;

class ConnectionTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped(
                'The Memcached extension is not available.'
            );
        }
    }

    public function testConstruct()
    {
        $memcached = new PHPMemcached();
        $memcached->addServer('127.0.0.1', 11211);
        $memcached->setOption(PHPMemcached::OPT_PREFIX_KEY, (string) microtime(true)); // sandbox the test
        $connection = new Memcached\Connection($memcached);
        $this->assertInstanceOf('Memcached', $connection->getConnection());
    }
}
