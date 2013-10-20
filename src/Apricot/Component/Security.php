<?php

namespace Apricot\Component;

trait Security
{
    public static function session($key, $value = null)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (null === $value) {
            if (!array_key_exists($key, $_SESSION)) {
                return false;
            }

            return $_SESSION[$key];
        } else {
            $_SESSION[$key] = $value;
        }
    }

    public static function validateCsrf($requestToken = '_csrf_token')
    {
        if ($token = self::session('_csrf_token')) {
            if (!isset($_REQUEST['_csrf_token']) || $token !== $requestToken) {
                throw new \UnexpectedValueException(sprintf("Invalid CSRF token provided."));
            }
        } else {
            return false;
        }
    }

    /**
     * Secures an URL pattern and triggers a callback when
     * any URL matching this pattern is requested.
     */
    public static function secure($pattern, callable $callback)
    {
        self::on('match', function ($path, $parameters) use ($pattern, $callback)
        {
            if (preg_match('#^'.$pattern.'#', $path)) {
                if (false === call_user_func_array($callback, $parameters)) {
                    self::triggerAccessDenied();
                }
            }
        });
    }
}