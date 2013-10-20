<?php

namespace Apricot\Tests\Component;

use Apricot\Apricot;

class MiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testMiddlewareIsCalled()
    {
        Apricot::reset();

        Apricot::add(function ()
        {
            echo 'Foo';
        });

        $this->assertTrue('Foo' === Apricot::browse('/'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testExceptionPassedThroughMiddlewares()
    {
        Apricot::reset();

        Apricot::add(function ()
        {
            throw new \Exception();
        });

        Apricot::add(function ($e)
        {
            throw new \RuntimeException();
        });

        Apricot::browse('/');
    }
}
