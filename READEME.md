# silverstripeltd/discoverer-bifrost

## Purpose

Perform search queries on your Silverstripe Search Service data through Silverstripe CMS controllers.

## Dependencies

We have two private modules that make up our Search Service integration (for performing actual searches):

* [Discoverer](https://github.com/silverstripeltd/discoverer)
    * This modules provides you with all of the searching interfaces that you will interact with in your project code.
    * The goal of this module is to be provider agnostic, so if we (for example) switch from Elasticsearch to Solr, or
      perhaps more likely, switch from Elastic App Search to Elasticsearch, then you (as a developer), shouldn't have to
      change much about how your applications interacts with the Service itself.
* [Discoverer > Bifröst](https://github.com/silverstripeltd/discoverer-bifrost)
    * (This module). Provides the adaptors so that the Service classes provided through the Discoverer module can
      communicate with Silverstripe's Search Service APIs.

## Installation

Add the following to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:silverstripeltd/discoverer.git"
        },
        {
            "type": "vcs",
            "url": "git@github.com:silverstripeltd/discoverer-bifrost.git"
        }
    ]
}
```

Then run the following:

```shell script
composer require silverstripeltd/discoverer-bifrost
```

## Specify environment variables

The following environment variables are required for this module to function:

* `BIFROST_ENDPOINT`
* `BIFROST_ENGINE_PREFIX`
* `BIFROST_PUBLIC_API_KEY`

## Usage

Please see the documentation provided in (Discoverer)[https://github.com/silverstripeltd/discoverer].

As mentioned above, this module serves as an "adaptor provider" for Discoverer. Besides the installation steps above,
you shouldn't really be interacting with this module in your code.
