# Islandora Fetch Bags

Script to generate Bags for objects in a remote Islandora instance using Islandora's REST interface.

Still a work in progress.

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

To use it, edit the `$output_dir`, `$site_base_url`, `$namespace`, and `$limit` variables at the top of the fetch.php script, and then run:

```
php fetch.php
```

Your Bags will be in the directory specified in `$output_dir`.

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

Bug reports, use cases and suggestions are welcome. If you want to open a pull request, please open an issue first.

## To do

* Allow user to determine the specific Solr query that retrieves PIDs (currently the query retrieves all objects with a given namespace).
* Allow user to populate bag-info.txt tags.

## License

The Unlicense
