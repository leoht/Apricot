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

    public static function provide($id, $class, array $arguments = array())
    {
        $apricot = self::getInstance();

        $apricot->dependencies[$id] = array(
            'class' => $class,
            'arguments' => $arguments,
        );
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