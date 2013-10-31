<?php

namespace Apricot\Component;

use Closure;

trait Http
{
    /**
     * @var array
     */
    protected $validMethods = array('GET', 'HEAD', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS');

    /**
     * @var integer
     */
    protected $cacheExpire = 0;

    /**
    * @var Closure
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
            // if a closure has been provided as second argument
            if ($content instanceof Closure) {
                return call_user_func_array($content, param_arr);
            }

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

    /**
     * Registers a callback triggered when a 403 Acess Denied response is sent.
     */
    public static function accessDenied(Closure $callback)
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
        if ('test' != $apricot->environment) {
            exit;
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

        if ('test' != $apricot->environment) {
            exit;
        }
    }
}
