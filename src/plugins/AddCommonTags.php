<?php

/**
 * @file
 * Extends AbstractIfbPlugin class.
 */

namespace ifb\plugins;

/**
 * Defines AddCommonTags class.
 */
class AddCommonTags extends AbstractIfbPlugin 
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
     * Add a couple of bag-info.txt tags using programmatically
     * derived values.
     */
    public function execute($bag, $object_response_body)
    {
        $pid = $object_response_body->pid;
        $islandora_base_url = rtrim($this->config['general']['islandora_base_url'], '/');
        $object_url = $islandora_base_url . '/islandora/object/' . $pid;
        $bag->setBagInfoData('Internal-Sender-Identifier', $object_url);
        $bag->setBagInfoData('Bagging-Date', date("Y-m-d"));
        return $bag;
    }
}
