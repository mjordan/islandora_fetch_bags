<?php

/**
 * @file
 * Extends AbstractIfbPlugin class.
 */

namespace ifb\plugins;

/**
 * Defines AddObjectProperties class.
 */
class AddObjectProperties extends AbstractIfbPlugin 
{
    /**
     * Constructor.
     *
     * @param array $config
     *    The configuration data from the .ini file.
     */
    public function __construct($config)
    {
        parent::__construct($config);
    }

    /**
     * Get object properties from the original REST request response, save
     * them to a JSON file, then add that file to the Bag.
     */
    public function execute($bag, $object_response_body)
    {
        $object_properties = json_encode($object_response_body);
        $object_properties_file_path = $bag->bagDirectory . DIRECTORY_SEPARATOR . 'object_properties.json';
        file_put_contents($object_properties_file_path, $object_properties);
        $bag->addFile($object_properties_file_path, 'object_properties.json');
        return $bag;
    }
}