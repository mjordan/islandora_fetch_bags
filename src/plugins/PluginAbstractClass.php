<?php

namespace ifb\plugins;

/**
 *
 */
abstract class AbstractIfbPlugin 
{
    /**
     *
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Router function for all plugins.
     *
     * @param $args
     *    Array of arguments. Varies by plugin type.
     *
     * @return Either 
     */
    abstract public function execute($args);
}
