<?php

/**
 * @file
 * Extends AbstractIfbPlugin class.
 */

namespace ifb\plugins;

/**
 * Defines AddObjectProperties class.
 */
class AddChildrenPids extends AbstractIfbPlugin 
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
     * Get object's children PIDs and sequence info, save them to a JSON
     * file in the temporary directory, then add that file to the Bag.
     */
    public function execute($bag, $object_response_body)
    {
        $pid_with_underscore = preg_replace('/:/', '_', $object_response_body->pid);
        $children_pid_list = $this->getChildren($object_response_body->pid, $object_response_body->models);
        $children_pid_list = json_encode($children_pid_list);

        if (count($children_pid_list)) {
            // Always save downloaded or written files to the temp directory so they are
            // cleaned up properly.
            $bag_temp_dir = $this->config['general']['temp_dir'] . DIRECTORY_SEPARATOR . $pid_with_underscore;
            $children_pid_list_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . 'children.json';
            file_put_contents($children_pid_list_file_path, $children_pid_list);

            $bag->addFile($children_pid_list_file_path, 'children.json');
        }
        return $bag;
    }

    /**
     * Issues a request to the Islandora REST interface for all of the children
     * of the object being Bagged.
     *
     * @param string $pid
     *    The PID of the object being Bagged.
     * @param array $cmodels
     *    The content models of the object being Bagged, as returned from the
     *    initial "describe an object" REST request.
     *
     * @return object
     *    The Solr result's documents.
     */
   private function getChildren($pid, $cmodels)
   {
        $pid_with_underscore = preg_replace('/:/', '_', $pid);
        $pid_with_backslash = preg_replace('/:/', '\\:', $pid);
        switch (true) {
            case in_array('islandora:collectionCModel', $cmodels):
                $solr_query = 'RELS_EXT_isMemberOfCollection_uri_s:*' . $pid_with_backslash .
                    '?fl=PID&rows=1000000';
                break;
            case in_array('islandora:compoundCModel', $cmodels):
                $solr_query = 'RELS_EXT_isConstituentOf_uri_s:*' . $pid_with_backslash .
                    '?fl=PID,RELS_EXT_isSequenceNumberOf' . $pid_with_underscore . '_literal_s&rows=1000000';
                break;
            case in_array('islandora:bookCModel', $cmodels):
                $solr_query = 'RELS_EXT_isMemberOf_uri_s:*' . $pid_with_backslash .
                    '?fl=PID,RELS_EXT_isSequenceNumber_literal_s&rows=1000000';
                break;
            case in_array('islandora:newspaperCModel', $cmodels):
                $solr_query = 'RELS_EXT_isMemberOf_uri_s:*' . $pid_with_backslash .
                    '?fl=PID,RELS_EXT_isSequenceNumber_literal_s&rows=1000000';
                break;
            case in_array('islandora:newspaperIssueCModel', $cmodels):
                $solr_query = 'RELS_EXT_isMemberOf_uri_s:*' . $pid_with_backslash .
                    '?fl=PID,RELS_EXT_isSequenceNumber_literal_s&rows=1000000';
                break;
            default:
                return array();
        }

        $solr_url = $this->config['general']['islandora_base_url'] . '/islandora/rest/v1/solr/' . $solr_query;

        $client = new \GuzzleHttp\Client();
        $solr_response = $client->request('GET', $solr_url, [
            'headers' => [
                'Accept' => 'application/json',
                // 'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
             ]
        ]);

        $solr_response_body = $solr_response->getBody();
        $solr_results = json_decode($solr_response_body);
        $docs = $solr_results->response->docs;

        return $docs;
    }
}
