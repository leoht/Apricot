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

    protected function cacheRouteResult($route, $result)
    {

    }
}
