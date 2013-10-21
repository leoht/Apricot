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

    /**
     * Uses an HTTP Basic authentication to authenticate an user.
     */
    public static function httpBasic($realm, array $users, $triggerAccessDenied = false)
    {
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            if (in_array($_SERVER['PHP_AUTH_USER'], $users)) {
                if ($_SERVER['PHP_AUTH_PW'] === $users[$_SERVER['PHP_AUTH_USER']]) {
                    return true;
                }

                if ($triggerAccessDenied) {
                    self::triggerAccessDenied();
                } else {
                    return false;
                }

            } else {
                if ($triggerAccessDenied) {
                    self::triggerAccessDenied();
                } else {
                    return false;
                }
            }
        } else {
            self::header('WWW-Authenticate', 'Basic realm="'.$realm.'"');
            self::header('HTTP/1.0 401 Unauthorized');
            echo '<h1>401 Unauthorized</h1>';
            exit;
        }
    }

    /**
     * Uses an HTTP Digest authentication to authenticate an user.
     */
    public static function httpDigest($realm, array $users, $triggerAccessDenied = false)
    {
        if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
            if (!($data = self::parseHttpDigest($_SERVER['PHP_AUTH_DIGEST'])) || !isset($users[$data['username']])) {
                if ($triggerAccessDenied) {
                    self::triggerAccessDenied();
                } else {
                    return false;
                }
                exit;
            }

            $A1 = md5($data['username'] . ':' . $realm . ':' . $users[$data['username']]);
            $A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
            $validResponse = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

            if ($data['response'] != $validResponse) {
                if ($triggerAccessDenied) {
                    self::triggerAccessDenied();
                } else {
                    return false;
                }
            } else {
                return true;
            }

        } else {
            self::header('WWW-Authenticate', 'Digest realm="'.$realm.'",qop="auth",nonce="'.uniqid(rand()).'",opaque="'.md5($realm).'"');
            self::header('HTTP/1.0 401 Unauthorized');
            echo '<h1>401 Unauthorized</h1>';
            exit;
        }
    }
}