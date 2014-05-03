<?php
namespace ContainMapperTest\Driver\MongoDB;

use ContainTest\Entity\SampleChildEntity;
use ContainTest\Entity\SampleEntity;
use ContainTest\Entity\SampleMultiTypeEntity;
use Mongo;

class DriverTest extends \PHPUnit_Framework_TestCase
{
    protected $driver;
    protected $entity;

    public function setUp()
    {
        if (!extension_loaded('mongo')) {
            $this->markTestSkipped(
                'The Mongo extension is not available.'
            );
        }

        $db = new Mongo('mongodb://127.0.0.1');
        $db->test->test->drop();
        $connection = new \ContainMapper\Driver\MongoDB\Connection($db, 'test', 'test');
        $this->driver = new \ContainMapper\Driver\MongoDB\Driver($connection);
        $this->entity = new SampleChildEntity(array('firstName' => 'test'));
    }

    public function testGetUpdateCriteriaUnsetsProperty()
    {
        $entity = new SampleMultiTypeEntity(array('string' => 'test1', 'entity' => $this->entity));
        $entity->clean();
        $entity->clear('string');
        $this->assertEquals(array('$unset' => array('string' => true)), $this->driver->getUpdateCriteria($entity));
    }

    public function testGetUpdateCriteriaUnsetsSubDocumentProperty()
    {
        $entity = new SampleMultiTypeEntity(array('string' => 'test1', 'entity' => $this->entity));
        $entity->clean()->getEntity()->clear('firstName');
        $this->assertEquals(array('$unset' => array('entity.firstName' => true)), $this->driver->getUpdateCriteria($entity));
    }

    public function testGetUpdateCriteriaSetsSubDocumentProperty()
    {
        $entity = new SampleMultiTypeEntity(array('string' => 'test1', 'entity' => $this->entity));
        $entity->clean()->getEntity()->setFirstName('Mrs.');
        $this->assertEquals(array('$set' => array('entity.firstName' => 'Mrs.')), $this->driver->getUpdateCriteria($entity));
    }

    public function testGetInsertCriteriaDoesntIncludeSubDocumentProperty()
    {
        $entity = new SampleMultiTypeEntity(array('string' => 'test1', 'entity' => $this->entity));
        $entity->clean()->getEntity()->clear('firstName')->clean('firstName');
        $criteria = $this->driver->getInsertCriteria($entity);
        $this->assertInstanceOf('MongoId', $criteria['_id']);
        $this->assertEquals('test1', $criteria['string']);
        $this->assertEquals(array('string', '_id'), array_keys($criteria));
    }

    public function testGetInsertCriteriaDoesIncludeSubDocumentProperty()
    {
        $entity = new SampleMultiTypeEntity(array('string' => 'test1', 'entity' => $this->entity));
        $criteria = $this->driver->getInsertCriteria($entity);
        $this->assertInstanceOf('MongoId', $criteria['_id']);
        $this->assertEquals('test1', $criteria['string']);
        $this->assertEquals(array('firstName' => 'test'), $criteria['entity']);
        $this->assertEquals(array('string', 'entity', '_id'), array_keys($criteria));
    }

    public function testPersist()
    {
        $this->assertSame($this->driver, $this->driver->persist($this->entity));
        $data = $this->driver->findOne(array('_id' => 'test'));
        $this->assertEquals($this->entity->export(), array('firstName' => 'test'));
        $this->assertEquals('test', $this->entity->getExtendedProperty('_id'));
    }

    public function testPersistAutoId()
    {
        $entity = new SampleEntity();
        $this->driver->persist($entity);
        $this->assertTrue((boolean) $entity->getExtendedProperty('_id'));
    }

    public function testPersistAutoIdIsMongoId()
    {
        $entity = new SampleEntity();
        $this->driver->persist($entity);
        $this->assertInstanceOf('MongoId', $entity->getExtendedProperty('_id'));
    }

    public function testPersistSavesSetMongoId()
    {
        $entity = new SampleEntity();
        $id = new \MongoId();
        $entity->setExtendedProperty('_id', $id);
        $this->driver->persist($entity);
        $this->assertInstanceOf('MongoId', $entity->getExtendedProperty('_id'));
        $this->assertEquals((string) $id, (string) $entity->getExtendedProperty('_id'));
    }

    public function testPersistSavesExplicitlySetMongoId()
    {
        $entity = new SampleEntity();
        $entity->define('id', 'mongoId', array(
            'primary' => true,
        ));
        $id = new \MongoId();
        $entity->setId($id);
        $this->driver->persist($entity);
        $this->assertInstanceOf('MongoId', $entity->getId());
        $this->assertEquals((string) $id, (string) $entity->getId());
        $this->assertEquals((string) $id, (string) $entity->getExtendedProperty('_id'));
    }

    public function testPersistSavesMongoIdToPrimary()
    {
        $entity = new SampleEntity(array('firstName' => 'Mr.'));
        $entity->define('id', 'mongoId', array(
            'primary' => true,
        ));
        $this->driver->persist($entity);
        $this->assertInstanceOf('MongoId', $entity->getId());
        $this->assertEquals((string) $entity->getId(), (string) $entity->getExtendedProperty('_id'));
    }

    public function testDelete()
    {
        $this->driver->persist($this->entity);
        $data = $this->driver->findOne(array('firstName' => 'test'));
        $this->driver->delete($this->entity);
        $this->assertFalse($this->driver->findOne(array('_id' => 'test')));
    }

    public function testFindOne()
    {
        $this->driver->persist($this->entity);
        $this->assertEquals(
            array('_id' => 'test'),
            $this->driver->findOne(array('_id' => 'test'))
        );
    }

    public function testFind()
    {
        $entity1 = new SampleChildEntity(array('firstName' => 'test1'));
        $entity1->setFirstName('test1');
        $this->driver->persist($entity1);

        $entity2 = new SampleChildEntity(array('firstName' => 'test2'));
        $entity2->setFirstName('test2');
        $this->driver->persist($entity2);

        $cursor = $this->driver->find(array('_id' => array('$in' => array('test1', 'test2', 'test3'))));
        $this->assertInstanceOf('MongoCursor', $cursor);

        $rows = array();
        foreach ($cursor as $row) {
            $rows[] = $row;
        }

        $this->assertEquals(
            array(
                array('_id' => 'test1'),
                array('_id' => 'test2'),
            ),
            $rows
        );
    }

    public function testGetUpdateCriteria()
    {
        $entity1 = new SampleMultiTypeEntity(array('string' => 'test1', 'entity' => $this->entity));
        $this->assertEquals(
            array('$set' => array(
                'string' => 'test1',
                'entity.firstName' => 'test',
            )),
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

    public function testGetUpdateCriteriaEmptyIfNothingToDo()
    {
        $entity = new SampleMultiTypeEntity(array('string' => 'test1', 'entity' => $this->entity));
        $entity->clean();
        $this->assertEquals(array(), $this->driver->getUpdateCriteria($entity));
    }
}
