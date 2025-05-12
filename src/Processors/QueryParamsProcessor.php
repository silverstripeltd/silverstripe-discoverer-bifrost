<?php

namespace SilverStripe\DiscovererBifrost\Processors;

use Elastic\EnterpriseSearch\AppSearch\Schema\PaginationResponseObject;
use Elastic\EnterpriseSearch\AppSearch\Schema\SearchFields;
use Elastic\EnterpriseSearch\AppSearch\Schema\SearchRequestParams;
use Elastic\EnterpriseSearch\AppSearch\Schema\SimpleObject;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Discoverer\Query\Filter\Criteria;
use SilverStripe\Discoverer\Query\Query;
use stdClass;

class QueryParamsProcessor
{

    use Injectable;

    public function getQueryParams(Query $query): SearchRequestParams
    {
        $params = Injector::inst()->create(SearchRequestParams::class, $query->getQueryString());

        $facets = $this->getFacetsFromQuery($query);
        $filters = $this->getFiltersFromQuery($query);
        $pagination = $this->getPaginationFromQuery($query);
        $resultFields = $this->getResultFieldsFromQuery($query);
        $searchFields = $this->getSearchFieldsFromQuery($query);
        $sort = $this->getSortFromQuery($query);
        $analytics = $this->getAnalyticsFromQuery($query);

        if ($facets) {
            $params->facets = $facets;
        }

        if ($filters) {
            $params->filters = $filters;
        }

        if ($pagination) {
            $params->page = $pagination;
        }

        if ($resultFields) {
            $params->result_fields = $resultFields;
        }

        if ($searchFields) {
            $params->search_fields = $searchFields;
        }

        if ($sort) {
            $params->sort = $sort;
        }

        if ($sort) {
            $params->sort = $sort;
        }

        if ($analytics) {
            $params->analytics = $analytics;
        }

        return $params;
    }

    private function getFacetsFromQuery(Query $query): ?SimpleObject
    {
        if (!$query->getFacetCollection()->getFacets()) {
            return null;
        }

        return $query->getFacetCollection()->getPreparedFacets();
    }

    private function getFiltersFromQuery(Query $query): ?SimpleObject
    {
        $filterCriteria = $query->getFilter();
        $clauses = $filterCriteria->getClauses();

        if (!$clauses) {
            return null;
        }

        // If our parent Criteria itself contains only one Criteria, then let's just drop this top level, as it provides
        // no value, and just adds confusion if you were to read the raw filter output
        if (count($clauses) === 1) {
            // Grab that single Clause
            $singleClause = array_shift($clauses);

            // Check if it's another Criteria, if it is, then we'll use that as our $filterCriteria
            if ($singleClause instanceof Criteria) {
                $filterCriteria = $singleClause;
            }
        }

        /** @var array $preparedQuery */
        $preparedQuery = $filterCriteria->getPreparedClause();

        $filters = new SimpleObject();
        $filters->all = $preparedQuery['all'];
        $filters->any = $preparedQuery['any'];
        $filters->none = $preparedQuery['none'];

        return $filters;
    }

    private function getPaginationFromQuery(Query $query): ?PaginationResponseObject
    {
        if (!$query->hasPagination()) {
            return null;
        }

        // The number of records that we will limit to
        $limit = $query->getPaginationLimit();
        // The offset number of records
        $offset = $query->getPaginationOffset();
        // Bifröst uses page numbers instead of offset, so we need to convert. Note: Offset starts at 0
        $pageNum = (int) ceil($offset / $limit) + 1;

        $pagination = Injector::inst()->create(PaginationResponseObject::class);
        $pagination->size = $limit;
        $pagination->current = $pageNum;

        return $pagination;
    }

    private function getResultFieldsFromQuery(Query $query): ?SimpleObject
    {
        if (!$query->getResultFields()) {
            return null;
        }

        $resultFields = new SimpleObject();
        // Ensure we include the default fields, to allow us to map these documents back to Silverstripe DataObjects
        $resultFields->record_base_class = new stdClass();
        $resultFields->record_base_class->raw = new stdClass();
        $resultFields->record_id = new stdClass();
        $resultFields->record_id->raw = new stdClass();
        // The ID field from Bifröst should always be added anyway, but having it here explicitly might help if their
        // API ever changes
        $resultFields->id = new stdClass();
        $resultFields->id->raw = new stdClass();

        foreach ($query->getResultFields() as $field) {
            $fieldName = $field->getFieldName();
            $fieldType = $field->isFormatted() ? 'snippet' : 'raw';
            $fieldSize = $field->getLength();

            if (!property_exists($resultFields, $fieldName)) {
                $resultFields->{$fieldName} = new stdClass();
            }

            $resultFields->{$fieldName}->{$fieldType} = new stdClass();

            if (!$fieldSize) {
                continue;
            }

            $resultFields->{$fieldName}->{$fieldType}->size = $fieldSize;
        }

        return $resultFields;
    }

    private function getSearchFieldsFromQuery(Query $query): ?SearchFields
    {
        if (!$query->getSearchFields()) {
            return null;
        }

        $searchFields = new SearchFields();

        foreach ($query->getSearchFields() as $fieldName => $weight) {
            $searchFields->{$fieldName} = new stdClass();

            if (!$weight) {
                continue;
            }

            $searchFields->{$fieldName}->weight = $weight;
        }

        return $searchFields;
    }

    private function getSortFromQuery(Query $query): array
    {
        $processedSort = [];

        foreach ($query->getSort() as $fieldName => $direction) {
            $processedSort[] = [
                $fieldName => strtolower($direction),
            ];
        }

        return $processedSort;
    }

    private function getAnalyticsFromQuery(Query $query): ?SimpleObject
    {
        if (!$query->getTags()) {
            return null;
        }

        $analyticsField = new SimpleObject();
        $analyticsField->tags = $query->getTags();

        return $analyticsField;
    }

}
