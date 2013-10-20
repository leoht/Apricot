<?php

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

    /**
     * Defines a prefix for all routes registered in the given callback.
     *
     * @param string $prefix
     * @param callable $callback The callback where prefixed routes are defined.
     */
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
    public static function notFound($callback)
    {
        $apricot = self::getInstance();

        $apricot->notFoundCallback = $callback;
    }
}