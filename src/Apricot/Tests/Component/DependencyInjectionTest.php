<?php

namespace Apricot\Tests\Component;

use Apricot\Apricot;

class DependencyInjectionTest extends \PHPUnit_Framework_TestCase
{

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
