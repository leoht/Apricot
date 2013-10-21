<?php

namespace Apricot\Tests\Component;

use Apricot\Apricot;

class CacheTest extends \PHPUnit_Framework_TestCase
{
    public function testCacheStoreValue()
    {
        Apricot::cache('foo', 'Bar', 1);

        $this->assertTrue('Bar' === Apricot::cache('foo'));
    }

    public function testCacheIsCleared()
    {
        Apricot::cache('baz', 'Bar', 1);

        Apricot::purge();

        $this->assertFalse(Apricot::cache('baz'));
    }
}