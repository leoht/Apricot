<?php

namespace Apricot\Component;

use Closure;
use Apricot\Component\Cache\FilesystemCache;
use Apricot\Component\Cache\ApcCache;

trait Cache
{
    use FilesystemCache;
    use ApcCache;

    protected $cacheDir;

    public static function useCacheDir($cacheDir)
    {
        $apricot = self::getInstance();

        $apricot->cacheDir = $cacheDir;
    }

    public static function cacheDir()
    {
        $apricot = self::getInstance();

        return $apricot->cacheDir;
    }

    public static function cache($key, $value = null, $expire = 3600, $forceOverride = false)
    {
        if (null === self::cacheDir() && extension_loaded('apc')) {
            return self::cacheWithApc($key, $value, $expire, $forceOverride);
        } else {
            return self::cacheWithFile($key, $value, $expire, $forceOverride);
        }
    }

    public static function purge()
    {
        if (extension_loaded('apc')) {
            self::purgeWithApc();
        } else {
            self::purgeCacheFile();
        }
    }
}
