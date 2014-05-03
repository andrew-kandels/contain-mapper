<?php
namespace ContainMapperTest\Driver\Memcached;

use ContainMapper\Driver\Memcached;
use ContainTest\Entity\SampleChildEntity;
use ContainTest\Entity\SampleEntity;
use Memcached as PHPMemcached;

class DriverTest extends \PHPUnit_Framework_TestCase
{
    protected $driver;
    protected $entity;

    public function setUp()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped(
                'The Memcached extension is not available.'
            );
        }

        $memcached = new PHPMemcached();
        $memcached->addServer('127.0.0.1', 11211);
        $memcached->setOption(PHPMemcached::OPT_PREFIX_KEY, (string) microtime(true)); // sandbox the test
        $connection = new Memcached\Connection($memcached);
        $this->driver = new Memcached\Driver($connection);
        $this->entity = new SampleChildEntity(array('firstName' => 'test'));
    }

    public function testPersist()
    {
        $this->assertSame($this->driver, $this->driver->persist($this->entity));
        $this->assertEquals($this->entity->export(), $this->driver->findOne('test'));
    }

    public function testPersistAutoId()
    {
        $entity = new SampleEntity();
        $this->driver->persist($entity);
        $this->assertTrue((boolean) $entity->getExtendedProperty('_id'));
    }

    public function testDelete()
    {
        $this->driver->persist($this->entity);
        $this->assertEquals($this->entity->export(), $this->driver->findOne('test'));
        $this->driver->delete($this->entity);
        $this->assertFalse($this->driver->findOne('test'));
    }

    public function testFindOne()
    {
        $this->driver->persist($this->entity);
        $this->assertEquals($this->entity->export(), $this->driver->findOne('test'));
    }

    public function testFind()
    {
        $entity1 = new SampleChildEntity(array('firstName' => 'test1'));
        $entity1->setFirstName('test1');
        $this->driver->persist($entity1);
        $this->assertEquals($entity1->export(), $this->driver->findOne('test1'));

        $entity2 = new SampleChildEntity(array('firstName' => 'test2'));
        $entity2->setFirstName('test2');
        $this->driver->persist($entity2);
        $this->assertEquals($entity2->export(), $this->driver->findOne('test2'));

        $this->assertEquals(
            array(
                'test1' => $entity1->export(),
                'test2' => $entity2->export(),
            ),
            $this->driver->find(array('test1', 'test2', 'test3'))
        );
    }
}
