<?php

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
