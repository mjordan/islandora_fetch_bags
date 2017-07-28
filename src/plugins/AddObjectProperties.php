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
     * Get object properties from the original REST "describe an object "request
     * response, save them to a JSON file in the temporary directory, then add
     * that file to the Bag.
     */
    public function execute($bag, $object_response_body)
    {
        $object_properties = json_encode($object_response_body);
        $pid = $object_response_body->pid;

        // Always save downloaded or written files to the temp directory so they are
        // cleaned up properly.
        $bag_name = get_bag_name($pid);
        $bag_temp_dir = get_bag_temp_dir($bag_name);
        $object_properties_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . 'object_properties.json';
        file_put_contents($object_properties_file_path, $object_properties);

        $bag->addFile($object_properties_file_path, 'object_properties.json');
        return $bag;
    }
}
