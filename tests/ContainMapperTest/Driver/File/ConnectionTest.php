<?php
namespace ContainMapperTest\Driver\File;

use ContainMapper\Driver\File;

class ConnectionTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $connection = new File\Connection('/tmp');
        $this->assertEquals('/tmp', $connection->getConnection());
    }

    public function testNonExisting()
    {
        $this->setExpectedException(
            'ContainMapper\Exception\InvalidArgumentException',
            '$path is not a directory'
        );
        $connection = new File\Connection('/broken/path/shouldnt/exist');
    }
}
