<?php

namespace Apricot\Tests\Component;

use Apricot\Apricot;

class HttpTest extends \PHPUnit_Framework_TestCase
{
    public function testCorrectMethodIsDetected()
    {
        Apricot::reset();

        Apricot::when('/', function ()
        {
            echo Apricot::method();
        });

        $this->assertTrue('GET' === Apricot::browse('/'));
        $this->assertTrue('POST' === Apricot::browse('/', 'POST'));
    }

    public function testAccessDeniedTriggered()
    {
        Apricot::reset();

        Apricot::when('/', function ()
        {
            Apricot::triggerAccessDenied('403 Access Denied');
        });

        $this->assertTrue('<h1>403 Access Denied</h1>' === Apricot::browse('/'));
    }

    public function testAccessDeniedTriggeredWithCallback()
    {
        Apricot::reset();
        
        Apricot::when('/', function ()
        {
            Apricot::triggerAccessDenied();
        });

        Apricot::accessDenied(function ()
        {
            echo 'Stop!';
        });

        $this->assertTrue('Stop!' === Apricot::browse('/'));
    }
}
