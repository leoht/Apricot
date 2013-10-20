<?php

namespace Apricot\Tests\Component;

use Apricot\Apricot;

class RouteTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Apricot\Component\Route::when
     */
    public function testRouteMatch()
    {
        Apricot::reset();
        Apricot::setEnvironment('test');
        
        Apricot::when('/', function ()
        {
            echo 'Hello!';
        });

        $this->assertTrue('Hello!' == Apricot::browse('/'));
    }

    /**
     * @covers Apricot\Component\Route::when
     */
    public function testRouteMatchWithParams()
    {
        Apricot::reset();

        Apricot::when('/:first_name/:last_name', function ($first_name, $last_name)
        {
            echo "Hello $first_name $last_name";
        });

        $this->assertTrue('Hello Foo Bar' == Apricot::browse('/Foo/Bar'));
    }

    public function testRouteMatchWithPrefix()
    {
        Apricot::reset();
        Apricot::setEnvironment('test');

        Apricot::prefix('/foo', function ()
        {
            Apricot::when('/bar', function ()
            {
                echo 'Foo!';
            });
        });

        $this->assertTrue('Foo!' === Apricot::browse('/foo/bar'));
    }

    public function testRouteMatchWithDeepPrefix()
    {
        Apricot::reset();
        Apricot::setEnvironment('test');

        Apricot::prefix('/foo', function ()
        {
            Apricot::when('/', function ()
            {
                echo "Foo!";
            });

            Apricot::prefix('/bar', function ()
            {
                Apricot::when('/', function ()
                {
                    echo "Bar!";
                });

                Apricot::when('/baz', function ()
                {
                    echo "Baz!";
                });
            });
        });

        $this->assertTrue('Foo!' === Apricot::browse('/foo'));
        $this->assertTrue('Bar!' === Apricot::browse('/foo/bar'));
        $this->assertTrue('Baz!' === Apricot::browse('/foo/bar/baz'));
    }

    /**
     * @covers Apricot\Component\Route::when
     * @covers Apricot\Component\Route::with
     */
    public function testRouteMatchRequirements()
    {
        Apricot::reset();
        Apricot::setEnvironment('test');

        Apricot::when('/:page', Apricot::with(array('page' => '\d+'), function ($page)
        {
            echo "Page $page";
        }));

        Apricot::notFound(function ()
        {
            echo "Not Found";
        });

        $this->assertTrue("Page 4" === Apricot::browse('/4'));
        $this->assertTrue("Not Found" === Apricot::browse('/foo'));
    }

    /**
     * @covers Apricot\Component\Route::generate
     */
    public function testGenerateUrlWithRouteName()
    {
        Apricot::reset();
        Apricot::setEnvironment('test');

        Apricot::when('/:first_name/:last_name', function ()
        {
            // nothing to see here
        }, 'hello_page');

        $url = Apricot::url('hello_page', array('first_name' => 'Foo', 'last_name' => 'Bar'));

        $this->assertTrue('/Foo/Bar' === $url);
    }

    /**
     * @covers Apricot\Component\Route::generate
     */
    public function testGenerateUrlWithPath()
    {
        Apricot::reset();
        Apricot::setEnvironment('test');

        $url = Apricot::url('/:first_name/:last_name', array('first_name' => 'Foo', 'last_name' => 'Bar'));

        $this->assertTrue('/Foo/Bar' === $url);
    }

    public function testSecureRouteNotMatchIfNoHttps()
    {
        Apricot::reset();
        Apricot::setEnvironment('test');

        Apricot::when('/secured', Apricot::with(array('_secure' => true), function ()
        {
            echo 'Foo';
        }));

        Apricot::notFound(function ()
        {
            echo 'Not Found';
        });

        $this->assertTrue('Not Found' === Apricot::browse('/secured'));
    }
}
