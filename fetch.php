<?php

/**
 * Define some variables.
 */

// Where you want your Bags to be saved. Must exist.
$output_dir = '/tmp/fetchbags';

// The URL of your collection's first browse page.
$browse_url = 'http://digital.lib.sfu.ca/hiv-collection/blair-henshaw-hiv-aids-stamp-collection';
$browse_url_parts = parse_url($browse_url);
$site_base_url = $browse_url_parts['scheme'] . '://' . $browse_url_parts['host'];

// This range corresponds to the number of pages in the collection's
// browse list, the second number being the "?page" value of the last page.
// @todo: automate getting this number.
$pages = range(0, 6);

/**
 * You will not need to change anything below this line.
 */

require_once 'vendor/autoload.php';
require 'vendor/scholarslab/bagit/lib/bagit.php';
use Goutte\Client;

// Scrape each of the parameterized browse pages defined in $pages.
$goutte_client = new Client();
$object_urls = array();
print "Scraping object URLs from collection browse pages starting at $browse_url.\n";
foreach ($pages as $page) {
    $crawler = $goutte_client->request('GET', $browse_url . '?page=' . $page);
    $hrefs = $crawler->filter('dd.islandora-object-caption > a')->extract(array('href'));
    $object_urls = array_merge($object_urls, $hrefs);
}

$count = count($object_urls);
print "Scrapted $count object URLs, starting to fetch content files.\n";

// Extract the PID from each object URL. This will be specific to the URLs on the site
// e.g., specific to path auto URL patterns, etc.
foreach ($object_urls as &$url) {
    $url = ltrim($url, '/');
    $pid = preg_replace('#/.*$#', '', $url);
    $pid = preg_replace('#\-#', ':', $pid);
    $describe_object_url = $site_base_url . '/islandora/rest/v1/object/' . $pid;
    describe_object($describe_object_url, $pid);
}

/**
 * Gets the list of datastreams from Islandora's REST interface.
 *
 * @param string $url
 *   The "describe object" REST URL.
 * @param string $pid
 *   The object's PID.
 */
function describe_object($url, $pid) {
	$client = new GuzzleHttp\Client();
    $object_response = $client->request('GET', $url, [
       'headers' => [
            'Accept' => 'application/json',
            // 'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
        ]
    ]);

    $object_response_body = $object_response->getBody();
    $object_response_body_array = json_decode($object_response_body, true);
    fetch_datastreams($object_response_body_array['datastreams'], $pid);

}

/**
 * Gets all of the datastream content files and save them in a directory.
 *
 * @param array $datastreams
 *   The list of datastreams retrieved from the "describe object" URL.
 * @param string $pid
 *   The object's PID.
 */
function fetch_datastreams($datastreams, $pid) {
	global $site_base_url;
	global $output_dir;
	$mimes = new \Mimey\MimeTypes;
	$bag_output_dir = $output_dir . DIRECTORY_SEPARATOR . preg_replace('/:/', '_', $pid);
	@mkdir($bag_output_dir);
	$client = new GuzzleHttp\Client();
    foreach ($datastreams as $ds) {
        $ds_url = $site_base_url . '/islandora/rest/v1/object/' . $pid . '/datastream/' . $ds['dsid'];
        $ds_response = $client->request('GET', $ds_url, [
            'headers' => [
                // 'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
            ]
        ]);

        $mimeTypes = $mimes->getAllExtensions($ds['mimeType']);
        $file_path = $bag_output_dir . DIRECTORY_SEPARATOR . $ds['dsid'] . '.' . $mimeTypes[0];
        file_put_contents($file_path, $ds_response->getBody());
    }
    print "Fetched content files for object $pid and saved them to temporary directory $bag_output_dir.\n";
}

/**
 * Generates a Bag from object's fetched content files.
 */
function generate_bag() {
    // @todo: Generate Bag.
}