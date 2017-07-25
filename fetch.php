<?php

require_once 'vendor/autoload.php';
require 'vendor/scholarslab/bagit/lib/bagit.php';

if (file_exists(trim($argv[1]))) {
    $config = parse_ini_file(trim($argv[1]), true);
} else {
    print "Cannot find configuration file " . trim($argv[1]) . "\n";
    exit;
}

$temp_dir = $config['general']['temp_dir'];
$output_dir = $config['general']['output_dir'];
$islandora_base_url = rtrim($config['general']['islandora_base_url'], '/');
$config['bag']['compression'] = isset($config['bag']['compression']) ?
    $config['bag']['compression'] : 'tgz';

$pids = array();
if (isset($config['objects']['solr_query'])) {
    $solr_query = $config['objects']['solr_query'];
    $pids = get_pids_from_solr($islandora_base_url, $solr_query);
}
if (isset($config['objects']['pid_file'])) {
    $pid_file = $config['objects']['pid_file'];
    $pids = get_pids_from_file($pid_file);
}

// Assemble each object URL and fetch datastream content.
if (count($pids) == 0) {
    print "No objects to generate Bags for, exiting.\n";
    exit;
}

foreach ($pids as $pid) {
    describe_object($pid, $islandora_base_url);
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
    fetch_datastreams($object_response_body, $islandora_base_url);
}

/**
 * Gets all of the datastream content files and save them in a directory.
 *
 * @param object $object_response_body
 *   The body of the REST request to describe the Islandora object.
 * @param string $islandora_base_url
 *   The site's base URL.
 */
function fetch_datastreams($object_response_body, $islandora_base_url) {
    global $temp_dir;
    $raw_pid = $object_response_body->pid;
    $datastreams = $object_response_body->datastreams;

    // Add some custom mimetype -> extension mappings.
    $builder = \Mimey\MimeMappingBuilder::create();
    $builder->add('text/xml', 'xml');

    $mimes = new \Mimey\MimeTypes($builder->getMapping());

    $pid = preg_replace('/:/', '_', $raw_pid);
    $bag_temp_dir = $temp_dir . DIRECTORY_SEPARATOR . $pid;
    @mkdir($bag_temp_dir);
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
        $file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $ds->dsid . '.' . $mimeTypes[0];
        file_put_contents($file_path, $ds_response->getBody());
        $data_files[] = $file_path;
    }
    generate_bag($object_response_body, $bag_temp_dir, $data_files);
}

/**
 * Generates a Bag from the object's datastream content files.
 *
 * @param object $object_response_body
 *   The body of the REST request to describe the Islandora object.
 * @param string $bag_temp_dir
 *   The object-level temporary directory where
 *   fetched datastream files have been saved.
 * @param array $files
 *   Array of file paths to the saved files.
 */
function generate_bag($object_response_body, $bag_temp_dir, $files) {
    global $output_dir;
    global $islandora_base_url;
    global $config;
    $pid = $object_response_body->pid;
    $object_url = $islandora_base_url . '/islandora/object/' . $pid;

    $bag_info = array();
    foreach ($config['bag-info']['tags'] as $bag_info_tag) {
        list($tag, $value) = explode(':', $bag_info_tag);
        $bag_info[$tag] = trim($value);
    }

    // @todo: PIDs can contain _, so we need to fix this.
    $filesystem_safe_pid = preg_replace('/:/', '_', $pid);

    $bag_dir = $output_dir . DIRECTORY_SEPARATOR . $filesystem_safe_pid;
    $bag = new BagIt($bag_dir, true, true, true, $bag_info);

    foreach ($files as $file) {
        $bag->addFile($file, basename($file));
    }

    if (isset($config['bag']['fetch'])) {
        foreach ($config['bag']['fetch'] as $fetch_url) {
            $bag->fetch->add($fetch_url, basename(parse_url($fetch_url, PHP_URL_PATH)));
        }
    }

    if (isset($config['bag']['plugins'])) {
        foreach ($config['bag']['plugins'] as $plugin) {
            $plugin_name = '\ifb\plugins\\' . $plugin;
            $bag_plugin = new $plugin_name($config);
            $bag = $bag_plugin->execute($bag, $object_response_body);
        }
    }

    $bag->update();

    $bag_output_dir = $output_dir . DIRECTORY_SEPARATOR . $pid;
    if ($config['bag']['compression'] == 'tgz' or $config['bag']['compression'] == 'zip') {
        $bag->package($bag_output_dir, $config['bag']['compression']);
        cleanup_temp_files($bag_output_dir);
    }
    print "Bag for $object_url saved in $output_dir\n";

    cleanup_temp_files($bag_temp_dir);
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
 * Returns a list of PIDs from Solr.
 *
 * @param string $islandora_base_url
 *   The target Islandora instance's base URL.
 * @param string $solr_query
 *   The query for retrieving PIDs.
 *
 * @return array
 *   A list of PIDs.
 */
function get_pids_from_solr($islandora_base_url, $solr_query) {
    $pids = array();
    $solr_url = $islandora_base_url . '/islandora/rest/v1/solr/' . $solr_query;

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
    print "Retrieved $count object PIDs from Solr, starting to fetch content files and generate Bags.\n";

    foreach ($docs as $doc) {
        $pids[] = $doc->PID;
    }
    return $pids;
}

/**
 * Returns a list of PIDs from a PID file.
 *
 * @param string $pid_file_path
 *   The absolute path to the PID file.
 *
 * @return array
 *   A list of PIDs.
 */
function get_pids_from_file($pid_file_path) {
    $pids = array();
    $lines = file($pid_file_path);
    foreach ($lines as $pid) {
        $pid = trim($pid);
        // Skip commented out rows.
        if (!preg_match('!(#|//)!', $pid)) {
            $pids[] = $pid;
        }
    }
    $count = count($pids);
    print "Retrieved $count object PIDs from $pid_file_path, starting to fetch content files and generate Bags.\n";
    return $pids;
}
