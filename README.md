# Islandora Fetch Bags

Script to generate Bags for objects in a remote Islandora instance using Islandora's REST interface.

Still a work in progress. Doesn't create Bags yet but does everything else.

## Requirements

* On the target Islandora instance
  * [Islandora REST](https://github.com/discoverygarden/islandora_rest)
* On the system where the script is run
  * PHP 5.5.0 or higher.
  * [Composer](https://getcomposer.org)

## Installation

1. `git clone https://github.com/mjordan/islandora_fetch_bags.git`
1. `cd islandora_fetch_bags`
1. `php composer.phar install` (or equivalent on your system, e.g., `./composer install`)

## Overview and usage

This script:

1. Scrapes the URLs for all objects in a collection from the collection browse pages
1. For each object,
  1. Queries Islandora's REST interface to get a list of its datastreams
  1. Fetches each datastream's content file
  1. Generates a Bag containing all of the object's content files.

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

Bug reports, use cases and suggestions are welcome. If you want to open a pull request, please open an issue first.

## License

The Unlicense
