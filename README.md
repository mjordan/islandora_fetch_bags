# Islandora Fetch Bags

Tool to generate [Bags](https://en.wikipedia.org/wiki/BagIt) for objects using Islandora's REST interface.

The standard [Islandora BagIt](https://github.com/Islandora/islandora_bagit) module integrates Bag creation into the Islandora user interface, and also provides a Drush command to generate Bags. Islandora Fetch Bags, on the other hand, can be run from any location with HTTP access to the target Islandora instance, enabling flexible, distributed preservation workflows.

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
; Your site's base URL.
islandora_base_url = 'http://digital.lib.sfu.ca';

; Where you want your Bags to be saved. Must exist.
output_dir = '/tmp/bags'

; Directory for saving fetched files temporarily. Must exist.
; Important: Always use a specific directory for 'temp_dir'
; (and not '/tmp') since its contents are deleted after Bags are created.
temp_dir = '/tmp/tmpbags';

[objects]
; 'solr_query' is used to retrieve the PIDs of objects you want to create Bags for. The
; one in this sample queries for objects whose namespace is 'hiv', with a limit of 20.
; If you want to retrieve _all_ the PIDs possible, set the 'rows' parameter to
; a ridiculously high value like 1000000.
solr_query = "PID:hiv\:*?fl=PID&rows=20"

; 'pid_file' is the full path to a file listing one PID per row. // and # can
; be used to comment out lines. Note that 'pid_file' and 'solr_query' are
; mutually exclusive.
; pid_file = "/tmp/create_bags_for_these_pids.txt'

[bag]
; Type of compression to use on the Bag. Can be 'tgz', 'zip', or 'none'. Defaults to 'tgz'.
; compression = none

; Plugins are PHP classes that modify the Bag. See below for more information.
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

Your Bags will be saved in the directory specified in the location specified in your `output_dir` .ini value.

## Plugins

If you want to customize your Bags beyond what the options in the .ini file allow, you can use plugins. A plugin is nothing more than a simple PHP class file. The abstract class plus two examples are in the `src/plugins` directory. You enable a plugin by registering its class name in the `[bag]` section of your .ini file like this:

[bag]
plugins[] = MyPlugin

Once you have done that, and you have placed your plugin in the `src/plugins` directory, you will need to run composer's `dump-autoload` command so that your plugin will be detected.

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

Bug reports, use cases and suggestions are welcome. If you want to open a pull request, please open an issue first.

## To do

* Allow the creation of Bags for complex object such as books or newspaper issues.
* Document Solr queries, like retieving PIDs for objects updated after a `fgs_lastModifiedDate_dt` value, or all objects in a collection.
* Add proper error handling and logging.
* Add support for access to the REST interface controlled by [Islandora REST Authen](https://github.com/mjordan/islandora_rest_authen)

## License

The Unlicense
