<?php

/**
 * @file
 * Extends AbstractIfbPlugin class.
 */

namespace ifb\plugins;

/**
 * Defines BasicCustomBag class.
 */
class BasicCustomBag extends AbstractIfbPlugin 
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
     * Add an extra file, set an extra tag.
     */
    public function execute($bag)
    {
        $bag->addFile('/path/to/extra_file.txt', 'extra_subdir/extra_file.txt');
        $bag->setBagInfoData('My-Custom-Tag', 'Hey there!');
        return $bag;
    }
}
