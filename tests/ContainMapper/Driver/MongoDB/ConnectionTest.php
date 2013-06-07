<?php
namespace ContainMapperTest\Driver\MongoDB;

use ContainMapper\Driver\MongoDB;
use Mongo;

class Test extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        if (!extension_loaded('mongo')) {
            $this->markTestSkipped(
                'The Mongo extension is not available.'
            );
        }
    }

    public function testConstruct()
    {
        $db = new Mongo('mongodb://127.0.0.1');
        $db->test->test->drop();
        $connection = new MongoDB\Connection($db, 'test', 'test');
        $this->assertInstanceOf('Mongo', $connection->getConnection());
    }
}
