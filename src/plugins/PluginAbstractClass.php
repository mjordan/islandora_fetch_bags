<?php

/**
 * @file
 * Defines the AbstractIfbPlugin class.
 */

namespace ifb\plugins;

/**
 * Abstract class for plugins.
 */
abstract class AbstractIfbPlugin 
{
    /**
     * Constructor.
     *
     * @param array $config
     *    The configuration data from the .ini file.
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Modifies the current Bag.
     *
     * All plugins must implement this method.
     *
     * @param object $bag
     *    The Bag object.
     *
     * @return The modified Bag.
     */
    abstract public function execute($bag);
}
