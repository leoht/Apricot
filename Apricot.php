<?php
/**
 * Apricot framework (one-file version).
 * Compiled on Thu 2013-10-31.
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

use Closure;

trait Middleware
{
    /**
     * @var array
     */
    protected $middlewares = array();

    /**
     * Adds a middleware callback.
     */
    public static function add(Closure $callback, $type = self::BEFORE_REQUEST)
    {
        $apricot = static::getInstance();

        $apricot->middlewares[] = array(
            'callback' => $callback,
            'type' => $type,
        );
    }

    /**
     * Runs all the middlewares of given type.
     */
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

namespace Apricot\Component\DependencyInjection;

class ScopeContainer implements \ArrayAccess
{
    protected $vars = array();

    public function set($key, $value)
    {
        $this->vars[$key] = $value;

        return $this;
    }

    public function get($key, $default = null)
    {
        if ($this->has($key)) {
            return $this->vars[$key];
        } else {
            return $default;
        }
    }

    public function offsetSet($key, $value)
    {
        return $this->set($key, $value);
    }

    public function offsetGet($key)
    {
        return $this->get($key);
    }

    public function offsetExists($key)
    {
        return $this->has($key);
    }

    public function offsetUnset($key)
    {
        unset($this->vars[$key]);
    }
}


namespace Apricot\Component;

use Closure;
use Apricot\Component\DependencyInjection\ScopeContainer;

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
     * @var array
     */
    protected $dependencies = array();

    /**
     * Sets a parameter or a service into the container.
     *
     * @param string $id
     * @param mixed $value
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

    public static function sets(array $values)
    {
        $apricot = self::getInstance();

        foreach($values as $name => $value) {
            self::set($name, $value);
        }
    }

    /**
     * Gets a parameter or a service from the container.
     *
     * @param string $id
     * @return mixed
     */
    public static function get($id)
    {
        $apricot = self::getInstance();

        if (array_key_exists($id, $apricot->parameters)) {
            return $apricot->parameters[$id];
        } elseif (array_key_exists($id, $apricot->services)) {
            return $apricot->services[$id];
        } elseif (array_key_exists($id, $apricot->dependencies)) {
            $dependency = $apricot->dependencies[$id];
            $r = new \ReflectionClass($dependency['class']);

            $arguments = array();

            foreach($dependency['arguments'] as $arg) {

                // the argument is a reference to another dependency
                if (0 === strpos($arg, '@')) {
                    $arg = substr($arg, 1);
                    if (! $arguments[] = self::get($arg)) {
                        throw new \InvalidArgumentException(sprintf("Unable to find service '%s' into the DI container.", $arg));
                    }
                } elseif (preg_match('#%(.+)%#', $arg, $matches)) {
                    $arguments[] = self::get($matches[1]);
                } else {
                    $arguments[] = $arg;
                }
            }

            $instance = $r->newInstanceArgs($arguments);

            return $instance;
        } else {
            return false;
        }
    }

    public static function provide($id, $class = null, array $arguments = array())
    {
        // Allow to provide a single array argument to registe multiple dependencies
        if (is_array($id)) {
            self::provideArray($id);
            return;
        }

        $apricot = self::getInstance();

        $apricot->dependencies[$id] = array(
            'class' => $class,
            'arguments' => $arguments,
        );
    }

    protected static function provideArray(array $dependencies)
    {
        $apricot = self::getInstance();

        foreach($dependencies as $id => $dependency) {
            $arguments = isset($dependency['arguments']) ? $dependency['arguments'] : array();

            $apricot->dependencies[$id] = array(
                'class' => $dependency['class'],
                'arguments' => $arguments,
            );
        }
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
    public static function scope($name, Closure $callback = null)
    {
        $apricot = static::getInstance();

        if (!array_key_exists($name, $apricot->scopes)) {
            $apricot->scopes[$name] = array(
                'container' => new ScopeContainer(),
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



namespace Apricot\Component;

use Closure;

trait Rest
{
    /**
     * Defines a REST resource.
     */
    public static function resource($resource, Closure $callback)
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

    public static function index(Closure $callback)
    {
        self::when('/', function () use ($callback)
        {
            call_user_func_array($callback, func_get_args());
        });
    }

    public static function show(Closure $callback)
    {
        self::when('/:id', self::withNumber('id', function () use ($callback)
        {
            call_user_func_array($callback, func_get_args());
        }));
    }

    public static function create(Closure $callback)
    {
        self::when('/', self::with(array('_method' => 'POST'), function () use ($callback)
        {
            call_user_func($callback);
        }));
    }

    public static function edit(Closure $callack)
    {
        self::when('/:id/edit', self::withNumber('id', function () use ($callback)
        {
            call_user_func_array($callback, func_get_args(oid));
        }));
    }

    public static function update(Closure $callback)
    {
        self::when('/:id', self::with(array('_method' => 'PUT', 'id' => '\d+'), function () use ($callback)
        {
            call_user_func_array($callback, func_get_args(oid));
        }));
    }

    public static function delete(Closure $callback)
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

use Closure;

trait Route
{
    /**
     * @var array
     */
    protected $routes = array();

    /**
     * @var Closure
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
    public static function when($pattern, Closure $callback, $name = null)
    {
        $apricot = self::getInstance();

        if (null !== $apricot->prefix) {
            $pattern = '/' === $pattern ? $apricot->prefix : $apricot->prefix . $pattern;
        }

        $originalPattern = $pattern;
        $pattern = preg_replace('#:([a-zA-Z0-9_]+)#is', '(.+)', $pattern);

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
     * Shortcut for Apricot::when('/')
     */
    public static function home(Closure $callback)
    {
        self::when('/', $callback);
    }

    /**
     * Add requirements to a route definition.
     * This is a middleware between Apricot::when() and the route callback.
     *
     * @param array $requirements An array of route requirements
     */
    public static function with(array $requirements, Closure $callback)
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

    /**
     * Defines a prefix for all routes registered in the given callback.
     *
     * @param string $prefix
     * @param Closure $callback The callback where prefixed routes are defined.
     */
    public static function prefix($prefix, Closure $callback)
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
    public static function withNumber($name, Closure $callback)
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

    /**
     * Generates an URL with a path containing parameters or the name of a route.
     *
     * @param string $path A path with parameters or a route name
     * @param array $parameters
     * @return string
     */
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

    /**
     * Registers a callback that will be triggered if no route matches.
     */
    public static function notFound(Closure $callback)
    {
        $apricot = self::getInstance();

        $apricot->notFoundCallback = $callback;
    }
}


namespace Apricot\Component;

use Closure;

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
    public static function secure($pattern, Closure $callback)
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
    public static function httpBasic($realm, Closure $callback)
    {
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            call_user_func_array($callback, array($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']));
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

namespace Apricot\Component\Cache;

trait FilesystemCache
{
    private static $cacheMap;

    public static function cacheWithFile($key, $value = null, $expire = 3600, $forceOverride = false)
    {
        $map = self::getCacheMap();

        if (array_key_exists($key, $map)) {

            $isStale = $map[$key]['expire'] < mktime();

            if (null !== $value && ($forceOverride || $isStale)) {
                $map[$key] = array('value' => $value, 'expire' => mktime()+$expire);
            } else {
                return $map[$key];
            }
        } else {
            $map[$key] = array('value' => $value, 'expire' => mktime()+$expire);
        }

        self::writeCacheMap($map);
    }

    public static function purgeCacheFile()
    {
        unlink(self::getCacheFile());
    }

    protected static function writeCacheMap(array $map)
    {
        $headContent = <<<EOF
<?php

/**
 * Cache file generated by Apricot
 */

EOF;
        file_put_contents(self::getCacheFile(), $headContent . "return " . var_export($map, true) .";\n");
    }

    protected static function getCacheMap()
    {
        if (null == self::$cacheMap) {
            self::$cacheMap = require self::getCacheFile();
        }
        
        return self::$cacheMap;
    }

    protected static function getCacheFile()
    {
        $cacheFile = self::cacheDir() . '/cache_map.php';

        if (!file_exists($cacheFile)) {
            if (!opendir(self::cacheDir())) {
                mkdir(self::cacheDir());
            }
            file_put_contents($cacheFile, "<?php\n\nreturn array();\n");
        }

        return $cacheFile;
    }
}


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



namespace Apricot\Component;

trait Database
{
    protected $pdo;

    public static function query($query, $parameters, callable $callback = null)
    {
        if (!is_array($parameters) && is_callable($parameters)) {
            $callback = $parameters;
        }

        $apricot = self::getInstance();

        $statement = $apricot->getPDO()->prepare($query);

        if (!$statement->execute()) {
            throw new \RuntimeException("Query failed.");
        }

        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        call_user_func_array($callback, $data);
    }

    protected function getPDO()
    {
        if (null === $this->pdo) {
            $driver = self::get('db._driver');
            $host = self::get('db._host');
            $dbname = self::get('db._name');
            $user = self::get('db._user');
            $pass = self::get('db._password');
            $this->pdo = new \PDO($driver.':host='.$host.';dbname='.$dbname, $user, $pass, array(
                \PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ));
        }

        return $this->pdo;
    }
}



namespace Apricot\Component;

use Closure;

trait Event
{
    /**
     * @var array
     */
    protected $listeners = array();

    /**
     * @var Boolean
     */
    protected $stopPropagation = false;
    
    /**
     * Registers an event listener.
     */
    public static function on($event, Closure $callback, $priority = 0)
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
    public static function emit($event, $arguments = array(), Closure $callback = null)
    {
        $apricot = self::getInstance();

        $response = $apricot->wakeUpListeners($event, $arguments, $callback);
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

    public static function stopPropagation()
    {
        $apricot = self::getInstance();

        $apricot->stopPropagation = true;
    }

    public function isPropagationStopped()
    {
        return (Boolean) $this->stopPropagation;
    }

    /**
     * Wakes up listeners of a specific event.
     */
    public function wakeUpListeners($event, array $arguments, Closure $callback = null)
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

            foreach($listeners as $listenerId => $listener) {

                if ($this->isPropagationStopped()) {
                    $this->stopPropagation = false;
                    return;
                }

                self::emit('event', array($event, $listener['callback']));

                $listenerResponse = call_user_func_array($listener['callback'], $arguments);
                
                if (null !== $callback) {
                    call_user_func_array($callback, array($listenerResponse, $listenerId));
                }
            }
        }

        return false;
    }
}



namespace Apricot;

use Closure;

class Apricot
{
    use Component\Http;
    use Component\Route;
    use Component\Event;
    use Component\DependencyInjection;
    use Component\Middleware;
    use Component\Security;
    use Component\Rest;
    use Component\Cache;

    const BEFORE_REQUEST = 5;

    const BEFORE_RESPONSE = 10;

    /**
     * @var self
     */
    protected static $instance;

    /**
     * @var string
     */
    protected $environment = 'prod';

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
        $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

        if ($response = self::emit('request', array($pathInfo))) {
            echo $response;
            return;
        }

        $apricot = self::getInstance();

        if (property_exists($apricot, 'middlewares')) {
            static::runMiddlewares(self::BEFORE_REQUEST);
        }

        $matchedParameters = array();

        try {
            foreach($apricot->routes as $attributes) {

                $pattern = $attributes['pattern'];
                
                if (preg_match('#^'.$pattern.'/?$#U', $pathInfo, $matchedParameters)) {
                    
                    if (array_key_exists('force_https', $attributes)) {
                        header('Location: https://'. $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].$_SERVER['PATH_INFO']);
                    }

                    $parameters = array_slice($matchedParameters, 1);

                    self::emit('match', array($pathInfo, $parameters));

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

            self::triggerNotFound();

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
            if (!$cb instanceof Closure) {
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

    public function setFailureCallback(Closure $callback)
    {
        $this->failureCallback = $callback;

        return $this;
    }

    public static function setEnvironment($environment)
    {
        $apricot = self::getInstance();

        $apricot->environment = $environment;
    }
}