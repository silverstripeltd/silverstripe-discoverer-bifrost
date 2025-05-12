<?php

namespace SilverStripe\DiscovererBifrost\Tests\Processors;

use Elastic\EnterpriseSearch\AppSearch\Schema\PaginationResponseObject;
use Elastic\EnterpriseSearch\AppSearch\Schema\SearchFields;
use Elastic\EnterpriseSearch\AppSearch\Schema\SimpleObject;
use ReflectionMethod;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Discoverer\Query\Facet\Facet;
use SilverStripe\Discoverer\Query\Facet\FacetAdaptor as FacetAdaptorInterface;
use SilverStripe\Discoverer\Query\Filter\Criteria;
use SilverStripe\Discoverer\Query\Filter\CriteriaAdaptor as CriteriaAdaptorInterface;
use SilverStripe\Discoverer\Query\Filter\Criterion;
use SilverStripe\Discoverer\Query\Filter\CriterionAdaptor as CriterionAdaptorInterface;
use SilverStripe\Discoverer\Query\Query;
use SilverStripe\DiscovererBifrost\Processors\QueryParamsProcessor;
use SilverStripe\DiscovererBifrost\Query\Facet\FacetAdaptor;
use SilverStripe\DiscovererBifrost\Query\Filter\CriteriaAdaptor;
use SilverStripe\DiscovererBifrost\Query\Filter\CriterionAdaptor;
use SilverStripe\DiscovererBifrost\Tests\Query\Facet\FacetAdaptorTest;
use SilverStripe\DiscovererBifrost\Tests\Query\Filter\CriteriaAdaptorTest;
use stdClass;

class QueryParamsHelperTest extends SapphireTest
{

    /**
     * This test only covers the basic functions performed in @see QueryParamsProcessor::getFacetsFromQuery()
     *
     * @see FacetAdaptorTest for test coverage rearding preparation of facets
     */
    public function testGetFacetsFromQuery(): void
    {
        $query = Query::create();

        /** @see QueryParamsProcessor::getFacetsFromQuery() */
        $reflectionMethod = new ReflectionMethod(QueryParamsProcessor::class, 'getFacetsFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no facets are set
        $this->assertNull($reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query));

        $facetOne = Facet::create();
        $facetOne->setType(Facet::TYPE_VALUE);
        $facetOne->setFieldName('fieldName1');
        $facetOne->setName('facet1');

        $facetTwo = Facet::create();
        $facetTwo->setType(Facet::TYPE_VALUE);
        $facetTwo->setFieldName('fieldName2');
        $facetTwo->setName('facet2');

        // Add a bunch of Facets.
        $query->addFacet($facetOne);
        $query->addFacet($facetTwo);

        /** @var SimpleObject $facets */
        $facets = $reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query);

        $this->assertObjectHasProperty('fieldName1', $facets);
        $this->assertObjectHasProperty('fieldName2', $facets);
    }

    /**
     * This test only covers the basic functions performed in @see QueryParamsProcessor::getFiltersFromQuery()
     *
     * @see CriteriaAdaptorTest for test coverage regarding preparation of filters
     */
    public function testGetFiltersFromQuery(): void
    {
        $query = Query::create();

        /** @see QueryParamsProcessor::getFiltersFromQuery() */
        $reflectionMethod = new ReflectionMethod(QueryParamsProcessor::class, 'getFiltersFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no filters are set
        $this->assertNull($reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query));

        // Set filter and retest
        $query->filter('field1', 'value1', Criterion::EQUAL);
        $query->filter('field2', 'value2', Criterion::NOT_EQUAL);

        /** @var SimpleObject $filters */
        $filters = $reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query);

        $this->assertCount(1, $filters->all);
        $this->assertCount(0, $filters->any);
        $this->assertCount(1, $filters->none);

        // Now testing that we remove an un-used level of nesting when only one Criteria is present in our filters
        $query = Query::create();

        $criteria = Criteria::createAny();
        $criterionOne = Criterion::create('field1', 'value1', Criterion::EQUAL);
        $criterionTwo = Criterion::create('field2', 'value2', Criterion::EQUAL);
        $criterionThree = Criterion::create('field2', 'value2', Criterion::NOT_EQUAL);

        $criteria->addClause($criterionOne);
        $criteria->addClause($criterionTwo);
        $criteria->addClause($criterionThree);

        $query->filter($criteria);

        /** @var SimpleObject $filters */
        $filters = $reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query);

        $this->assertCount(0, $filters->all);
        $this->assertCount(2, $filters->any);
        $this->assertCount(1, $filters->none);
    }

    public function testGetPaginationFromQuery(): void
    {
        $query = Query::create();

        /** @see QueryParamsProcessor::getPaginationFromQuery() */
        $reflectionMethod = new ReflectionMethod(QueryParamsProcessor::class, 'getPaginationFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no pagination is set
        $this->assertNull($reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query));

        // Set pagination and retest
        $query->setPagination(10, 0);

        /** @var PaginationResponseObject $pagination */
        $pagination = $reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query);

        $this->assertEquals(10, $pagination->size);
        $this->assertEquals(1, $pagination->current);

        // Set pagination and retest. Note: offset starts at 0, so an offset of 20 is page 3, not page 2
        $query->setPagination(10, 20);

        /** @var PaginationResponseObject $pagination */
        $pagination = $reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query);

        $this->assertEquals(10, $pagination->size);
        $this->assertEquals(3, $pagination->current);
    }

    public function testGetResultFieldsFromQuery(): void
    {
        $query = Query::create();

        /** @see QueryParamsProcessor::getResultFieldsFromQuery() */
        $reflectionMethod = new ReflectionMethod(QueryParamsProcessor::class, 'getResultFieldsFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no result fields are set
        $this->assertNull($reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query));

        // Set result fields and retest
        $query->addResultField('field1');
        $query->addResultField('field2', 0, true);
        $query->addResultField('field3', 10);
        // Raw and Snippet for field4
        $query->addResultField('field4', 100);
        $query->addResultField('field4', 20, true);

        /** @var SimpleObject $resultsFields */
        $resultsFields = $reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query);

        // Check that we have our two default result fields
        $this->assertObjectHasProperty('record_base_class', $resultsFields);
        $this->assertObjectHasProperty('record_id', $resultsFields);
        // Check that each of those default fields has a "raw" field
        $this->assertObjectHasProperty('raw', $resultsFields->record_base_class);
        $this->assertObjectHasProperty('raw', $resultsFields->record_id);
        // Check our custom result fields
        $this->assertObjectHasProperty('field1', $resultsFields);
        $this->assertObjectHasProperty('field2', $resultsFields);
        $this->assertObjectHasProperty('field3', $resultsFields);
        $this->assertObjectHasProperty('field4', $resultsFields);

        $fieldOneExpected = new stdClass();
        $fieldOneExpected->raw = new stdClass();

        $this->assertEquals($fieldOneExpected, $resultsFields->field1);

        $fieldTwoExpected = new stdClass();
        $fieldTwoExpected->snippet = new stdClass();

        $this->assertEquals($fieldTwoExpected, $resultsFields->field2);

        $fieldThreeExpected = new stdClass();
        $fieldThreeExpected->raw = new stdClass();
        $fieldThreeExpected->raw->size = 10;

        $this->assertEquals($fieldThreeExpected, $resultsFields->field3);

        $fieldFourExpected = new stdClass();
        $fieldFourExpected->raw = new stdClass();
        $fieldFourExpected->raw->size = 100;
        $fieldFourExpected->snippet = new stdClass();
        $fieldFourExpected->snippet->size = 20;

        $this->assertEquals($fieldFourExpected, $resultsFields->field4);
    }

    public function testGetSearchFieldsFromQuery(): void
    {
        $query = Query::create();

        /** @see QueryParamsProcessor::getSearchFieldsFromQuery() */
        $reflectionMethod = new ReflectionMethod(QueryParamsProcessor::class, 'getSearchFieldsFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no search fields are set
        $this->assertNull($reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query));

        // Set search fields and retest
        // No weight
        $query->addSearchField('field1');
        // Weight added
        $query->addSearchField('field2', 2);

        /** @var SearchFields $searchFields */
        $searchFields = $reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query);

        $this->assertObjectHasProperty('field1', $searchFields);
        $this->assertObjectHasProperty('field2', $searchFields);

        $fieldOneExpected = new stdClass();
        $fieldTwoExpected = new stdClass();
        $fieldTwoExpected->weight = 2;

        $this->assertEquals($fieldOneExpected, $searchFields->field1);
        $this->assertEquals($fieldTwoExpected, $searchFields->field2);
    }

    public function testGetSortFromQuery(): void
    {
        $query = Query::create();

        /** @see QueryParamsProcessor::getSortFromQuery() */
        $reflectionMethod = new ReflectionMethod(QueryParamsProcessor::class, 'getSortFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no sorts are set
        $this->assertEquals([], $reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query));

        // Add sorts and retest
        $query->addSort('field1');
        $query->addSort('field2', Query::SORT_DESC);

        $expected = [
            ['field1' => 'asc'],
            ['field2' => 'desc'],
        ];

        $this->assertEqualsCanonicalizing(
            $expected,
            $reflectionMethod->invoke(QueryParamsProcessor::singleton(), $query)
        );
    }

    public function testGetQueryParams(): void
    {
        $query = Query::create('search string');

        $facet = Facet::create();
        $facet->setType(Facet::TYPE_VALUE);
        $facet->setFieldName('fieldName1');
        $facet->setName('facet1');

        $query->addFacet($facet);
        $query->addResultField('field1');
        $query->addSearchField('field2');
        $query->addSort('field3');
        $query->filter('field1', 'value1', Criterion::EQUAL);
        $query->setPagination(10, 20);

        $params = QueryParamsProcessor::singleton()->getQueryParams($query);

        $this->assertEquals('search string', $params->query);
        $this->assertInstanceOf(SimpleObject::class, $params->facets);
        $this->assertInstanceOf(SimpleObject::class, $params->filters);
        $this->assertInstanceOf(SimpleObject::class, $params->result_fields);
        $this->assertInstanceOf(SearchFields::class, $params->search_fields);
        $this->assertInstanceOf(PaginationResponseObject::class, $params->page);
        $this->assertIsArray($params->sort);
    }

    public function testGetAnalytics(): void
    {
        $query = Query::create('search string');
        $query->addTag('web');
        $query->addTag('mobile');

        $expected = [
            'web',
            'mobile',
        ];

        $params = QueryParamsProcessor::singleton()->getQueryParams($query);

        $this->assertInstanceOf(SimpleObject::class, $params->analytics);
        $this->assertEqualsCanonicalizing($expected, $params->analytics->tags);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Injector::inst()->registerService(new CriteriaAdaptor(), CriteriaAdaptorInterface::class);
        Injector::inst()->registerService(new CriterionAdaptor(), CriterionAdaptorInterface::class);
        Injector::inst()->registerService(new FacetAdaptor(), FacetAdaptorInterface::class);
    }

}
