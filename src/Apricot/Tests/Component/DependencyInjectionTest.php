<?php

namespace Apricot\Tests\Component;

use Apricot\Apricot;

class DependencyInjectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Apricot\Component\DependencyInjection::set
     * @covers Apricot\Component\DependencyInjection::get
     */
    public function testParameterIsStored()
    {
        Apricot::set('foo', 'Bar');

        $this->assertTrue('Bar' === Apricot::get('foo'));
    }

    public function testParametersAndObjectsAreStored()
    {
        Apricot::sets(array(
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => new \stdClass(),
        ));

        $this->assertTrue('Foo' === Apricot::get('foo') && 'Bar' === Apricot::get('bar') && Apricot::get('baz') instanceof \stdClass);
    }

    /**
     * @covers Apricot\Component\DependencyInjection::set
     * @covers Apricot\Component\DependencyInjection::get
     */
    public function testObjectIsStored()
    {
        Apricot::reset();
        
        $object = new \stdClass();

        Apricot::set('bar', $object);

        $this->assertTrue(Apricot::get('bar') instanceof \stdClass);
    }

    /**
     * @covers Apricot\Component\DependencyInjection::provide
     * @covers Apricot\Component\DependencyInjection::get
     */
    public function testObjectIsCreatedFromClass()
    {
        Apricot::provide('baz', '\\stdClass');

        $this->assertTrue(Apricot::get('baz') instanceof \stdClass);
    }

    public function testObjectsAreCreatedFromClasses()
    {
        Apricot::reset();

        Apricot::provide(array(
            'foo' => array(
                'class' => '\\stdClass'
            ),
            'bar' => array(
                'class' => '\\stdClass'
            ),
        ));

        $this->assertTrue(Apricot::get('foo') instanceof \stdClass);
        $this->assertTrue(Apricot::get('bar') instanceof \stdClass);
    }

    /**
     * @covers Apricot\Component\DependencyInjection::provide
     * @covers Apricot\Component\DependencyInjection::get
     */
    public function testObjectIsCreatedWithArguments()
    {
        require 'Fixtures/DependencyInjectionFixture.php';

        Apricot::reset();

        Apricot::set('foo', 'Foo');
        Apricot::provide('bar', '\\stdClass');
        Apricot::provide('baz', '\\DependencyInjectionFixture', array('%foo%', '@bar'));
        
        $instance = Apricot::get('baz');

        $this->assertTrue('Foo' === $instance->a);
        $this->assertTrue($instance->b instanceof \stdClass);
    }

    /**
     * @covers Apricot\Component\DependencyInjection::scope
     */
    public function testScopeHoldVars()
    {
        Apricot::reset();

        Apricot::scope('main', function ($scope)
        {
            $scope->foo = 'Bar';
        });

        Apricot::scope('main', function ($scope)
        {
            $this->assertTrue('Bar' === $scope->foo);
        });
    }

    /**
     * @covers Apricot\Component\DependencyInjection::inject
     */
    public function testScopeInjection()
    {
        Apricot::reset();

        Apricot::scope('main');

        Apricot::inject('main', array('foo' => 'Bar'));

        Apricot::scope('main', function ($scope)
        {
            $this->assertTrue('Bar' === $scope->foo);
        });
    }

    /**
     * @expectedException \LogicException
     * @covers Apricot\Component\DependencyInjection::freeze
     * @covers Apricot\Component\DependencyInjection::scope
     */
    public function testThrowsExceptionIfAccessFrozenScope()
    {
        Apricot::reset();

        Apricot::scope('main', function ($scope)
        {
        });

        Apricot::freeze('main');

        // Now trying to re-open the scope should throw an exception.
        Apricot::scope('main', function ($scope)
        {
        });
    }
}
