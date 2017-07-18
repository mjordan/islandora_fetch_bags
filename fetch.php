<?php

/**
 * Define some variables.
 */

// Where you want your Bags to be saved. Must exist.
$output_dir = '/tmp/fetchbags';

// Your site's base URL.
$site_base_url = 'http://digital.lib.sfu.ca';

// The namespace of the objects you want to generate Bags for.
$namespace ='hiv';

// Set to 1000000 or some ridiculously high number unless you want a subset.
$limit = '10';

/**
 * You will not need to change anything below this line.
 */


// @todo: Allow user to define the Solr query.
$solr_url = $site_base_url . '/islandora/rest/v1/solr/PID:' . $namespace . '\:*?fl=PID&rows=' . $limit;

require_once 'vendor/autoload.php';
require 'vendor/scholarslab/bagit/lib/bagit.php';

// Get the results of the Solr query.
$client = new GuzzleHttp\Client();
$solr_response = $client->request('GET', $solr_url, [
       'headers' => [
            'Accept' => 'application/json',
            // 'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
        ]
]);

$solr_response_body = $solr_response->getBody();
$solr_results = json_decode($solr_response_body);
$docs = $solr_results->response->docs;

$count = count($docs);
print "Retrieved $count object URLs, starting to fetch content files.\n";

// Assemble each object URL and fetch datastream content.
foreach ($docs as $doc) {
    describe_object($doc->PID, $site_base_url);
}

/**
 * Gets the list of datastreams from Islandora's REST interface.
 *
 * @param string $pid
 *   The object's PID.
 * @param string $site_base_url
 *   The site's base URL.
 */
function describe_object($pid, $site_base_url) {
	$object_url = $site_base_url . '/islandora/rest/v1/object/' . $pid;
	$client = new GuzzleHttp\Client();
    $object_response = $client->request('GET', $object_url, [
       'headers' => [
            'Accept' => 'application/json',
            // 'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
        ]
    ]);

    $object_response_body = json_decode($object_response->getBody());
    fetch_datastreams($pid, $object_response_body->datastreams, $site_base_url);
}

/**
 * Gets all of the datastream content files and save them in a directory.
 *
 * @param array $datastreams
 *   The list of datastreams retrieved from the "describe object" URL.
 * @param string $pid
 *   The object's PID.
 * @param string $site_base_url
 *   The site's base URL.
 */
function fetch_datastreams($raw_pid, $datastreams, $site_base_url) {
	global $output_dir;

    // Add some custom mimetype -> extension mappings.
    $builder = \Mimey\MimeMappingBuilder::create();
    $builder->add('text/xml', 'xml');

    $mimes = new \Mimey\MimeTypes($builder->getMapping());

    $pid = preg_replace('/:/', '_', $raw_pid);
	$bag_output_dir = $output_dir . DIRECTORY_SEPARATOR . $pid;
	@mkdir($bag_output_dir);
	$client = new GuzzleHttp\Client();
	$data_files = array();
    foreach ($datastreams as $ds) {
        $ds_url = $site_base_url . '/islandora/rest/v1/object/' . $raw_pid . '/datastream/' . $ds->dsid;
        $ds_response = $client->request('GET', $ds_url, [
            'headers' => [
                // 'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
            ]
        ]);

        $mimeTypes = $mimes->getAllExtensions($ds->mimeType);
        $file_path = $bag_output_dir . DIRECTORY_SEPARATOR . $ds->dsid . '.' . $mimeTypes[0];
        file_put_contents($file_path, $ds_response->getBody());
        $data_files[] = $file_path;
    }
    generate_bag($pid, $bag_output_dir, $data_files);
}

/**
 * Generates a Bag from object's fetched content files.
 *
 * @param string $pid
 *   The object's PID.
 */
function generate_bag($pid, $dir, $files) {
	global $output_dir;
	global $site_base_url;
	// @todo: PIDs can contain _, so we need to fix this.
	$pid_with_colon = preg_replace('/_/', ':', $pid);
	$object_url = $site_base_url . '/islandora/object/' . $pid_with_colon;
	$bag_info = array(
	  'Internal-Sender-Identifier' => $object_url,
	  'External-Description' => 'A simple Bag containing datastreams exported from ' . $pid_with_colon . '.',
	  'Bagging-Date	' => date("Y-m-d"),
	);

	$bag = new BagIt($output_dir . DIRECTORY_SEPARATOR . $pid, true, true, true, $bag_info);
	foreach ($files as $file) {
	    $bag->addFile($file, basename($file));
	}

    $bag->update();
    $bag->package($dir);
    print "Bag for $object_url saved in $output_dir\n";

    cleanup_temp_files($dir);
}

/**
 * Deletes a directory and all its children.
  *
  * @param string $dir
  *    The directory to delete.
  */
function cleanup_temp_files($dir) {
	$files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
    	$child = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($child) ? cleanup_temp_files($child) : unlink($child);
    }
    rmdir($dir);
}