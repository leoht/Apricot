<?php

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