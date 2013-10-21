<?php

namespace Apricot\Component;

trait Cache
{
    public static function cache($key, $value = null, $expire = 3600)
    {
        // add data into cache
        if (null !== $value) {
            if (function_exists('apc_add')) {
                if (!apc_add($key, $value, $expire)) {
                    throw new \RuntimeException(sprintf("Could not store data into cache (with name '%s')", $key));
                }
            }
        } else {
            if (function_exists('apc_fetch')) {
                return apc_fetch($key);
            }
        }
    }

    public static function purge()
    {
        if (function_exists('apc_clear_cache')) {
            return apc_clear_cache('user');
        }
    }
}