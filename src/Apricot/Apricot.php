<?php

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
    use Component\View;

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
     * Requires a PHP file or a whole directory.
     */
    public static function load($path, $require = true)
    {
        $apricot = self::getInstance();

        $path = $apricot->basePath . '/' . $path;

        // is it a directory ?
        if (false === strpos($path, '.php') && is_dir($path)) {
            return $apricot->loadDir($path, $require);
        }

        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf("Cannot find file '%s' in base directory '%s'", $path, $apricot->basePath));
        }

        if ($require) {
            require $path;
        } else {
            include $path;
        }

        return true;
    }

    protected function loadDir($path, $require = true)
    {
        $directoryIterator = new \RecursiveDirectoryIterator($path);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);
        $regex = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

        foreach ($regex as $matchFile) {
            $file = $matchFile[0];

            if (file_exists($file)) {
                if ($require) {
                    require $file;
                } else {
                    include $file;
                }
            }
        }

        return true;
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