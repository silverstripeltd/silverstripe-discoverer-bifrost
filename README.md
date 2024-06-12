# 🧭 Silverstripe Discoverer > 🌈 Bifröst Search Provider

## Purpose

Perform search queries on your Silverstripe Search Service data through Silverstripe CMS controllers.

## Dependencies

We have three private modules that make up our Search Service integration (for performing actual searches):

* [Discoverer](https://github.com/silverstripeltd/silverstripe-discoverer)
    * Provides you with all of the searching interfaces that you will interact with in your project code.
    * The goal of this module is to be provider agnostic, so if you (for example) switch from Elasticsearch to Solr, or
      perhaps more likely, switch from Elastic App Search to Elasticsearch, then you (as a developer), shouldn't have to
      change much about how your applications interacts with the Service itself.
* [Discoverer > Elastic Enterprise](https://github.com/silverstripeltd/silverstripe-discoverer-elastic-enterprise)
    * Provides the adaptors so that the Service classes provided through the Discoverer module can communicate with
      Elastic Enterprise Search Service APIs.
* [Discoverer > Bifröst](https://github.com/silverstripeltd/silverstripe-discoverer-bifrost)
    * (This module). Updates the client factory so that the (above) Elastic Enterprise adaptors can communicate with
      Silverstripe's Search Service APIs.

## Installation

Add the following to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:silverstripeltd/silverstripe-discoverer.git"
        },
        {
            "type": "vcs",
            "url": "git@github.com:silverstripeltd/silverstripe-discoverer-elastic-enterprise.git"
        },
        {
            "type": "vcs",
            "url": "git@github.com:silverstripeltd/silverstripe-discoverer-bifrost.git"
        }
    ]
}
```

Then run the following:

```shell script
composer require silverstripe/silverstripe-discoverer-bifrost
```

## Specify environment variables

The following environment variables are required for this module to function:

* `BIFROST_ENDPOINT`
* `BIFROST_ENGINE_PREFIX`
* `BIFROST_QUERY_API_KEY`

## Usage

Please see the documentation provided in (Discoverer)[https://github.com/silverstripeltd/silverstripe-discoverer].

As mentioned above, this module serves as an "adaptor provider" for Discoverer. Besides the installation steps above,
you shouldn't really be interacting with this module in your code.
