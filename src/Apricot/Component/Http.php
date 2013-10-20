<?php

namespace Apricot\Component;

trait Http
{
    /**
     * @var array
     */
    protected $validMethods = array('GET', 'HEAD', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS');

    /**
     * @var string
     */
    protected $cacheDir = 'cache';

    /**
     * @var integer
     */
    protected $cacheExpire = 0;

    /**
    * @var callable
    */
    protected $accessDeniedCallback;

    /**
     * Secured shortcut ($httponly default to true) for setcookie().
     */
    public function setCookie($name, $content, $expire = 0, $path = '/', $domain = null, $secure = false, $httponly = true)
    {
        setCookie($name, $content, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * Gets a cookie, or set it if it doesn't exist yet.
     */
    public function cookie($name, $content = null, $expire = 0, $path = '/', $domain = null, $secure = false, $httponly = true)
    {
        if (isset($_COOKIE[$name])) {
            return $_COOKIE[$name];
        }

        self::setCookie($name, $content, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * Simulates a browser and navigates to the given path.
     * The outputted content is buffered and returned, which can be useful for functional tests.
     *
     * @param string $path
     * @return string The page output.
     */
    public static function browse($path = '/', $method = 'GET')
    {
        $_REQUEST['_method'] = $method;

        ob_start();
        $_SERVER['PATH_INFO'] = $path;
        self::run();
        $response = ob_get_clean();

        return $response;
    }

    /**
     * Returns a request parameter.
     */
    public static function parameter($key)
    {
        if (isset($_REQUEST[$key])) {
            return $_REQUEST[$key];
        }

        return false;
    }

    /**
     * Returns the HTTP method used by the request.
     */
    public static function method()
    {
        // Browsers only support GET and POST,
        // using a _method parameter in the request
        // allows to artificially use others methods.
        if (isset($_REQUEST['_method'])) {

            return $_REQUEST['_method'];
        }

        return $_SERVER['HTTP_METHOD'];
    }

    public static function header($name, $value = null)
    {
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        header($name.': '.$value);
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

    /**
     * Parses an HTTP digest response header.
     */
    protected static function parseHttpDigest($digest)
    {
        // protect against missing data
        $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
        $data = array();
        $keys = implode('|', array_keys($needed_parts));

        preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $digest, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $data[$m[1]] = $m[3] ? $m[3] : $m[4];
            unset($needed_parts[$m[1]]);
        }

        return $needed_parts ? false : $data;
    }

    /**
     * Sends a HTTP request.
     */
    public static function request($method, $url, array $data = array())
    {
        $apricot = self::getInstance();

        if (!in_array($method, $apricot->validMethods)) {
            throw new \InvalidArgumentException(sprintf("'%s' is not a valid HTTP method.", $method));
        }

        $curl = curl_init();

        curl_setopt($curl, \CURLOPT_URL, $url);
        curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);

        if ('POST' == $method) {
            curl_setopt($curl, \CURLOPT_POST, true);
            curl_setopt($curl, \CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    public static function cache(callable $callback, $expire = 3600)
    {
        $apricot = self::getInstance();

        $apricot->cacheExpire = $expire;

        call_user_func($callback);
    }

    /**
     * Registers a callback triggered when a 403 Acess Denied response is sent.
     */
    public static function accessDenied(callable $callback)
    {
        $apricot = self::getInstance();

        $apricot->accessDeniedCallback = $callback;
    }

    /**
     * Registers a callback triggered when a 404 Not Found response is sent.
     */
    public static function triggerNotFound($message = "404 Not Found")
    {
        $apricot = self::getInstance();

        if (null != $apricot->notFoundCallback) {
            call_user_func_array($apricot->notFoundCallback, array());
        } else {
            echo "<h1>$message</h1>";
        }
    }

    /**
     * Triggers a 403 Access Denied response.
     */
    public static function triggerAccessDenied($message = "403 Access Denied")
    {
        $apricot = self::getInstance();

        if (null != $apricot->accessDeniedCallback) {
            call_user_func_array($apricot->accessDeniedCallback, array());
        } else {
            echo "<h1>$message</h1>";
        }
    }
}
