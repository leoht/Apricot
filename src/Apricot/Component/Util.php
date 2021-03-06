<?php

namespace Apricot\Component;

use Closure;

trait Util
{
    public static function each(array $array, Closure $iteratorCallback)
    {
        $r = new \ReflectionFunction($iteratorCallback);
        $argc = count($r->getParameters());

        foreach($array as $key => $value) {
            $args = $argc < 2 ? array($value) : array($key, $value);
            call_user_func_array($iteratorCallback, $args);
        }
    }

    public static function map(array $array, Closure $iteratorCallback)
    {
        $r = new \ReflectionFunction($iteratorCallback);
        $argc = count($r->getParameters());

        foreach($array as $key => &$value) {
            $args = $argc < 2 ? array($value) : array($key, $value);
            call_user_func_array($iteratorCallback, $args);
        }
    }
}