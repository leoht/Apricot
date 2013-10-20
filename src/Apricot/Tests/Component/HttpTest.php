<?php

namespace Apricot\Tests\Component;

use Apricot\Apricot;

class HttpTest extends \PHPUnit_Framework_TestCase
{
    public function testCorrectMethodIsDetected()
    {
        Apricot::when('/', function ()
        {
            echo Apricot::method();
        });

        $this->assertTrue('GET' === Apricot::browse('/'));
        $this->assertTrue('POST' === Apricot::browse('/', 'POST'));
    }
}
