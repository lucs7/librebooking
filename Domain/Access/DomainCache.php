<?php

class DomainCache
{
    private $_cache;

    public function __construct()
    {
        $this->_cache = [];
    }

    /**
     * @return bool
     */
    public function Exists($key)
    {
        return array_key_exists($key, $this->_cache);
    }

    public function Get($key)
    {
        return $this->_cache[$key];
    }

    public function Add($key, $object)
    {
        $this->_cache[$key] = $object;
    }

    public function Remove($key)
    {
        unset($this->_cache[$key]);
    }
}
