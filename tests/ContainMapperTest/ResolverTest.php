<?php
namespace ContainMapperTest;

use ContainMapper\Resolver;
use ContainTest\Entity\SampleChildEntity;
use ContainTest\Entity\SampleMultiTypeEntity;

class ResolverTest extends \PHPUnit_Framework_TestCase
{
    protected $entity;
    protected $resolver;

    public function setUp()
    {
        $this->entity = new SampleMultiTypeEntity(array('entity' => array('firstName' => 'test')));
        $this->resolver = new Resolver('entity.firstName');
    }

    public function testConstruct()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Resolve failed with invalid or non-existent query.'
        );

        $entity = new Resolver('');
    }

    public function testScan()
    {
        $this->assertInstanceOf('ContainMapper\\Resolver', $this->resolver->scan($this->entity));
    }

    public function testGetValue()
    {
        $this->assertEquals('test', $this->resolver->scan($this->entity)->getValue());
    }

    public function testGetProperty()
    {
        $this->assertEquals('firstName', $this->resolver->scan($this->entity)->getProperty());
    }

    public function testGetType()
    {
        $this->assertInstanceOf('Contain\Entity\Property\Type\StringType', $this->resolver->scan($this->entity)->getType());
    }

    public function testGetSteps()
    {
        $this->resolver->scan($this->entity);
        $steps = $this->resolver->getSteps();
        $this->assertEquals(1, count($steps));
        $this->assertInstanceOf('ContainMapper\Resolver', $steps[0]);
    }

    public function testClearSteps()
    {
        $this->resolver->scan($this->entity);
        $steps = $this->resolver->clearSteps()->getSteps();
        $this->assertEquals(array(), $steps);
    }

    public function testScanListWhenEmpty()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Resolver query \'entity.firstName\' failed on ContainTest\Entity\SampleMultiTypeEntity. Index 0 not set'
        );

        $this->resolver->scan($this->entity, 'listEntity.0');
    }

    public function testScanList()
    {
        $this->entity->setListEntity(array(new SampleChildEntity(array('firstName' => 'test'))));
        $this->assertInstanceOf('ContainMapper\Resolver', $this->resolver->scan($this->entity, 'listEntity.0'));
    }
}
