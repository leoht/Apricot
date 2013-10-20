<?php

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