<?php
namespace ContainMapperTest\Driver\File;

use ContainMapper\Driver\File;
use ContainTest\Entity\SampleChildEntity;

class DriverTest extends \PHPUnit_Framework_TestCase
{
    protected $driver;
    protected $entity;

    public function setUp()
    {
        $connection = new File\Connection('/tmp');
        $this->driver = new File\Driver($connection);
        $this->entity = new SampleChildEntity(array('firstName' => 'test'));
    }

    public function testPersist()
    {
        $this->assertSame($this->driver, $this->driver->persist($this->entity));
    }

    public function testFindOne()
    {
        $this->driver->persist($this->entity);
        $this->assertEquals($this->entity->export(), (array) $this->driver->findOne('test'));
    }

    public function testFind()
    {
        $this->driver->persist($this->entity);
        $this->assertEquals(array($this->entity->export()), (array) $this->driver->find());
    }

    public function testDelete()
    {
        $this->driver->persist($this->entity);
        $this->driver->delete($this->entity);
        $this->assertFalse($this->driver->findOne('test'));
    }
}
