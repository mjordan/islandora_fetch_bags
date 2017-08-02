<?php

/**
 * @file
 * fetch.php, a utility for generating Bags via the Islandora REST interface.
 *
 * See the README.md file for more information.
 */

require_once 'vendor/autoload.php';
require 'vendor/scholarslab/bagit/lib/bagit.php';
use Monolog\Logger;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

// Read the .ini file.
if (file_exists(trim($argv[1]))) {
    $config = parse_ini_file(trim($argv[1]), true);
} else {
    print "Cannot find configuration file " . trim($argv[1]) . "\n";
    exit;
}

/**
 * Set up configuration values and log file.
 */
$temp_dir = $config['general']['temp_dir'];
$output_dir = $config['general']['output_dir'];
$islandora_base_url = rtrim($config['general']['islandora_base_url'], '/');
$config['bag']['compression'] = isset($config['bag']['compression']) ?
    $config['bag']['compression'] : 'tgz';
$name_template = isset($config['general']['name_template']) ?
    $config['general']['name_template'] : '[PID]';
$pid_separator = isset($config['general']['pid_separator']) ?
    $config['general']['pid_separator'] : '_';

$path_to_log = isset($config['general']['path_to_log']) ?
    $config['general']['path_to_log'] : 'fetch_bags.log';
$log = new Monolog\Logger('Islandora Fetch Bags');
$log_stream_handler= new Monolog\Handler\StreamHandler($path_to_log, Logger::INFO);
$log->pushHandler($log_stream_handler);
$log->addInfo("fetch.php started generating Bags from " . $islandora_base_url . " starting at ". date("F j, Y, g:i a"));

/**
 * Get PIDs of objects to create Bags for, loop through
 * the list, call Islandora's REST interface to get info
 * on each object, and generate its Bag.
 */
$pids = array();
if (isset($config['objects']['solr_query'])) {
    $solr_query = $config['objects']['solr_query'];
    $pids = get_pids_from_solr($islandora_base_url, $solr_query);
}
if (isset($config['objects']['pid_file'])) {
    $pid_file = $config['objects']['pid_file'];
    $pids = get_pids_from_file($pid_file);
}

if (count($pids) == 0) {
    print "No objects to generate Bags for, exiting.\n";
    exit;
}

@mkdir($temp_dir);
@mkdir($output_dir);

foreach ($pids as $pid) {
    describe_object($pid, $islandora_base_url);
}

$log->addInfo("fetch.php finished exporting Bags at ". date("F j, Y, g:i a"));

/**
 * Functions.
 */

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

    try {
        $client = new GuzzleHttp\Client();
        $object_response = $client->request('GET', $object_url, [
           'headers' => [
               'Accept' => 'application/json',
                // 'X-Authorization-User' => $user . ':' . $token,
           ]
        ]);
     } catch (Exception $e) {
        if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException ) {
            $log->addError(Psr7\str($e->getRequest()));
            if ($e->hasResponse()) {
                $log->addError(Psr7\str($e->getResponse()));
                print Psr7\str($e->getResponse()) . "\n";
            }
            exit;
        }
    }

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
    global $log;
    global $name_template;
    global $pid_separator;
    $raw_pid = $object_response_body->pid;
    $datastreams = $object_response_body->datastreams;

    // Add some custom mimetype -> extension mappings.
    $builder = \Mimey\MimeMappingBuilder::create();
    $builder->add('text/xml', 'xml');
    $builder->add('image/jp2', 'jp2');

    $mimes = new \Mimey\MimeTypes($builder->getMapping());

    $bag_name = get_bag_name($raw_pid);
    $bag_temp_dir = get_bag_temp_dir($bag_name);
    mkdir($bag_temp_dir);
    $client = new GuzzleHttp\Client();
    $data_files = array();
    foreach ($datastreams as $ds) {
        $ds_url = $islandora_base_url . '/islandora/rest/v1/object/' . $raw_pid . '/datastream/' . $ds->dsid;
        $ds_response = $client->request('GET', $ds_url, [
            'headers' => [
                // 'X-Authorization-User' => $user . ':' . $token,
            ]
        ]);

        $mimeTypes = $mimes->getAllExtensions($ds->mimeType);
        $file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $ds->dsid . '.' . $mimeTypes[0];
        file_put_contents($file_path, $ds_response->getBody());
        $data_files[] = $file_path;
    }

    generate_bag($object_response_body, $data_files);
}

/**
 * Generates a Bag from the object's datastream content files.
 *
 * @param object $object_response_body
 *   The body of the REST request to describe the Islandora object.
 * @param array $files
 *   Array of file paths to the saved files.
 */
function generate_bag($object_response_body, $files) {
    global $log;
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

    $bag_name = get_bag_name($pid);

    $bag_dir = $output_dir . DIRECTORY_SEPARATOR . $bag_name;
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

    $bag_output_dir = $output_dir . DIRECTORY_SEPARATOR . $bag_name;
    if ($config['bag']['compression'] == 'tgz' or $config['bag']['compression'] == 'zip') {
        $bag->package($bag_output_dir, $config['bag']['compression']);
        cleanup_temp_files($bag_output_dir);
    }

    $message = "Bag for $object_url saved in $output_dir";
    $log->addInfo($message);
    print $message . "\n";

    $bag_temp_dir = get_bag_temp_dir($bag_name);
    cleanup_temp_files($bag_temp_dir);
}

/**
 * Gets the Bag's name, which is the name of the directory where
 * the Bag will be output to, or the name of the zip/tar file
 * if compression is enabled.
 *
 * @param string $pid
 *    The object's PID.
 *
 * @return string
 *    The Bag's name.
 */
function get_bag_name($pid) {
    global $name_template;
    global $pid_separator;
    $pid_for_bag_dir = preg_replace('/' . ':' .'/', $pid_separator, $pid);
    $bag_name = preg_replace('/\[PID\]/', $pid_for_bag_dir, $name_template);
    return $bag_name;
}

/**
 * Gets the temporary directory path where the Bag's files
 * are stored.
 *
 * @param string $bag_name
 *   The Bag's name, which is the name of the directory where
 *   the Bag will be output to, or the name of the zip/tar file
 *   if compression is enabled.
 *
 * @return string
 *    The full path to the temporary directory.
 */
function get_bag_temp_dir($bag_name) {
    global $temp_dir;
    return $temp_dir . DIRECTORY_SEPARATOR . $bag_name;
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
                // 'X-Authorization-User' => $user . ':' . $token,
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
