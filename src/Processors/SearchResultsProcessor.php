<?php

namespace SilverStripe\DiscovererBifrost\Processors;

use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Discoverer\Analytics\AnalyticsData;
use SilverStripe\Discoverer\Analytics\AnalyticsMiddleware;
use SilverStripe\Discoverer\Service\FieldService;
use SilverStripe\Discoverer\Service\Results\Facet;
use SilverStripe\Discoverer\Service\Results\FacetData;
use SilverStripe\Discoverer\Service\Results\Field;
use SilverStripe\Discoverer\Service\Results\Record;
use SilverStripe\Discoverer\Service\Results\Results;
use stdClass;

class SearchResultsProcessor
{

    use Configurable;
    use Injectable;

    /**
     * Bifröst has a default limit of handling 100 pages. If you request a page beyond this limit
     * then an error occurs. We use this to limit the number of pages that are returned.
     */
    private static int $bifrost_page_limit = 100;

    /**
     * Bifröst has a default limit of 10000 results returned in a single query
     */
    private static int $bifrost_results_limit = 10000;

    /**
     * @throws Exception
     */
    public function getProcessedResults(Results $results, stdClass $response): void
    {
        // Check that we have all critical fields in our Bifröst response
        $this->validateResponse($response);
        // Start populating our Results object with data from our Bifröst response
        $this->processMetaData($results, $response);
        $this->processRecords($results, $response);
        $this->processFacets($results, $response);
    }

    /**
     * @throws Exception
     */
    private function validateResponse(stdClass $response): void
    {
        // If any errors are present, then let's throw and track what they were
        if (property_exists($response, 'errors')) {
            throw new Exception(sprintf('Bifröst response contained errors: %s', json_encode($response->errors)));
        }

        // The top level fields that we expect to receive from Bifröst for each search
        $meta = $response->meta ?? null;
        $results = $response->results ?? null;
        // Check if any required fields are missing
        $missingTopLevelFields = [];

        // Basic falsy check is fine here. An empty `meta` would still be an error
        if (!$meta) {
            $missingTopLevelFields[] = 'meta';
        }

        // Specifically checking for null, because an empty results array is a valid response
        if ($results === null) {
            $missingTopLevelFields[] = 'results';
        }

        // We were missing one or more required top level fields
        if ($missingTopLevelFields) {
            throw new Exception(sprintf(
                'Missing required top level fields: %s',
                implode(', ', $missingTopLevelFields)
            ));
        }

        // We expect every search to contain a value for `request_id`
        $requestId = $meta->request_id ?? null;

        if (!$requestId) {
            throw new Exception('Expected value for meta.request_id');
        }

        $engineName = $meta->engine->name ?? null;

        if (!$engineName) {
            throw new Exception('Expected value for meta.engine.name');
        }

        // We expect every search to contain pagination results, even if there is only 1 page of 0 results
        $pagination = $meta->page ?? null;

        // Ensure we have pagination results
        if (!$pagination) {
            throw new Exception('Missing structure for meta.page in Bifröst search response');
        }

        $missingPaginationFields = [];
        $expectedPagination = [
            'current',
            'size',
            'total_pages',
            'total_results',
        ];

        foreach ($expectedPagination as $expectedKey) {
            if (property_exists($pagination, $expectedKey)) {
                continue;
            }

            $missingPaginationFields[] = $expectedKey;
        }

        if ($missingPaginationFields) {
            throw new Exception(sprintf(
                'Missing required pagination fields: %s',
                implode(', ', $missingPaginationFields)
            ));
        }
    }

    private function processMetaData(Results $results, stdClass $response): void
    {
        $pageSize = $response->meta->page->size ?? 0;
        $pageLimit = $this->config()->get('bifrost_page_limit') ?? 0;
        $resultsLimit = $this->config()->get('bifrost_results_limit') ?? 0;
        $currentPage = $response->meta->page->current ?? 1;

        // Calculate the total paginated results that can be handled, taking into account the default Bifröst limits.
        // The page size also needs to be considered here so that we only handle the number of results rendered
        // on the first 100 pages (bifrost_page_limit * $pageSize).
        $totalResults = min([
            $response->meta->page->total_results ?? 0,
            $pageLimit * $pageSize,
            $resultsLimit,
        ]);

        $records = $results->getRecords();
        $records->setLimitItems(false);
        $records->setPageLength($pageSize);
        $records->setTotalItems($totalResults);
        $records->setCurrentPage($currentPage);
    }

    /**
     * @throws Exception
     */
    private function processRecords(Results $results, stdClass $response): void
    {
        if (!property_exists($response, 'results')) {
            throw new Exception('Bifröst Response contained no results array');
        }

        // Only used if analytics are enabled
        $requestId = $response->meta->request_id ?? null;
        $engineName = $response->meta->engine->name ?? null;
        // Check if any required fields are missing
        $missingRequiredFields = [];

        if (!$requestId) {
            $missingRequiredFields[] = 'meta.request_id';
        }

        if (!$engineName) {
            $missingRequiredFields[] = 'meta.engine.name';
        }

        // Shouldn't ever be null, since we passed the validation step which requires these fields, but it's best to
        // double-check
        if ($missingRequiredFields) {
            throw new Exception(sprintf('Expected values for: %s', implode(', ', $missingRequiredFields)));
        }

        $queryString = $results->getQuery()->getQueryString();

        foreach ($response->results as $result) {
            $record = Record::create();

            foreach ($result as $fieldName => $valueFields) {
                // Convert snake_case (Bifröst's field name format) to PascalCase (Silverstripe's field name format)
                $formattedFieldName = FieldService::singleton()->getConvertedFieldName($fieldName);

                $raw = $valueFields->raw ?? null;
                $snippet = $valueFields->snippet ?? null;

                $field = Field::create($raw, $snippet);

                /** @see Record::__set() */
                $record->{$formattedFieldName} = $field;
            }

            if (Environment::getEnv(AnalyticsMiddleware::ENV_ANALYTICS_ENABLED)) {
                // This field should always be there, as it's the default ID field in Bifröst. We won't break stuff
                // if it isn't there though - better that search works without analytics
                $documentId = $result->id->raw ?? null;

                $analyticsData = AnalyticsData::create();
                $analyticsData->setQueryString($queryString);
                $analyticsData->setIndexName($engineName);
                $analyticsData->setDocumentId($documentId);
                $analyticsData->setRequestId($requestId);

                $record->setAnalyticsData($analyticsData);
            }

            $results->addRecord($record);
        }
    }

    private function processFacets(Results $results, stdClass $response): void
    {
        $facets = $response->facets ?? null;

        if (!is_array($facets)) {
            return;
        }

        foreach ($facets as $fieldName => $facetResults) {
            foreach ($facetResults as $facetResult) {
                $facet = Facet::create();
                $facet->setFieldName($fieldName);
                $facet->setName($facetResult->name ?? null);
                $facet->setType($facetResult->type ?? null);

                foreach ($facetResult->data as $resultData) {
                    $facetData = FacetData::create();
                    $facetData->setValue($resultData->value ?? '');
                    $facetData->setFrom($resultData->from ?? '');
                    $facetData->setTo($resultData->to ?? '');
                    $facetData->setCount($resultData->count ?? '');

                    $facet->addData($facetData);
                }

                $results->addFacet($facet);
            }
        }
    }

}
