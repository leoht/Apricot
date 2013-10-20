<?php

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