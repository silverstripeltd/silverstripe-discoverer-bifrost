<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use Exception;
use SilverStripe\Discoverer\Query\Query;
use SilverStripe\Discoverer\Service\Interfaces\SearchAdaptor as SearchAdaptorInterface;
use SilverStripe\Discoverer\Service\Results\Results;
use SilverStripe\Discoverer\Service\SearchService;
use SilverStripe\DiscovererBifrost\Processors\SearchRequestProcessor;
use SilverStripe\DiscovererBifrost\Processors\SearchResultsProcessor;
use Silverstripe\Search\Client\Exception\SearchPostNotFoundException;
use Silverstripe\Search\Client\Exception\SearchPostUnprocessableEntityException;
use Silverstripe\Search\Client\Exception\UnexpectedStatusCodeException;
use stdClass;
use Throwable;

class SearchAdaptor extends BaseAdaptor implements SearchAdaptorInterface
{

    /**
     * @throws Exception
     */
    public function process(Query $query, string $indexSuffix): Results
    {
        try {
            $request = SearchRequestProcessor::singleton()->getRequest($query);
            // searchPost() returns a stdClass() even though the typehint states otherwise
            /** @var stdClass $response */
            $response = $this->getClient()->searchPost(
                SearchService::singleton()->environmentizeIndex($indexSuffix),
                $request
            );

            $results = Results::create(200, $query);
            SearchResultsProcessor::singleton()->getProcessedResults($results, $response);
        } catch (SearchPostNotFoundException|SearchPostUnprocessableEntityException $e) {
            $this->getLogger()->warning(
                $e->getMessage(),
                [
                    'exception' => $e,
                    'responseBody' => (string) $e->getResponse()->getBody(),
                ]
            );
            $results = Results::create($e->getResponse()->getStatusCode(), $query);
        } catch (UnexpectedStatusCodeException $e) {
            $this->getLogger()->warning(
                $e->getMessage(),
                [
                    'exception' => $e,
                    'responseBody' => $e->getMessage(),
                ]
            );
            $results = Results::create($e->getCode(), $query);
        } catch (Throwable $e) {
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);
            $results = Results::create(500, $query);
        } finally {
            return $results;
        }
    }

}
