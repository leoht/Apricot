<?php
/**
 * Apricot framework (one-file version).
 * Compiled on Sun 2013-10-20.
 *
 * Copyright (c) 2013 Léonard Hetsch <leo.hetsch@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Apricot\Component;

trait Middleware
{
    /**
     * @var array
     */
    protected $middlewares = array();

    /**
     * Adds a middleware callback.
     */
    public static function add(callable $callback, $type = self::BEFORE_REQUEST)
    {
        $apricot = static::getInstance();

        $apricot->middlewares[] = array(
            'callback' => $callback,
            'type' => $type,
        );
    }

    public static function runMiddlewares($type = self::BEFORE_REQUEST)
    {
        $apricot = static::getInstance();

        $middlewares = $apricot->middlewares;

        /** @var \Exception */
        $error = null;

        foreach($middlewares as $key => $middleware) {

            $hasNextMiddleware = array_key_exists($key+1, $middlewares);

            if ($type !== $middleware['type']) {
                continue;
            }

            $r = new \ReflectionFunction($middleware['callback']);
            $parameters = $r->getParameters();

            $next = $hasNextMiddleware ? $middlewares[$key+1] : function(){};

            try {
                $r->invokeArgs(array($error, $next));
            } catch(\Exception $e) {

                // If there is no more middleware to run, throw the exception.
                if (!$hasNextMiddleware) {
                    throw $e;
                }

                $error = $e;
            }
        }
    }
}


namespace Apricot\Component;

trait DependencyInjection
{
    /**
     * @var array
     */
    protected $services = array();

    /**
     * @var array
     */
    protected $parameters = array();

    /**
     * @var array
     */
    protected $scopes = array();

    /**
     * Sets a parameter or a service into the container.
     */
    public static function set($id, $value)
    {
        $apricot = self::getInstance();

        // an instantiated object
        if (is_object($value)) {
            $apricot->services[$id] = $value;
        } else {
            $apricot->parameters[$id] = $value;
        }
    }

    public static function get($id)
    {
        $apricot = self::getInstance();

        $service = $apricot->services[$id];

        return $service;
    }

    /**
     * Create and open a scope or re-open it if it has not been created yet.
     * The 2nd argument is a closure which takes only one argument: the scope that has been called.
     * A scope is a container that can only be accessed when calling it explicitly:
     *
     * <code>
     * Apricot::scope('main', function ($scope)
     * {
     *     $scope->foo = 'bar'
     * });
     * </code>
     * 
     * @param string $name
     * @param callable $callback
     * @throws \LogicException if the scope is frozen.
     */
    public static function scope($name, $callback = null)
    {
        $apricot = static::getInstance();

        if (!array_key_exists($name, $apricot->scopes)) {
            $apricot->scopes[$name] = array(
                'container' => new \stdClass(),
                'frozen' => false,
            );
        }

        if (true === $apricot->scopes[$name]['frozen']) {
            throw new \LogicException(sprintf("Cannot re-open frozen scope '%s'", $name));
        }

        if (null != $callback) {
            call_user_func_array($callback, array($apricot->scopes[$name]['container']));
        }
    }

    /**
     * Injects an array of values into a scope.
     */
    public static function inject($scope, array $arguments)
    {
        static::scope($scope, function($s) use ($arguments)
        {
            foreach($arguments as $name => $value) {
                $s->$name = $value;
            }
        });
    }

    /**
     * Freezes a scope, which means it cannot be opened again.
     * Trying to do so after freezing will throw an exception.
     *
     * @param string $scope
     * @throws \InvalidArgumentException if the scope doesn't exist.
     */
    public static function freeze($scope)
    {
        $apricot = static::getInstance();

        if (!array_key_exists($scope, $apricot->scopes)) {
            throw new \InvalidArgumentException(sprintf("Cannot freeze scope '%s', as it has not been declared yet.", $scope));
        }

        $apricot->scopes[$scope]['frozen'] = true;
    }
}


namespace Apricot\Component;

trait Util
{
    public static function each(array $array, $iteratorCallback)
    {
        $r = new \ReflectionFunction($iteratorCallback);
        $argc = count($r->getParameters());

        foreach($array as $key => $value) {
            $args = $argc < 2 ? array($value) : array($key, $value);
            call_user_func_array($iteratorCallback, $args);
        }
    }

    public static function map(array $array, $iteratorCallback)
    {
        $r = new \ReflectionFunction($iteratorCallback);
        $argc = count($r->getParameters());

        foreach($array as $key => &$value) {
            $args = $argc < 2 ? array($value) : array($key, $value);
            call_user_func_array($iteratorCallback, $args);
        }
    }
}


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



namespace Apricot\Component;

trait Rest
{
    /**
     * Defines a REST resource.
     */
    public static function resource($resource, callable $callback)
    {
        $apricot = self::getInstance();

        // is it a sub-resource ?
        if (null !== $apricot->prefix) {
            $parentResource = str_replace('/', '', $apricot->prefix);
            $parentResource = substr_replace($parentResource, '', -1);
            $apricot->prefix .= "/:{$parentResource}_id";
        }

        self::prefix('/' . $resource, $callback);
    }

    public static function index(callable $callback)
    {
        self::when('/', function () use ($callback)
        {
            call_user_func_array($callback, func_get_args());
        });
    }

    public static function show(callable $callback)
    {
        self::when('/:id', self::withNumber('id', function () use ($callback)
        {
            call_user_func_array($callback, func_get_args());
        }));
    }

    public static function create(callable $callback)
    {
        self::when('/', self::with(array('_method' => 'POST'), function () use ($callback)
        {
            call_user_func($callback);
        }));
    }

    public static function edit(callable $callack)
    {
        self::when('/:id/edit', self::withNumber('id', function () use ($callback)
        {
            call_user_func_array($callback, func_get_args(oid));
        }));
    }

    public static function update(callable $callback)
    {
        self::when('/:id', self::with(array('_method' => 'PUT', 'id' => '\d+'), function () use ($callback)
        {
            call_user_func_array($callback, func_get_args(oid));
        }));
    }

    public static function delete(callable $callback)
    {
        self::when('/:id', self::with(array('_method' => 'DELETE', 'id' => '\d+'), function () use ($callback)
        {
            call_user_func_array($callback, func_get_args(oid));
        }));
    }

    /**
     * Generates an URL related to a resource and an action.
     */
    public static function restUrl($resource, $action, $id = null)
    {
        $url = '/' . $resource;

        switch($action) {
            case 'show':
            case 'update':
            case 'delete':
                $url .= '/' . $id;
        }

        return $url;
    }
}


namespace Apricot\Component;

trait Route
{
    /**
     * @var array
     */
    protected $routes = array();

    /**
     * @var callable
     */
    protected $notFoundCallback;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var array
     */
    protected $prefixParameters = array();

    /**
     * Registers a route into Apricot.
     */
    public static function when($pattern, callable $callback, $name = null)
    {
        $apricot = self::getInstance();

        if (null !== $apricot->prefix) {
            $pattern = '/' === $pattern ? $apricot->prefix : $apricot->prefix . $pattern;
        }

        $originalPattern = $pattern;
        $pattern = preg_replace('#:([a-z_]+)#is', '([a-zA-Z0-9\-_]+)', $pattern);

        // Override an existing route with the exact same pattern
        foreach($apricot->routes as $routeName => $route) {
            if ($route['original_pattern'] == $originalPattern) {
                unset($apricot->routes[$routeName]);
            }
        }

        if (null === $name) {
            $name = count($apricot->routes);
        }

        $apricot->routes[$name] = array(
            'pattern' => $pattern,
            'original_pattern' => $originalPattern,
            'callback' => $callback
        );
    }

    /**
     * Add requirements to a route definition.
     * This is a middleware between Apricot::when() and the route callback.
     *
     * @param array $requirements An array of route requirements
     */
    public static function with(array $requirements, callable $callback)
    {
        $r = new \ReflectionFunction($callback);
        $arguments = $r->getParameters();

        return function() use ($arguments, $requirements, $callback)
        {
            $givenArguments = func_get_args();

            foreach($requirements as $parameter => $requirement) {

                if ('_secure' === $parameter && true == $requirement && ! isset($_SERVER['HTTPS'])) {
                    return false;
                }

                if ('_method' === $parameter && self::method() !== $requirement) {
                    return false;
                }

                foreach($arguments as $arg) {
                    if ($arg->getName() === $parameter && ! preg_match("#^$requirement$#", $givenArguments[$arg->getPosition()])) {
                        return false;
                    }
                }
            }

            call_user_func_array($callback, $givenArguments);
        };
    }

    public static function prefix($prefix, callable $callback)
    {
        $apricot = self::getInstance();

        $oldPrefix = $apricot->prefix;

        if (null !== $apricot->prefix) {
            $prefix = $apricot->prefix . $prefix;
        }

        $apricot->prefix = $prefix;

        call_user_func($callback);

        $apricot->prefix = $oldPrefix;
    }

    /**
     * Shortcut to add an integer requirement on a route parameter.
     */
    public static function withNumber($name, callable $callback)
    {
        return static::with(array($name => '\d+'), $callback);
    }

    // public static function forceHttps($pattern)
    // {
    //     $apricot = self::getInstance();

    //     foreach($apricot->routes as $name => $attributes) {
    //         if (preg_match('#^' . $pattern . '#', $attributes['pattern'])) {
    //             $apricot->routes[$name]['force_https'] = true;
    //         }
    //     }
    // }

    public static function url($path, array $parameters)
    {
        $pattern = null;

        $apricot = self::getInstance();

        // Is the path an URL ?
        if (false === strpos($path, '/')) {
            foreach($apricot->routes as $name => $attributes) {
                if ($name === $path) {
                    $pattern = $attributes['original_pattern'];
                }
            }

            if (!$pattern) {
                throw new \LogicException(sprintf("No route was found under name '%s'", $path));
            }

            $path = $pattern;
        }
        
        foreach($parameters as $name => $value) {
            $path = preg_replace('#/(:'.$name.')#isU', '/'.$value, $path);
        }

        return $path;
    }

    public static function notFound($callback)
    {
        $apricot = self::getInstance();

        $apricot->notFoundCallback = $callback;
    }
}


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
}


namespace Apricot\Component;

trait Event
{
    /**
     * @var array
     */
    protected $listeners = array();
    
    /**
     * Registers an event listener.
     */
    public static function on($event, callable $callback, $priority = 0)
    {
        $apricot = self::getInstance();

        $apricot->listeners[$event][] = array(
            'callback' => $callback,
            'priority' => $priority,
        );
    }

    /**
     * Emits an event into Apricot and wake up its listeners.
     */
    public static function emit($event, $arguments = array())
    {
        $apricot = self::getInstance();

        $apricot->wakeUpListeners($event, $arguments);
    }

    /**
     * Clears all listeners for a given event.
     *
     * @param string $event
     */
    public static function clear($event)
    {
        $apricot = self::getInstance();

        unset($apricot->listeners[$event]);
    }

    /**
     * Wakes up listeners of a specific event.
     */
    public function wakeUpListeners($event, array $arguments)
    {
        foreach($this->listeners as $e => $listeners)
        {
            if ($e !== $event) {
                continue;
            }

            usort($listeners, function ($a, $b)
            {
                return $a['priority'] > $b['priority'] ? -1 : 1;
            });

            foreach($listeners as $listener) {
                $listenerResponse = call_user_func_array($listener['callback'], $arguments);
                
                if (null !== $listenerResponse) {
                    return $listenerResponse;
                }
            }
        }

        return false;
    }
}


namespace Apricot;

class Apricot
{
    use Component\Http;
    use Component\Route;
    use Component\Event;
    use Component\DependencyInjection;
    use Component\Middleware;
    use Component\Security;
    use Component\Rest;

    const BEFORE_REQUEST = 5;

    const BEFORE_RESPONSE = 10;

    /**
     * @var self
     */
    protected static $instance;

    /**
     * @var array
     */
    protected $modules = array();

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var callable
     */
    protected $failureCallback;

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public static function run($catch = true)
    {
        
        // if (false !== $response = self::emit('request')) {
        //     echo $response;
        //     return;
        // }

        $apricot = self::getInstance();

        if (property_exists($apricot, 'middlewares')) {
            static::runMiddlewares(self::BEFORE_REQUEST);
        }

        $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
        $matchedParameters = array();

        try {
            foreach($apricot->routes as $attributes) {

                $pattern = $attributes['pattern'];
                
                if (preg_match('#^'.$pattern.'/?$#U', $pathInfo, $matchedParameters)) {
                    
                    if (array_key_exists('force_https', $attributes)) {
                        header('Location: https://'. $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].$_SERVER['PATH_INFO']);
                    }

                    $parameters = array_slice($matchedParameters, 1);
                    
                    if (false === $response = call_user_func_array($attributes['callback'], $parameters)) {
                        continue;
                    } else {
                        if (property_exists($apricot, 'middlewares')) {
                            static::runMiddlewares(self::BEFORE_RESPONSE);
                        }

                        return $response;
                    }
                }
            }

            if (null != $apricot->notFoundCallback) {
                call_user_func_array($apricot->notFoundCallback, array($pathInfo));
            }

        } catch(\Exception $e) {
            if (false === $catch || null == $apricot->getFailureCallback()) {
                throw $e;
            }

            call_user_func_array($apricot->getFailureCallback(), array($e));
        }
    }

    public static function fail($callback)
    {
        $apricot = self::getInstance();

        $apricot->setFailureCallback($callback);
    }
    
    /**
     * Sets the root directory of Apricot.
     */
    public static function base($basePath)
    {
        $apricot = self::getInstance();

        $apricot->basePath = $basePath;
    }

    /**
     * Requires a file.
     */
    public static function load($file)
    {
        $apricot = self::getInstance();

        if (!file_exists($file)) {
            throw new \RuntimeException(sprintf("Cannot find file '%s' in base directory '%s'", $file, $apricot->basePath));
        }

        require $file;
    }

    public static function module($name, array $callbacks)
    {
        foreach($callbacks as $cb) {
            if (!is_callable($cb)) {
                throw new \LogicException("Array passed to Apricot::module() must be composed only of valid PHP callbacks.");
            }
        }

        $apricot = static::getInstance();
        
        $apricot->modules[$name] = $callbacks;
    }

    /**
     * Resets Apricot.
     */
    public static function reset()
    {
        self::$instance = new static;
    }


    public function getFailureCallback()
    {
        return $this->failureCallback;
    }

    public function setFailureCallback(callable $callback)
    {
        $this->failureCallback = $callback;

        return $this;
    }
}