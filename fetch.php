<?php

require_once 'vendor/autoload.php';
require 'vendor/scholarslab/bagit/lib/bagit.php';

if (file_exists(trim($argv[1]))) {
    $config = parse_ini_file(trim($argv[1]), true);
} else {
    print "Cannot find configuration file " . trim($argv[1]) . "\n";
    exit;
}

$output_dir = $config['general']['output_dir'];
$islandora_base_url = $config['general']['islandora_base_url'];
$solr_query = $config['objects']['solr_query'];
$solr_url = $islandora_base_url . '/islandora/rest/v1/solr/' . $solr_query;

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
print "Retrieved $count object PIDs, starting to fetch content files and generate Bags.\n";

// Assemble each object URL and fetch datastream content.
foreach ($docs as $doc) {
    describe_object($doc->PID, $islandora_base_url);
}

/**
 * Gets the list of datastreams from Islandora's REST interface.
 *
 * @param string $pid
 *   The object's PID.
 * @param string $islandora_base_url
 *   The site's base URL.
 */
function describe_object($pid, $islandora_base_url) {
  $object_url = $islandora_base_url . '/islandora/rest/v1/object/' . $pid;
  $client = new GuzzleHttp\Client();
    $object_response = $client->request('GET', $object_url, [
       'headers' => [
            'Accept' => 'application/json',
            // 'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
        ]
    ]);

    $object_response_body = json_decode($object_response->getBody());
    fetch_datastreams($pid, $object_response_body->datastreams, $islandora_base_url);
}

/**
 * Gets all of the datastream content files and save them in a directory.
 *
 * @param array $datastreams
 *   The list of datastreams retrieved from the "describe object" URL.
 * @param string $pid
 *   The object's PID.
 * @param string $islandora_base_url
 *   The site's base URL.
 */
function fetch_datastreams($raw_pid, $datastreams, $islandora_base_url) {
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
        $ds_url = $islandora_base_url . '/islandora/rest/v1/object/' . $raw_pid . '/datastream/' . $ds->dsid;
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
    global $islandora_base_url;
    global $config;
    // @todo: PIDs can contain _, so we need to fix this.
    $pid_with_colon = preg_replace('/_/', ':', $pid);
    $object_url = $islandora_base_url . '/islandora/object/' . $pid_with_colon;

    $bag_info = array(
      'Internal-Sender-Identifier' => $object_url,
      'Bagging-Date ' => date("Y-m-d"),
    );

    foreach ($config['bag-info']['tags'] as $bag_info_tag) {
        list($tag, $value) = explode(':', $bag_info_tag);
        $bag_info[$tag] = trim($value);
    }

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

/**
 * Returns a list of PIDs from a PID file.
 *
 * Not used yet.
 *
 * @param string $pid_file_path
 *   The absolute path to the PID file.
 *
 * @return array
 *   A list of PIDs.
 */
function read_pid_file($pid_file_path) {
    $pids = array();
    $lines = file($pid_file_path);
    foreach ($lines as $pid) {
        $pid = trim($pid);
        // Skip commented out rows.
        if (!preg_match('!(#|//)!', $pid)) {
            $pids[] = $pid;
        }
    }
    return $pids;
}