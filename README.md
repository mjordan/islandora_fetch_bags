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

On the system where the script is run:

1. `git clone https://github.com/mjordan/islandora_fetch_bags.git`
1. `cd islandora_fetch_bags`
1. `php composer.phar install` (or equivalent on your system, e.g., `./composer install`)

## Overview and usage

This script:

1. Queries Solr for the PIDs for all objects to generate Bags for (or optionally reads PIDs from a file), and for each object:
1. Makes a request to Islandora's REST interface to get a list of the object's datastreams
1. Fetches each datastream's content file
1. Generates a Bag containing all of the object's content files (and optionally, other files generated by plugins).

To use it, create an .ini file like this one:

```
; Sample .ini file for the Islandora Fetch Bags tool.

[general]
; Your Islandora instance's base URL. Do not include the trailing /.
islandora_base_url = 'http://islandora.example.com';

; Where you want your Bags to be saved. Must exist.
output_dir = '/tmp/bags'

; Directory for saving fetched files temporarily. Must exist.
; Important: Always use a specific directory for 'temp_dir' (and not
; '/tmp' or 'c:\temp') since its contents are deleted after Bags are created.
temp_dir = '/tmp/tmpbags';

; This tool allows users to create a template for the names of their Bags using two
; configuration options, 'name_template' and 'pid_separator'. The generation of the Bag
; names is a two-step process. First, the ':' in the PID is replaced with another string,
; and second, the output of this replacement is combined with a name template.In the
: template, the special placeholder [PID] will be replaced with the modified PID. For
; example, if the pid_separator is '_', the PID 'islandora:100' will be converted to
; 'islandora_100'. Then, if the name_pattern is 'mybag-[PID], the Bag name will become
; 'mybag-islandora_100'. name_pattern defaults to nothing, and pid_separator defaults to
; '_', resulting in default Bag names like 'islandora_100'.

; name_template = mybag-[PID]
; pid_separator = %3A 

; Path to log file. Defaults to 'fetch_bags.log' in the same directory as 'fetch.php'.
; path_to_log = "/path/to/log.txt'

[objects]
; You can select which objects to create Bags for in two ways, 1) a Solr query or
; 2) a list of PIDs. Note that these two options are mutually exclusive.

; Use 'solr_query' if you want to get a list of PIDs from your Islandora instance's Solr
; index. The query in this example queries for objects whose namespace is 'foo', with a
; limit of 20. If you want to retrieve _all_ the PIDs possible, set the 'rows' parameter to
; a ridiculously high value like 1000000.
solr_query = "PID:foo\:*?fl=PID&rows=20"

; Use 'pid_file' if you have a specific list of PIDs. The 'pid_file' setting defines
; the full path to a file listing the PIDs, one PID per row. // and # can be used to
; comment out lines.
; pid_file = '/tmp/create_bags_for_these_pids.txt'

[bag]
; Type of compression to use on the Bag. Can be 'tgz', 'zip', or 'none'. Defaults to 'tgz'.
; compression = none

; See the README's "Plugins" section for more info.
plugins[] = AddCommonTags
; plugins[] = AddObjectProperties

; URLs added to the 'fetch[]' setting will be added to the Bag's fetch.txt file.
; fetch[] = "http://example.com/termsofuse.htm"
; fetch[] = "http://example.com/contact.htm"

[bag-info]
; Tags defined in this section are added to the bag-info.txt file in all Bags. Values
; must be literal strings. If you want to generate values based on properties of each
; object, you will need to write a plugin. Consult the AdvancedCustomBag.php plugin
; for an example of how to do that.
; tags[] = 'Contact-Email:bag-creators@example.com'
; tags[] = 'Source-Organization:Example Corporation'
```

Once you have your .ini file, run the `fetch.php` command, providing the name of your .ini file as its argument:

`php fetch.php myconfig.ini`

When the script finishes, your Bags will be saved in the directory specified in your `output_dir` .ini value.

## Solr queries

The `[objects] solr_query` setting can take any Solr query that is compatible with Islandora REST's `solr` endpoint (which is pretty much any Solr query). Queries should:

* specify the `fl=PID` parameter, since they only need to return a list of PIDs
* have a `rows` parameter with a very high value, such as 1000000, to ensure that your query returns all the PIDs that it finds (Solr's default is 10 rows)
* have any [special characters](http://lucene.apache.org/core/4_5_0/queryparser/org/apache/lucene/queryparser/classic/package-summary.html?is-external=true#Escaping_Special_Characters) escaped with a backslash
* should not be URL encoded, since the REST client does this automatically.

Some useful queries include:

* `PID:foo\:*?fl=PID&rows=1000000`
  * Retrieves all objects with a namespace of "foo".
* `RELS_EXT_isMemberOfCollection_uri_ms:*islandora\:sp_basic_image_collection?fl=PID&rows=1000000`
  * Retrieves all objects that are members of the islandora:sp_basic_image_collection.
* `fgs_lastModifiedDate_dt:[2017-05-21T00:00:00.000Z TO *]?fl=PID&rows=100`
  * Retrieves the first 100 objects modified since midnight UTC on 2017-05-12. Date must be in [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601) format.

## Plugins

### Using plugins

Plugins provide a way to customize or alter Bags. To enable a plugin, add its name to the `[bag] plugins[]` section of your .ini file, like this:

```
[bag]
plugins[] = MyPlugin
```

Three plugins that you might find useful are:

* AddCommonTags
  * Adds a `Internal-Sender-Identifier` tag to bagit-info.txt with the object's URL as its value
  * Adds a `Bagging-Date` tag to bagit-info.txt with the current date in yyyy-mm-dd format as its value
* AddObjectProperties
  * Adds a JSON file to the Bag that contains the Islandora object's properties (label, owner, list of datastreams, etc.)
* AddChildrenPids
  * Adds a JSON file to the Bag that contains the PIDs and sequence properties of the Islandora object's children (for objects with collection, compound, book, newspaper, and newspaper issue content models)

Plugins are executed in the order in which they are registered in the .ini file.

### Writing plugins

A plugin is contained within a single PHP class file. The abstract class `PluginAbstractClass.php` plus two example plugin class files (`BasicCustomBag.php` and `AdvancedCustomBag.php`) are available in the `src/plugins` directory. The plugins listed above provide additional examples.

Once you have written a plugin, do the following to use it:

1. placed your plugin in the `src/plugins` directory
1. run composer's `dump-autoload` command so that your plugin's class file will be detected
1. enable your plugin by registering its class name in the `[bag]` section of your .ini file, as illustrated above

Some useful points relevant to writing plugins:

* Plugins extend the abstract class `src/plugins/PluginAbstractClass.php`.
* Within you plugin's `->execute()` method, you can use [BagIt PHP](https://github.com/scholarslab/BagItPHP)'s methods for manipulating your Bags, but you should not use its `->update()` or `->package()` methods, since these are called by the main `fetch.php` script after all plugins are executed.
* Within your plugin's methods, you can access configuration values in `$this->config`, e.g., `$this->config['general']['temp_dir']`, including custom .ini values.
* Within plugins, you can write entries to the Monolog logger by using `$this->log`.
* If you download or generate a file within your plugin that you want included in your Bags, save it in `$this->config['general']['temp_dir']` so it is cleaned up automatically after the Bag is generated.

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

Bug reports, use cases and suggestions are welcome. So are plugins! If you want to open a pull request, please open an issue first.

## To do

* Allow the creation of Bags for complex object such as books or newspaper issues that contain all children Bags.
  * The AddChildrenPids plugin only adds to the Bag a file listing all children, it doesn't add the children's content.
* Add more error handling and logging.
* Add support for access to the REST interface controlled by [Islandora REST Authen](https://github.com/mjordan/islandora_rest_authen)

## License

The Unlicense
