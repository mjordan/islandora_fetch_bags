<?php

namespace ifb\plugins;

/**
 *
 */
class CustomBag extends AbstractIfbPlugin 
{
    /**
     *
     */
    public function __construct($config)
    {
        parent::__construct($config);
    }

    /**
     */
    public function execute($bag)
    {
        $bag->addFile('/tmp/files_plugin_sample.txt', 'blurg/files_plugin_sample.txt');
        $bag->setBagInfoData('markscustomtag', 'Hey there!');
        return $bag;

    }
}
