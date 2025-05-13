<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use Exception;
use SilverStripe\Discoverer\Query\Query;
use SilverStripe\Discoverer\Service\Interfaces\SearchAdaptor as SearchAdaptorInterface;
use SilverStripe\Discoverer\Service\Results\Results;
use SilverStripe\DiscovererBifrost\Processors\SearchRequestProcessor;
use SilverStripe\DiscovererBifrost\Processors\SearchResultsProcessor;
use Silverstripe\Search\Client\Exception\SearchPostUnprocessableEntityException;
use stdClass;
use Throwable;

class SearchAdaptor extends BaseAdaptor implements SearchAdaptorInterface
{

    /**
     * @throws Exception
     */
    public function process(Query $query, string $indexName): Results
    {
        // Instantiate our Results class with empty data. This will still be returned if there is an Exception during
        // communication with Bifröst (so that the page doesn't seriously break)
        $results = Results::create($query);

        try {
            $engine = $this->environmentizeIndex($indexName);
            $request = SearchRequestProcessor::singleton()->getRequest($query);
            // searchPost() returns a stdClass() even though the typehint states otherwise
            /** @var stdClass $response */
            $response = $this->getClient()->searchPost($engine, $request);

            SearchResultsProcessor::singleton()->getProcessedResults($results, $response);
            // If we got this far, then the request was a success
            $results->setSuccess(true);
        } catch (SearchPostUnprocessableEntityException $e) {
            // Log the error without breaking the page
            $this->getLogger()->error(sprintf((string) $e->getResponse()->getBody(), $e->getMessage()), ['bifrost' => $e]);
            // Our request was not a success
            $results->setSuccess(false);
        } catch (Throwable $e) {
            // Log the error without breaking the page
            $this->getLogger()->error(sprintf('Bifrost error: %s', $e->getMessage()), ['bifrost' => $e]);
            // Our request was not a success
            $results->setSuccess(false);
        } finally {
            return $results;
        }
    }

}
