<?php

namespace Apricot\Tests;

use Apricot\Apricot;

class ApricotTest extends \PHPUnit_Framework_TestCase
{

    public function testResponseIsReturned()
    {
        //...
    }

    public function testFailureCallbackIsCalled()
    {
        Apricot::fail(function($e)
        {
            echo "Failed: ".$e->getMessage();
        });

        Apricot::when('/', function()
        {
            throw new \Exception("Whow!");
        });

        $this->assertTrue(Apricot::browse('/') === "Failed: Whow!");
    }

    public function testAppIsResetted()
    {
        Apricot::when('/', function ()
        {
        });

        $old = Apricot::getInstance();

        Apricot::reset();

        $new = Apricot::getInstance();

        $this->assertTrue($old !== $new);
    }

}