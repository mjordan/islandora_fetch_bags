# Islandora Fetch Bags

Tool to generate Bags for objects in a remote Islandora instance using Islandora's REST interface.

[Islandora BagIt](https://github.com/Islandora/islandora_bagit) integrates Bag creation into the Islandora user interface, and also provides a Drush command to generate Bags. This tool, on the other hand, can be run from any location with HTTP access to the target Islandora instance.

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
site_base_url = 'http://digital.lib.sfu.ca';

; The namespace of the objects you want to generate Bags for.
namespace ='hiv';

; Set to 1000000 or some ridiculously high number unless you want a subset.
limit = '10';
```

and then run the `fetch.php` command providing the name of your .ini file as its argument:

`php fetch.php config.ini`

Your Bags will be in the directory specified in the location specified in your `output_dir` .ini value.

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

Bug reports, use cases and suggestions are welcome. If you want to open a pull request, please open an issue first.

## To do

* Allow user to determine the specific Solr query that retrieves PIDs (currently the query retrieves all objects with a given namespace).
* Allow user to populate bag-info.txt tags.
* Provide option to read PIDs from an input file instead of from a Solr query.
* Add plugins to allow the retrieval or creation of additional files to add to the Bag (such as PREMIS XML) or to fetch books or newspaper issues.
* Document Solr queries, like retieving PIDs for objects updated after a `fgs_lastModifiedDate_dt` value, or all objects in a collection.

## License

The Unlicense
