<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use Exception;
use SilverStripe\Discoverer\Query\Query;
use SilverStripe\Discoverer\Service\Interfaces\SearchAdaptor as SearchAdaptorInterface;
use SilverStripe\Discoverer\Service\Results\Results;
use SilverStripe\Discoverer\Service\SearchService;
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
    public function process(Query $query, string $indexSuffix): Results
    {
        // Instantiate our Results class with empty data. This will still be returned if there is an Exception during
        // communication with Bifröst (so that the page doesn't seriously break)
        $results = Results::create($query);

        try {
            $request = SearchRequestProcessor::singleton()->getRequest($query);
            // searchPost() returns a stdClass() even though the typehint states otherwise
            /** @var stdClass $response */
            $response = $this->getClient()->searchPost(
                SearchService::singleton()->environmentizeIndex($indexSuffix),
                $request
            );

            SearchResultsProcessor::singleton()->getProcessedResults($results, $response);
            // If we got this far, then the request was a success
            $results->setSuccess(true);
        } catch (SearchPostUnprocessableEntityException $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing the
            // client response to a 500)
            $this->getLogger()->warning(
                $e->getMessage(),
                [
                    'exception' => $e,
                    'responseBody' => (string) $e->getResponse()->getBody(),
                ]
            );
            // Our request was not a success
            $results->setSuccess(false);
        } catch (Throwable $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing the
            // client response to a 500)
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);
            // Our request was not a success
            $results->setSuccess(false);
        } finally {
            return $results;
        }
    }

}
