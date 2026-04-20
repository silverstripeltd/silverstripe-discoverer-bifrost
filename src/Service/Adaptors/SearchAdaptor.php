<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use SilverStripe\Discoverer\Query\Query;
use SilverStripe\Discoverer\Service\Interfaces\SearchAdaptor as SearchAdaptorInterface;
use SilverStripe\Discoverer\Service\Results\Results;
use SilverStripe\Discoverer\Service\SearchService;
use SilverStripe\DiscovererBifrost\Processors\SearchRequestProcessor;
use SilverStripe\DiscovererBifrost\Processors\SearchResultsProcessor;
use Throwable;

class SearchAdaptor extends BaseAdaptor implements SearchAdaptorInterface
{

    public function process(Query $query, string $indexSuffix): Results
    {
        try {
            $request = SearchRequestProcessor::singleton()->getRequest($query);
            $response = $this->getClient()->search(
                SearchService::singleton()->environmentizeIndex($indexSuffix),
                $request
            );

            $statusCode = $response->getStatusCode();
            $results = Results::create($statusCode, $query);

            // Valid response; process and return
            if ($statusCode >= 200 && $statusCode < 300) {
                SearchResultsProcessor::singleton()->getProcessedResults(
                    $results,
                    json_decode((string) $response->getBody())
                );

                return $results;
            }

            if ($statusCode >= 500) {
                // Log the error without breaking the page ("warning" is the highest level we can log without changing
                // the client response code)
                $this->getLogger()->warning(
                    sprintf('Search request failed with status %d', $statusCode),
                    [
                        'responseBody' => (string) $response->getBody(),
                    ]
                );
            }

            return $results;
        } catch (Throwable $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing
            // the client response code)
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);

            return Results::create(500, $query);
        }
    }

}
