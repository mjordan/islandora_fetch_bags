# Islandora Fetch Bags

Tool to generate Bags for objects in a remote Islandora instance using Islandora's REST interface.

[Islandora BagIt](https://github.com/Islandora/islandora_bagit) integrates Bag creation into the Islandora user interface, and also provides a Drush command to generate Bags. This tool, on the other hand, can be run from any location with HTTP access to the target Islandora instance. This ability allows for a variety of workflows involving distributed preservation and redunant storage services.

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

1. Queries Solr for the URLs for all objects in the specified namespace, and for each object:
1. Queries Islandora's REST interface to get a list of its datastreams
1. Fetches each datastream's content file
1. Generates a Bag containing all of the object's content files.

To use it, create an .ini file like this one:

```
; Sample .ini file for the Islandora Fetch Bags tool

; Where you want your Bags to be saved. Must exist.
output_dir = '/tmp/fetchbags';

; Your site's base URL.
islandora_base_url = 'http://digital.lib.sfu.ca';

; Solr query used to retrieve the PIDs of objects you want to create Bags for.
; This one queries for objects whose namespace is 'hiv', with a limit of 20.
; If you want to retrieve _all_ the PIDs possible, set the 'rows' parameter to
: a ridiculously high value like 1000000.
solr_query = "PID:hiv\:*?fl=PID&rows=20"
```

and then run the `fetch.php` command providing the name of your .ini file as its argument:

`php fetch.php config.ini`

Your Bags will be in the directory specified in the location specified in your `output_dir` .ini value.

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

Bug reports, use cases and suggestions are welcome. If you want to open a pull request, please open an issue first.

## To do

* Allow user to populate bag-info.txt tags.
* Provide option to read PIDs from an input file instead of from a Solr query.
* Document Solr queries, like retieving PIDs for objects updated after a `fgs_lastModifiedDate_dt` value, or all objects in a collection.
* Add plugins to allow the retrieval or creation of additional files to add to the Bag (such as PREMIS XML) or to fetch books or newspaper issues.
* Add proper error handling and logging.
* Add support for access to the REST interface restricted by [Islandora REST Authen](https://github.com/mjordan/islandora_rest_authen)

## License

The Unlicense
