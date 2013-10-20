<?php

namespace Apricot\Tests\Component;

use Apricot\Apricot;

class SecurityTest extends \PHPUnit_Framework_TestCase
{
    public function testUrlIsSecured()
    {
        Apricot::reset();
        Apricot::setEnvironment('test');

        Apricot::secure('/secured', function ($token)
        {
            return 'F00B4z' === $token;
        });

        Apricot::when('/secured/:token', function ()
        {
        });

        $this->assertTrue('' === Apricot::browse('/secured/F00B4z'));
        $this->assertTrue('<h1>403 Access Denied</h1>' === Apricot::browse('/secured/foo'));
    }

    public function testUrlIsSecuredWithParams()
    {
        Apricot::reset();
        Apricot::setEnvironment('test');

        Apricot::secure('/secured', function ($token)
        {
            echo "Hello $token";
            return true;
        });

        Apricot::when('/secured/:token', function ()
        {
        });

        $this->assertTrue('Hello F00B4z' === Apricot::browse('/secured/F00B4z'));
    }
}