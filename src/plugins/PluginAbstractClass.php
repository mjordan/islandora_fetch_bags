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

        $this->path_to_log = isset($config['general']['path_to_log']) ?
            $config['general']['path_to_log'] : 'fetch_bags.log';

        $log = new \Monolog\Logger('Islandora Fetch Bags');
        $log_stream_handler= new \Monolog\Handler\StreamHandler($this->path_to_log, \Monolog\Logger::INFO);
        $log->pushHandler($log_stream_handler);
        $this->log = $log;
    }

    /**
     * Modifies the current Bag.
     *
     * All plugins must implement this method.
     *
     * @param object $bag
     *    The Bag object.
     * @param object $object_response_body
     *   The body of the REST "describe an object" request. This response
     *   object has been converted from the JSON structure described at
     *   https://github.com/discoverygarden/islandora_rest#describe-an-existing-object
     *   by json_decode() into a PHP object.
     *
     * @return The modified Bag.
     */
    abstract public function execute($bag, $object_response_body);
}
