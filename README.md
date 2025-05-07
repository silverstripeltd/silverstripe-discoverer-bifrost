# 🧭 Silverstripe Discoverer > <img src="https://www.silverstripe.com/favicon.ico" style="height:40px; vertical-align:middle"/> Silverstripe Search

<!-- TOC -->
* [🧭 Silverstripe Discoverer > <img src="https://www.silverstripe.com/favicon.ico" style="height:40px; vertical-align:middle"/> Silverstripe Search](#-silverstripe-discoverer--img-srchttpswwwsilverstripecomfaviconico-styleheight40px-vertical-alignmiddle-silverstripe-search)
  * [Purpose](#purpose)
  * [Installation](#installation)
  * [Engine vs Index](#engine-vs-index)
  * [Specify environment variables](#specify-environment-variables)
    * [Understanding your engine prefix and suffix:](#understanding-your-engine-prefix-and-suffix)
  * [Usage](#usage)
    * [Building a query](#building-a-query)
    * [Processing results](#processing-results)
    * [Understanding fields and results](#understanding-fields-and-results)
<!-- TOC -->

## Purpose

Perform search queries on your Silverstripe Search data through Silverstripe CMS controllers.

This module is used to integrate with the 🌈 Bifröst - the API for Silverstripe's Search service.

## Installation

```shell script
composer require silverstripe/silverstripe-discoverer-bifrost
```

## Engine vs Index

> [!IMPORTANT]
> **TL;DR:**\
> For all intents and purposes, "engine" and "index" are synonomous. If we refer to something as "engine", but the Discoverer module is asking for an "index", then you simply need to give it the data you have for your engine.

The Discoverer module is built to be service agnostic; meaning, you can use it with any search provider, as long as
there is an adaptor (like this module) for that service.

When Discoverer refers to an "index", it is talking about the data store used for housing your content. These data
stores are known by different names across different search providers. Algolia and Elasticsearch call them "indexes",
Typesense calls them "collections", App Search calls them "engines". Discoverer had to call them **something** in its
code, and it chose to call then "indexes"; Silverstripe Search, however, calls them "engines".

## Specify environment variables

To integrate with Silverstripe Search, define environment variables containing your endpoint, engine prefix, and
query API key.

```
BIFROST_ENDPOINT="https://abc.provided.domain"
BIFROST_ENGINE_PREFIX="<engine-prefix>" # See "Understanding your engine prefix and suffix" below
BIFROST_QUERY_API_KEY="abc.123.xyz"
```

### Understanding your engine prefix and suffix:

> [!IMPORTANT]
> **TL;DR:**
> - All Silverstripe Search engine names follow a 4 slug format like this: `search-<subscription>-<environment>-<suffix>`
> - Your `<engine-prefix>` is everything except `-<suffix>`; so, it's just `search-<subscription>-<environment>`

For example:

| Engine                    | Engine prefix        | Engine suffix |
|---------------------------|----------------------|---------------|
| search-acmecorp-prod-main | search-acmecorp-prod | main          |
| search-acmecorp-prod-inc  | search-acmecorp-prod | inc           |
| search-acmecorp-uat-main  | search-acmecorp-uat  | main          |
| search-acmecorp-uat-inc   | search-acmecorp-uat  | inc           |

**Why?**

Because you probably have more than one environment type that you're running search on (e.g. Production and UAT), and
(generally speaking) you should have different engines for each of those environments. So, you can't just hardcode
the entire engine name into your project, because that code doesn't change between environments.

Whenever you make a query, Discoverer will ask you for the "index" name; you will actually want to provide only the
`<suffix>`. We will then take `BIFROST_ENGINE_PREFIX` and your `<suffix>`, put them together, and that's what will be
queried. This allows you to set `BIFROST_ENGINE_PREFIX` differently for each environment, while having your `<suffix>`
hardcoded in your project.

More on this in [Usage](#usage)

## Usage

As mentioned above, this module serves as an "adaptor provider" for Discoverer. Besides the installation steps above,
you shouldn't really be interacting with this module in your code.

That said, below is a **very simple** examples on how to get your first query and results, but please see the
documentation provided in [Discoverer](https://github.com/silverstripeltd/silverstripe-discoverer) for more details on how to build queries and display results.

### Building a query

Instantiate the search service:

```php
$service = SearchService::create();
```

Create a new query:

```php
$query = Query::create('lorem ipsum');
```

When performing a search, Discoverer will ask you for the `Query` object (above), and the "index" to be queried. This
should just be the engine `<suffix>` (mentioned in [Understanding your engine prefix and suffix](#understanding-your-engine-prefix-and-suffix)):

```php
// $results will be a Result object, a class provided by the Discoverer module
$results = $service->search($query, '<suffix>');
// Debug results
Debug::dump($results);
```

### Processing results

For this quick example, we'll assume we had a couple of fields in our engine: `title`, `content` (more on fields in
[Understanding fields and results](#understanding-fields-and-results))

You have your `$results` (a `Results` object). You can now loop through its `Records`, which is just a paginated list
of `Record`.

In PHP:

```php
foreach ($results->getRecords() as $record) {
    Debug::dump($record->Title);
    Debug::dump($record->Content);
}
```

Or in your template:

```silverstripe
<% loop $Results.Records %>
    <h3>$Title</h3>
    <p>$Content</p>
<% end_loop %>
```

### Understanding fields and results

The Discoverer module attempts to standardise all data store (index/engine/collection) fields into a "Silverstripe'y"
format that we're all familiar with - PascalCase.

For example:

| Engine field      | Discoverer results field |
|-------------------|--------------------------|
| id                | Id                       |
| record_id         | RecordId                 |
| title             | Title                    |
| content           | Content                  |
| meta_image_url    | MetaImageUrl             |
| elemental_area    | ElementalArea            |
| taxonomy_term_ids | TaxonomyTermIds          |

**Note:** Abbreviations like `id`, or `url` are treated like any other word, so even though it's quite common practice
in Silverstripe to name it an `ID` (both capitalised), Discoverer will convert these to `Id` and `Url` respectively.

**Why?**

Because the Discoverer module has no way to programatically understand what abbreviation you might have in your code,
so it's better to just use a standard across anything and everything that looks like a word.
