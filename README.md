# 🧭 Silverstripe Discoverer > <img src="https://www.silverstripe.com/favicon.ico" style="height:40px; vertical-align:middle"/> Silverstripe Search

## Purpose

Perform search queries on your Silverstripe Search data through Silverstripe CMS controllers.

This module is used to integrate with the 🌈 Bifröst - the API for Silverstripe's Search service.

## Installation

```shell script
composer require silverstripe/silverstripe-discoverer-bifrost
```

## Specify environment variables

The following environment variables are required for this module to function:

```
BIFROST_ENDPOINT="https://abc.provided.domain"
BIFROST_ENGINE_PREFIX="engine-name-excluding-variant"
BIFROST_QUERY_API_KEY="abc.123.xyz"
```

## Usage

Please see the documentation provided in (Discoverer)[https://github.com/silverstripeltd/silverstripe-discoverer].

As mentioned above, this module serves as an "adaptor provider" for Discoverer. Besides the installation steps above,
you shouldn't really be interacting with this module in your code.
