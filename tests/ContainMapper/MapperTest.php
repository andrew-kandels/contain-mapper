<?php
namespace ContainMapperTest;

use Mongo;
use ContainTest\Entity\SampleMultiTypeEntity;

class MapperTest extends \PHPUnit_Framework_TestCase
{
    protected $mapper;
    protected $entity;

    public function setUp()
    {
        $db = new Mongo('mongodb://127.0.0.1');
        $db->test->test->drop();
        $connection = new \ContainMapper\Driver\MongoDB\Connection($db, 'test', 'test');
        $driver = new \ContainMapper\Driver\MongoDB\Driver($connection);
        $this->mapper = new \ContainMapper\Mapper('ContainTest\Entity\SampleMultiTypeEntity', $driver);
        $this->entity = new SampleMultiTypeEntity(array('string' => 'test'));
    }

    public function testSetAndGetDriver()
    {
        $this->assertInstanceOf('ContainMapper\Driver\MongoDB\Driver', $this->mapper->getDriver());

        $connection = new \ContainMapper\Driver\File\Connection('/tmp');
        $driver = new \ContainMapper\Driver\File\Driver($connection);

        $this->mapper->setDriver($driver);
        $this->assertInstanceOf('ContainMapper\Driver\File\Driver', $this->mapper->getDriver());
    }

    public function testHydrate()
    {
        $status = new \stdclass();
        $entity = $this->mapper->hydrate(array('string' => 'test'));
        $this->assertEquals($entity->export(), $entity->export());
        $this->assertTrue($entity->isPersisted());
        $this->assertEquals(array(), $entity->dirty());
    }

    public function testFindOne()
    {
        $this->mapper->persist($this->entity);
        $this->assertInstanceOf('Contain\Entity\AbstractEntity', $this->mapper->findOne(array('string' => 'test')));
        $this->assertEquals($this->entity->export(), $this->mapper->findOne(array('string' => 'test'))->export());
        $this->assertFalse($this->mapper->findOne(array('string' => 'doesnotexist')));
    }

    public function testFind()
    {
        $this->mapper->persist($this->entity);
        $results = $this->mapper->find(array('string' => 'test'));
        $this->assertInstanceOf('Contain\Entity\AbstractEntity', $results->current());
        $this->assertEquals(array($this->entity->export()), array_map(function ($a) {
            return $a->export();
        }, array_values(iterator_to_array($results))));
        $rs = $this->mapper->find(array('string' => 'doesnotexist'));
        foreach ($rs as $notFound) { break; }
        $this->assertTrue(empty($notFound));
    }

    public function testDelete()
    {
        $this->mapper->persist($this->entity);

        $pre = new \stdclass();
        $this->entity->attach('delete.pre', function () use ($pre) {
            $pre->called = true;
        });

        $post = new \stdclass();
        $this->entity->attach('delete.post', function () use ($post) {
            $post->called = true;
        });

        $this->assertSame($this->mapper, $this->mapper->delete($this->entity));
        $this->assertTrue($pre->called);
        $this->assertTrue($post->called);
        $this->assertFalse($this->mapper->findOne(array('string' => 'test')));
        $this->assertEquals(array(), $this->entity->dirty());
    }

    public function testPersistNotDirty()
    {
        $pre = new \stdclass();
        $this->mapper->getEventManager()->attach('insert.pre', function () use ($pre) {
            $pre->called = true;
        });

        $post = new \stdclass();
        $this->mapper->getEventManager()->attach('insert.post', function () use ($post) {
            $post->called = true;
        });

        $this->entity->clean()->persisted();
        $this->assertSame($this->mapper, $this->mapper->persist($this->entity));
        $this->assertTrue(empty($pre->called));
        $this->assertTrue(empty($post->called));
    }

    public function testPersistNotDirtyButFlag()
    {
        $pre = new \stdclass();
        $this->entity->attach('insert.pre', function () use ($pre) {
            $pre->called = true;
        });

        $post = new \stdclass();
        $this->entity->attach('insert.post', function () use ($post) {
            $post->called = true;
        });

        $this->entity->clean();
        $this->assertSame($this->mapper, $this->mapper->persist($this->entity, false));
        $this->assertTrue($pre->called);
        $this->assertTrue($post->called);
    }

    public function testPersist()
    {
        $pre = new \stdclass();
        $this->entity->attach('insert.pre', function () use ($pre) {
            $pre->called = true;
        });

        $post = new \stdclass();
        $this->entity->attach('insert.post', function () use ($post) {
            $post->called = true;
        });

        $this->assertSame($this->mapper, $this->mapper->persist($this->entity));
        $this->assertTrue($pre->called);
        $this->assertTrue($post->called);
    }

    public function testPersistUpdate()
    {
        $pre = new \stdclass();
        $this->entity->attach('update.pre', function () use ($pre) {
            $pre->called = true;
        });

        $post = new \stdclass();
        $this->entity->attach('update.post', function () use ($post) {
            $post->called = true;
        });

        $this->assertSame($this->mapper, $this->mapper->persist($this->entity));
        $this->assertSame($this->mapper, $this->mapper->persist($this->entity->setString('changed')));
        $this->assertTrue($pre->called);
        $this->assertTrue($post->called);
    }

    public function testResolve()
    {
        $this->entity->setList(array(1, 2, 3, 4, 5));
        $resolver = $this->mapper->resolve($this->entity, 'list.3');
        $this->assertEquals('list', $resolver->getProperty());
        $this->assertEquals(4, $resolver->getValue());
    }

    public function testIncrementWhenNotPersisted()
    {
        $this->setExpectedException(
            'ContainMapper\Exception\InvalidArgumentException',
            'Cannot increment properties as $entity has not been persisted.'
        );
        $this->mapper->increment($this->entity, 'integer', 1);
    }

    public function testPushWhenNotPersisted()
    {
        $this->setExpectedException(
            'ContainMapper\Exception\InvalidArgumentException',
            'Cannot push to $entity as this is an update operation and $entity has not been persisted.'
        );
        $this->mapper->push($this->entity, 'integer', 1);
    }

    public function testPullWhenNotPersisted()
    {
        $this->setExpectedException(
            'ContainMapper\Exception\InvalidArgumentException',
            'Cannot push to $entity as this is an update operation and $entity has not been persisted.'
        );
        $this->mapper->pull($this->entity, 'integer', 1);
    }

    public function testIncrement()
    {
        $pre = new \stdclass();
        $this->entity->attach('update.pre', function () use ($pre) {
            $pre->called = true;
        });

        $post = new \stdclass();
        $this->entity->attach('update.post', function () use ($post) {
            $post->called = true;
        });

        $this->assertSame($this->mapper, $this->mapper->persist($this->entity));
        $this->mapper->increment($this->entity, 'integer', 1);
        $this->assertEquals(1, $this->entity->getInteger());
        $entity = $this->mapper->findOne(array('string' => 'test'));
        $this->assertEquals(1, $entity->getInteger());
        $this->assertEquals(array(), $this->entity->dirty());
        $this->assertTrue($pre->called);
        $this->assertTrue($post->called);
    }

    public function testPush()
    {
        $pre = new \stdclass();
        $this->entity->attach('update.pre', function () use ($pre) {
            $pre->called = true;
        });

        $post = new \stdclass();
        $this->entity->attach('update.post', function () use ($post) {
            $post->called = true;
        });

        $this->assertSame($this->mapper, $this->mapper->persist($this->entity));
        $this->mapper->push($this->entity, 'list', 1);
        $this->assertEquals(array(1), $this->entity->getList());
        $entity = $this->mapper->findOne(array('string' => 'test'));
        $this->assertEquals(array(1), $entity->getList());
        $this->assertEquals(array(), $this->entity->dirty());
        $this->assertTrue($pre->called);
        $this->assertTrue($post->called);
    }

    public function testPull()
    {
        $pre = new \stdclass();
        $this->entity->attach('update.pre', function () use ($pre) {
            $pre->called = true;
        });

        $post = new \stdclass();
        $this->entity->attach('update.post', function () use ($post) {
            $post->called = true;
        });

        $this->entity->setList(array(1, 2, 3));
        $this->mapper->persist($this->entity);
        $this->mapper->pull($this->entity, 'list', 2);
        $this->assertEquals(array(1, 3), $this->entity->getList());
        $entity = $this->mapper->findOne(array('string' => 'test'));
        $this->assertEquals(array(1, 3), $entity->getList());
        $this->assertEquals(array(), $this->entity->dirty());
        $this->assertTrue($pre->called);
        $this->assertTrue($post->called);
    }
}
