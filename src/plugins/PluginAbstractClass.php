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
     * @param object $object_response_body
     *   The body of the REST request to describe the Islandora object. This
     *   object has been converted from the JSON structure described at
     *   https://github.com/discoverygarden/islandora_rest#describe-an-existing-object
     *   by json_decode() into a PHP object.
     *
     * @return The modified Bag.
     */
    abstract public function execute($bag, $object_response_body);
}
