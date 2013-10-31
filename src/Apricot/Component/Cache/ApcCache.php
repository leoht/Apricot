<?php

namespace Apricot\Component\Cache;

trait ApcCache
{
    public static function cacheWithApc($key, $value = null, $expire = 3600, $forceOverride = false)
    {
        // add data into cache
        if (null !== $value && (!apc_exists($key) || $forceOverride)) {
            apc_delete($key);
            if (!apc_add($key, $value, $expire)) {
                throw new \RuntimeException(sprintf("Could not store data into cache (with name '%s')", $key));
            }

            return $value;
        } else {
            $old = apc_fetch($key);

            if ($value == $old) {
                return $old;
            } else {
                apc_store($key, $value);
            }

            return $value;
        }
    }

    public static function purgeWithApc()
    {
        apc_clear_cache('user');
    }
}
