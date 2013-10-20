<?php

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