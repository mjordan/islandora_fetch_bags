<?php

/**
 * @file
 * Extends AbstractIfbPlugin class.
 */

namespace ifb\plugins;

/**
 * Defines AdvancedCustomBag class.
 */
class AdvancedCustomBag extends AbstractIfbPlugin 
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
     * Get the object's dc:description value and add it to bag-info.txt.
     * Also delete the TN and MEDIUM_SIZE datastram files before packaging
     * up the Bag.
     */
    public function execute($bag)
    {
        // Remove some files you don't want in your Bags.
        unlink($bag->bagDirectory . DIRECTORY_SEPARATOR . 'data/TN.jpeg');
        unlink($bag->bagDirectory . DIRECTORY_SEPARATOR . 'data/MEDIUM_SIZE.jpeg');

        // Populate a tag with some DC metadata.
        if (file_exists($bag->bagDirectory . DIRECTORY_SEPARATOR . 'data/DC.xml')) {
            // Get the valud of dc.description.
            $dom = new \DOMDocument;
            $dom->load($bag->bagDirectory . DIRECTORY_SEPARATOR . 'data/DC.xml');
            $elements = $dom->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'description');
            $description_values = '';
            foreach ($elements as $e) {
                $description_values .= $e->nodeValue;
            }
           $bag->setBagInfoData('External-Description', $description_values);
        }

        return $bag;
    }
}
