<?php

namespace SilverStripe\DiscovererBifrost\Processors;

use ArrayObject;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Discoverer\Query\Filter\Criteria;
use SilverStripe\Discoverer\Query\Query;
use Silverstripe\Search\Client\Model\Filters;
use Silverstripe\Search\Client\Model\PaginationNoTotals;
use Silverstripe\Search\Client\Model\SearchRequest;
use Silverstripe\Search\Client\Model\SearchRequestResultField;
use Silverstripe\Search\Client\Model\SearchRequestResultFieldRaw;
use Silverstripe\Search\Client\Model\SearchRequestResultFieldSnippet;

class SearchRequestProcessor
{

    use Injectable;

    public function getRequest(Query $query): SearchRequest
    {
        $request = new SearchRequest();
        $request->setQuery($query->getQueryString());

        $facets = $this->getFacetsFromQuery($query);
        $filters = $this->getFiltersFromQuery($query);
        $pagination = $this->getPaginationFromQuery($query);
        $resultFields = $this->getResultFieldsFromQuery($query);
        $searchFields = $this->getSearchFieldsFromQuery($query);
        $sort = $this->getSortFromQuery($query);

        if ($facets) {
            $request->setFacets($facets);
        }

        if ($filters) {
            $request->setFilters($filters);
        }

        if ($pagination) {
            $request->setPage($pagination);
        }

        if ($resultFields) {
            $request->setResultFields($resultFields);
        }

        if ($searchFields) {
            $request->setSearchFields($searchFields);
        }

        if ($sort) {
            $request->setSort($sort);
        }

        return $request;
    }

    private function getFacetsFromQuery(Query $query): ?ArrayObject
    {
        if (!$query->getFacetCollection()->getFacets()) {
            return null;
        }

        return $query->getFacetCollection()->getPreparedFacets();
    }

    private function getFiltersFromQuery(Query $query): ?Filters
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

        $filters = new Filters();
        $filters->setAll($preparedQuery['all']);
        $filters->setAny($preparedQuery['any']);
        $filters->setNone($preparedQuery['none']);

        return $filters;
    }

    private function getPaginationFromQuery(Query $query): ?PaginationNoTotals
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

        $pagination = new PaginationNoTotals();
        $pagination->setSize($limit);
        $pagination->setCurrent($pageNum);

        return $pagination;
    }

    private function getResultFieldsFromQuery(Query $query): ?ArrayObject
    {
        if (!$query->getResultFields()) {
            return null;
        }

        $resultFields = new ArrayObject();
        // Ensure we include the default fields, to allow us to map these documents back to Silverstripe DataObjects
        $resultFields['record_base_class'] = new SearchRequestResultField();
        $resultFields['record_base_class']->setRaw(new SearchRequestResultFieldRaw());
        $resultFields['record_id'] = new SearchRequestResultField();
        $resultFields['record_id']->setRaw(new SearchRequestResultFieldRaw());
        $resultFields['id'] = new SearchRequestResultField();
        $resultFields['id']->setRaw(new SearchRequestResultFieldRaw());

        foreach ($query->getResultFields() as $field) {
            $fieldName = $field->getFieldName();
            $fieldSize = $field->getLength();
            $fieldType = $field->isFormatted()
                ? new SearchRequestResultFieldSnippet()
                : new SearchRequestResultFieldRaw();

            if (!isset($resultFields[$fieldName])) {
                $resultFields[$fieldName] = new SearchRequestResultField();
            }

            if ($fieldSize) {
                $fieldType->setSize($fieldSize);
            }

            $field->isFormatted()
                ? $resultFields[$fieldName]->setSnippet($fieldType)
                : $resultFields[$fieldName]->setRaw($fieldType);
        }

        return $resultFields;
    }

    private function getSearchFieldsFromQuery(Query $query): ?ArrayObject
    {
        if (!$query->getSearchFields()) {
            return null;
        }

        $searchFields = new ArrayObject();

        foreach ($query->getSearchFields() as $fieldName => $weight) {
            $searchFields[$fieldName] = new ArrayObject();

            if (!$weight) {
                continue;
            }

            $searchFields[$fieldName]['weight'] = $weight;
        }

        return $searchFields;
    }

    private function getSortFromQuery(Query $query): array
    {
        $processedSort = [];

        foreach ($query->getSort() as $fieldName => $direction) {
            $sort = new ArrayObject();
            $sort[$fieldName] = strtolower($direction);

            $processedSort[] = $sort;
        }

        return $processedSort;
    }

}
