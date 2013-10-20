<?php

namespace Apricot\Tests\Component;

use Apricot\Apricot;

class EventTest extends \PHPUnit_Framework_TestCase
{

    public function testListenerIsCalled()
    {
        $hasToBeTrue = false;

        Apricot::on('foo', function () use (&$hasToBeTrue)
        {
            $hasToBeTrue = true;
        });

        Apricot::emit('foo');

        $this->assertTrue($hasToBeTrue);
    }

    public function testArgumentsPassedToListener()
    {
        $hasToBeTrue = false;

        Apricot::on('foo', function ($argument) use (&$hasToBeTrue)
        {
            $hasToBeTrue = $argument;
        });

        Apricot::emit('foo', array(true));

        $this->assertTrue($hasToBeTrue);
    }

    public function testEventIsCleared()
    {
        $hasToBeTrue = false;

        Apricot::on('foo', function ($argument) use (&$hasToBeTrue)
        {
            $hasToBeTrue = $argument;
        });

        // will delete the listener above
        Apricot::clear('foo');

        Apricot::emit('foo', array(true));

        $this->assertFalse($hasToBeTrue);
    }

    public function testListenersCalledOrderedByPriority()
    {
        $result = '';

        // should be called last
        Apricot::on('foo', function () use (&$result)
        {
            $result .= 'A';
        }, 20);

        // should be called first
        Apricot::on('foo', function () use (&$result)
        {
            $result .= 'B';
        }, 40);

        Apricot::emit('foo');

        $this->assertTrue('BA' === $result);
    }
}
