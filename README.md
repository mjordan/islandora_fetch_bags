# Islandora Fetch Bags

Tool to generate [Bags](https://en.wikipedia.org/wiki/BagIt) for objects using Islandora's REST interface.

The standard [Islandora BagIt](https://github.com/Islandora/islandora_bagit) module integrates Bag creation into the Islandora user interface, and also provides a Drush command to generate Bags and save them on the Islandora server's file system. Islandora Fetch Bags, on the other hand, can be run from any location with HTTP access to the target Islandora instance, enabling flexible, distributed preservation workflows.

## Requirements

* On the target Islandora instance
  * [Islandora REST](https://github.com/discoverygarden/islandora_rest). The anonymous user should have Islandora REST's 'View objects' permission.
* On the system where the script is run
  * PHP 5.5.0 or higher.
  * [Composer](https://getcomposer.org)

## Installation

1. `git clone https://github.com/mjordan/islandora_fetch_bags.git`
1. `cd islandora_fetch_bags`
1. `php composer.phar install` (or equivalent on your system, e.g., `./composer install`)

## Overview and usage

This script:

1. Queries Solr for the PIDs for all objects to generate Bags for (or optionally reads PIDs from a file), and for each object:
1. Makes a request to Islandora's REST interface to get a list of the object's datastreams
1. Fetches each datastream's content file
1. Generates a Bag containing all of the object's content files.

To use it, create an .ini file like this one:

```
; Sample .ini file for the Islandora Fetch Bags tool

[general]
; Your Islandora instance's base URL. Do not include the trailing /.
islandora_base_url = 'http://digital.lib.sfu.ca';

; Where you want your Bags to be saved. Must exist.
output_dir = '/tmp/bags'

; Directory for saving fetched files temporarily. Must exist.
; Important: Always use a specific directory for 'temp_dir'
; (and not '/tmp') since its contents are deleted after Bags are created.
temp_dir = '/tmp/tmpbags';

[objects]
; You can select which objects to create Bags for in two ways, 1) a Solr query or
; 2) a list of PIDs. Note that these two options are mutually exclusive.

; Use 'solr_query' if you want to get a list of PIDs from your Islandora instance's Solr
; index. The query in this example queries for objects whose namespace is 'hiv', with a
; limit of 20. If you want to retrieve _all_ the PIDs possible, set the 'rows' parameter to
; a ridiculously high value like 1000000.
solr_query = "PID:hiv\:*?fl=PID&rows=20"

; Use 'pid_file' if you have a specific list of PIDs. The 'pid_file' setting defines
; the full path to a file listing the PIDs, one PID per row. // and # can be used to
; comment out lines.
; pid_file = "/tmp/create_bags_for_these_pids.txt'

[bag]
; Type of compression to use on the Bag. Can be 'tgz', 'zip', or 'none'. Defaults to 'tgz'.
; compression = none

; Plugins are PHP code that modify the Bag. See the README's "Plugins" section for more info.
; plugins[] = BasicCustomBag
; plugins[] = AdvancedCustomBag

; URLs added to the 'fetch[]' setting will be added to the Bag's fetch.txt file.
; fetch[] = "http://example.com/termsofuse.htm"
; fetch[] = "http://example.com/contact.htm"

[bag-info]
; Tags defined in this section are added to the bag-info.txt file in each Bag.
; Two tags are always added: Internal-Sender-Identifier and Bagging-Date. The first
; is assigned the object's URL and the second is assigned the current date, e.g.:
; Internal-Sender-Identifier: http://digital.lib.sfu.ca/islandora/object/hiv:26
; Bagging-Date : 2017-07-19
tags[] = 'Contact-Email:bag-creators@sfu.ca'
tags[] = 'Source-Organization:Simon Fraser University Library'
```

Once you have your .ini file, run the `fetch.php` command, providing the name of your .ini file as its argument:

`php fetch.php myconfig.ini`

When the script finishes, your Bags will be saved in the directory specified in your `output_dir` .ini value.

## Solr queries

The `[objects] solr_query` setting can take any Solr query that is compatible with Islandora REST's `solr` endpoint (which is pretty much any Solr query). Queries should:

* specify the `fl=PID` parameter, since they only need to return a list of PIDs
* have a `rows` paramter with a very high value, such as 1000000, to ensure that your query returns all the PIDs that it finds (Solr's default is 10 rows)
* have any [special characters](http://lucene.apache.org/core/4_5_0/queryparser/org/apache/lucene/queryparser/classic/package-summary.html?is-external=true#Escaping_Special_Characters) escaped with a backslash.
* be URL encoded.

Some useful queries include:

* `PID:foo\:*?fl=PID&rows=1000000`
  * Retrieves all objects with a namespace of "foo"
* `RELS_EXT_isMemberOfCollection_uri_ms:info\:fedora/islandora\:sp_basic_image_collection`
  * Retrieves all objects that are members of the islandora:sp_basic_image_collection
* `fgs_lastModifiedDate_dt:[2017-07-21T00:00:00.000Z TO *]`
  * Retrieves all objects modified since 2017-07-12 UTC.

## Plugins

If you want to customize your Bags beyond what the options in the .ini file allow, you can use plugins. A plugin is a simple PHP class file. The abstract class plus two example plugins are available in the `src/plugins` directory. A third plugin, AddObjectProperties, can be used to add a JSON file to the Bag that contains the Islandora object's properties (label, owner, list of datastreams, etc.).

Once you have written a plugin, do the following to use it:

1. placed your plugin in the `src/plugins` directory
1. run composer's `dump-autoload` command so that your plugin will be detected
1. enable your plugin by registering its class name in the `[bag]` section of your .ini file like this:

```
[bag]
plugins[] = MyPlugin
```

Within you plugin's `->execute()` method, you can use any of [BagIt PHP](https://github.com/scholarslab/BagItPHP)'s methods for manipulating your Bags, but you should not use its `->update()` or `->package()` methods, since these are called by the main `fetch.php` script after all plugins are executed.

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

Bug reports, use cases and suggestions are welcome. So are plugins! If you want to open a pull request, please open an issue first.

## To do

* Allow the creation of Bags for complex object such as books or newspaper issues.
* Add proper error handling and logging.
* Add support for access to the REST interface controlled by [Islandora REST Authen](https://github.com/mjordan/islandora_rest_authen)

## License

The Unlicense
