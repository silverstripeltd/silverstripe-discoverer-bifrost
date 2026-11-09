<?php

namespace SilverStripe\DiscovererBifrost\Processors;

use InvalidArgumentException;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Discoverer\Query\Boost as QueryBoost;
use SilverStripe\Discoverer\Query\Filter\Criteria;
use SilverStripe\Discoverer\Query\Query;
use Silverstripe\Search\Client\Model\Field\Boost;
use Silverstripe\Search\Client\Model\Field\ResultField;
use Silverstripe\Search\Client\Model\Field\ResultFieldRaw;
use Silverstripe\Search\Client\Model\Field\ResultFieldSnippet;
use Silverstripe\Search\Client\Model\Field\SearchFieldWeight;
use Silverstripe\Search\Client\Model\Pagination;
use Silverstripe\Search\Client\Model\Search\Filters;
use Silverstripe\Search\Client\Model\Search\Tags;
use Silverstripe\Search\Client\Request\Search\SearchRequest;

class SearchRequestProcessor
{

    use Injectable;

    public function getRequest(Query $query): SearchRequest
    {
        $request = new SearchRequest($query->getQueryString());

        $facets = $this->getFacetsFromQuery($query);
        $filters = $this->getFiltersFromQuery($query);
        $pagination = $this->getPaginationFromQuery($query);
        $resultFields = $this->getResultFieldsFromQuery($query);
        $searchFields = $this->getSearchFieldsFromQuery($query);
        $sort = $this->getSortFromQuery($query);
        $tags = $this->getTagsFromQuery($query);

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
            $request->setSorts($sort);
        }

        if ($tags) {
            $request->setAnalytics($tags);
        }

        // When null, the engine's configured (dashboard) precision is used
        if ($query->getPrecision() !== null) {
            $request->setPrecision($query->getPrecision());
        }

        // Only send boosts when some are set, as an empty set would replace the engine's stored boosts
        foreach ($this->getBoostsFromQuery($query) as $fieldName => $fieldBoosts) {
            foreach ($fieldBoosts as $boost) {
                // Cast, as PHP turns a numeric field name such as "2024" into an int array key
                $request->addBoost((string) $fieldName, $boost);
            }
        }

        return $request;
    }

    /**
     * Translates the boosts on the query into Search Service boosts, grouped by field name.
     *
     * @param Query $query - the query to read the boosts from
     * @return array<int|string, array<int, Boost>> - the translated boosts, empty when the query has none
     * @throws InvalidArgumentException
     */
    private function getBoostsFromQuery(Query $query): array
    {
        $boosts = [];

        foreach ($query->getBoosts() as $fieldName => $fieldBoosts) {
            foreach ($fieldBoosts as $queryBoost) {
                $boosts[$fieldName][] = $this->buildBoost((string) $fieldName, $queryBoost);
            }
        }

        return $boosts;
    }

    /**
     * Builds a single Search Service boost from a Discoverer boost.
     *
     * @param string $fieldName - the field being boosted, used in error messages
     * @param QueryBoost $queryBoost - the Discoverer boost to translate
     * @return Boost - the boost in the form the Search Service expects
     * @throws InvalidArgumentException
     */
    private function buildBoost(string $fieldName, QueryBoost $queryBoost): Boost
    {
        $factor = $queryBoost->getFactor();

        // Throw rather than clamp, as clamping would silently change ranking and hide a configuration mistake
        if ($factor !== null && !($factor >= 0 && $factor <= 10)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Boost factor for field "%s" must be between 0 and 10, got %s',
                    $fieldName,
                    $this->formatFloat($factor)
                )
            );
        }

        $boost = new Boost($this->getApiBoostType($queryBoost->getType()));

        if ($queryBoost->getType() === QueryBoost::TYPE_VALUE) {
            $boost->setValue(array_map($this->formatScalar(...), $queryBoost->getValues()));
        } else {
            $boost->setCenter($this->formatScalar($queryBoost->getCenter()));
            $boost->setFunction($this->getApiBoostFunction($queryBoost->getFunction()));
        }

        // Leaving the factor unset keeps the client's default
        if ($factor !== null) {
            $boost->setFactor($factor);
        }

        return $boost;
    }

    /**
     * Maps a Discoverer boost type constant to the string the Search Service API expects.
     *
     * @param string $type - a `QueryBoost::TYPE_` constant
     * @return string - the API boost type
     * @throws InvalidArgumentException
     */
    private function getApiBoostType(string $type): string
    {
        return match ($type) {
            QueryBoost::TYPE_VALUE => 'value',
            QueryBoost::TYPE_PROXIMITY => 'proximity',
            default => throw new InvalidArgumentException(sprintf('Unknown boost type "%s"', $type)),
        };
    }

    /**
     * Maps a Discoverer boost function constant to the string the Search Service API expects.
     *
     * @param string|null $function - a `QueryBoost::FUNCTION_` constant
     * @return string - the API boost function
     * @throws InvalidArgumentException
     */
    private function getApiBoostFunction(?string $function): string
    {
        return match ($function) {
            QueryBoost::FUNCTION_GAUSSIAN => 'gaussian',
            QueryBoost::FUNCTION_EXPONENTIAL => 'exponential',
            QueryBoost::FUNCTION_LINEAR => 'linear',
            default => throw new InvalidArgumentException(sprintf('Unknown boost function "%s"', (string) $function)),
        };
    }

    /**
     * Converts a boost value or centre to the string the API expects, formatting floats as plain decimals.
     *
     * @param string|int|float $value - the value to convert
     * @return string - the value as a string
     * @throws InvalidArgumentException
     */
    private function formatScalar(string|int|float $value): string
    {
        return is_float($value) ? $this->formatFloat($value) : (string) $value;
    }

    /**
     * Formats a float as a plain decimal string: no exponent, shortest round-trip form, no trailing zeros.
     *
     * @param float $value - the float to format
     * @return string - the plain decimal representation
     * @throws InvalidArgumentException
     */
    private function formatFloat(float $value): string
    {
        if (!is_finite($value)) {
            throw new InvalidArgumentException('Boost numbers must be finite');
        }

        // `var_export()` gives the shortest round-trip digits and ignores the locale, but may use an exponent
        $repr = var_export($value, true);

        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?E([+-]\d+)$/i', $repr, $m) === 1) {
            $digits = $m[2] . $m[3];
            $pointAt = strlen($m[2]) + (int) $m[4];

            if ($pointAt <= 0) {
                $repr = $m[1] . '0.' . str_repeat('0', -$pointAt) . $digits;
            } elseif ($pointAt >= strlen($digits)) {
                $repr = $m[1] . $digits . str_repeat('0', $pointAt - strlen($digits));
            } else {
                $repr = $m[1] . substr($digits, 0, $pointAt) . '.' . substr($digits, $pointAt);
            }
        }

        if (str_contains($repr, '.')) {
            $repr = rtrim(rtrim($repr, '0'), '.');
        }

        return $repr === '-0' ? '0' : $repr;
    }

    private function getFacetsFromQuery(Query $query): ?array
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

    private function getPaginationFromQuery(Query $query): ?Pagination
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

        return new Pagination($pageNum, $limit);
    }

    /**
     * @return array<string, ResultField>|null
     */
    private function getResultFieldsFromQuery(Query $query): ?array
    {
        if (!$query->getResultFields()) {
            return null;
        }

        $resultFields = [];
        // Ensure we include the default fields, to allow us to map these documents back to Silverstripe DataObjects
        $resultFields['record_base_class'] = (new ResultField())->setRaw(new ResultFieldRaw());
        $resultFields['record_id'] = (new ResultField())->setRaw(new ResultFieldRaw());
        $resultFields['id'] = (new ResultField())->setRaw(new ResultFieldRaw());

        foreach ($query->getResultFields() as $field) {
            $fieldName = $field->getFieldName();
            $fieldSize = $field->getLength();

            if (!isset($resultFields[$fieldName])) {
                $resultFields[$fieldName] = new ResultField();
            }

            if ($field->isFormatted()) {
                $resultFields[$fieldName]->setSnippet(new ResultFieldSnippet($fieldSize ?: null));
            } else {
                $resultFields[$fieldName]->setRaw(new ResultFieldRaw($fieldSize ?: null));
            }
        }

        return $resultFields;
    }

    /**
     * @return array<string, SearchFieldWeight>|null
     */
    private function getSearchFieldsFromQuery(Query $query): ?array
    {
        if (!$query->getSearchFields()) {
            return null;
        }

        $searchFields = [];

        foreach ($query->getSearchFields() as $fieldName => $weight) {
            $searchFields[$fieldName] = new SearchFieldWeight($weight ?: null);
        }

        return $searchFields;
    }

    private function getSortFromQuery(Query $query): array
    {
        $processedSort = [];

        foreach ($query->getSort() as $fieldName => $direction) {
            $processedSort[] = [$fieldName => strtolower($direction)];
        }

        return $processedSort;
    }

    private function getTagsFromQuery(Query $query): ?Tags
    {
        if (!$query->getTags()) {
            return null;
        }

        return new Tags($query->getTags());
    }

}
