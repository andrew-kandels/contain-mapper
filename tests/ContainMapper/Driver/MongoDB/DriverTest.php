<?php
namespace ContainMapperTest\Driver\MongoDB;

use Mongo;
use ContainTest\Entity\SampleChildEntity;
use ContainTest\Entity\SampleEntity;
use ContainTest\Entity\SampleMultiTypeEntity;

class DriverTest extends \PHPUnit_Framework_TestCase
{
    protected $driver;
    protected $entity;

    public function setUp()
    {
        $db = new Mongo('mongodb://127.0.0.1');
        $db->test->test->drop();
        $connection = new \ContainMapper\Driver\MongoDB\Connection($db, 'test', 'test');
        $this->driver = new \ContainMapper\Driver\MongoDB\Driver($connection);
        $this->entity = new SampleChildEntity(array('firstName' => 'test'));
    }

    public function testPersist()
    {
        $this->assertSame($this->driver, $this->driver->persist($this->entity));
        $data = $this->driver->findOne(array('firstName' => 'test'));
        $this->assertEquals($this->entity->export() + array('_id' => 'test'), $data); 
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
        $data = $this->driver->findOne(array('firstName' => 'test'));
        $this->assertEquals($this->entity->export() + array('_id' => 'test'), $data); 
        $this->driver->delete($this->entity);
        $this->assertFalse($this->driver->findOne(array('firstName' => 'test')));
    }

    public function testFindOne()
    {
        $this->driver->persist($this->entity);
        $this->assertEquals($this->entity->export() + array('_id' => 'test'), $this->driver->findOne(array('firstName' => 'test')));
    }

    public function testFind()
    {
        $entity1 = new SampleChildEntity(array('firstName' => 'test1'));
        $entity1->setFirstName('test1');
        $this->driver->persist($entity1);

        $entity2 = new SampleChildEntity(array('firstName' => 'test2'));
        $entity2->setFirstName('test2');
        $this->driver->persist($entity2);

        $this->assertEquals(
            array(
                $entity1->export() + array('_id' => 'test1'),
                $entity2->export() + array('_id' => 'test2'),
            ),
            $this->driver->find(array('firstName' => array('$in' => array('test1', 'test2', 'test3'))))
        );
    }

    public function testGetUpdateCriteria()
    {
        $entity1 = new SampleMultiTypeEntity(array('string' => 'test1', 'entity' => $this->entity));
        $this->assertEquals(
            array(
                'string' => 'test1',
                'entity.firstName' => 'test',
            ), 
            $this->driver->getUpdateCriteria($entity1)
        );
        $entity1->clean();
        $this->assertEquals(array(), $this->driver->getUpdateCriteria($entity1));
    }

    public function testIncrement()
    {
        $entity = new SampleMultiTypeEntity(array('string' => 'test1', 'entity' => $this->entity));
        $this->driver->persist($entity);
        $this->driver->increment($entity, 'integer', 5);
        $data = $this->driver->findOne(array('string' => 'test1'));
        $this->assertEquals(5, $data['integer']);
    }

    public function testPush()
    {
        $entity = new SampleMultiTypeEntity(array('string' => 'test1', 'entity' => $this->entity));
        $this->driver->persist($entity);
        $this->driver->push($entity, 'list', 5);
        $data = $this->driver->findOne(array('string' => 'test1'));
        $this->assertEquals(array(5), $data['list']);
    }

    public function testPull()
    {
        $entity = new SampleMultiTypeEntity(array('string' => 'test1', 'entity' => $this->entity));
        $this->driver->persist($entity);
        $this->driver->push($entity, 'list', 5);
        $this->driver->push($entity, 'list', 4);
        $this->driver->pull($entity, 'list', 5);
        $data = $this->driver->findOne(array('string' => 'test1'));
        $this->assertEquals(array(4), $data['list']);
    }
}