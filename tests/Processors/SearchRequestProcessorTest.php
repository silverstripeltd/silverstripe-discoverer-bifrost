<?php

namespace SilverStripe\DiscovererBifrost\Tests\Processors;

use ArrayObject;
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
use SilverStripe\DiscovererBifrost\Processors\SearchRequestProcessor;
use SilverStripe\DiscovererBifrost\Query\Facet\FacetAdaptor;
use SilverStripe\DiscovererBifrost\Query\Filter\CriteriaAdaptor;
use SilverStripe\DiscovererBifrost\Query\Filter\CriterionAdaptor;
use SilverStripe\DiscovererBifrost\Tests\Query\Facet\FacetAdaptorTest;
use SilverStripe\DiscovererBifrost\Tests\Query\Filter\CriteriaAdaptorTest;
use Silverstripe\Search\Client\Model\Filters;
use Silverstripe\Search\Client\Model\PaginationNoTotals;
use Silverstripe\Search\Client\Model\SearchRequestResultFieldRaw;
use Silverstripe\Search\Client\Model\SearchRequestResultFieldSnippet;
use stdClass;

class SearchRequestProcessorTest extends SapphireTest
{

    /**
     * This test only covers the basic functions performed in @see SearchRequestProcessor::getFacetsFromQuery()
     *
     * @see FacetAdaptorTest for test coverage rearding preparation of facets
     */
    public function testGetFacetsFromQuery(): void
    {
        $query = Query::create();

        /** @see SearchRequestProcessor::getFacetsFromQuery() */
        $reflectionMethod = new ReflectionMethod(SearchRequestProcessor::class, 'getFacetsFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no facets are set
        $this->assertNull($reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query));

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

        /** @var ArrayObject $facets */
        $facets = $reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query);

        $this->assertArrayHasKey('fieldName1', $facets);
        $this->assertArrayHasKey('fieldName2', $facets);
    }

    /**
     * This test only covers the basic functions performed in @see SearchRequestProcessor::getFiltersFromQuery()
     *
     * @see CriteriaAdaptorTest for test coverage regarding preparation of filters
     */
    public function testGetFiltersFromQuery(): void
    {
        $query = Query::create();

        /** @see SearchRequestProcessor::getFiltersFromQuery() */
        $reflectionMethod = new ReflectionMethod(SearchRequestProcessor::class, 'getFiltersFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no filters are set
        $this->assertNull($reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query));

        // Set filter and retest
        $query->filter('field1', 'value1', Criterion::EQUAL);
        $query->filter('field2', 'value2', Criterion::NOT_EQUAL);

        /** @var Filters $filters */
        $filters = $reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query);

        $this->assertCount(1, $filters->getAll());
        $this->assertCount(0, $filters->getAny());
        $this->assertCount(1, $filters->getNone());

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

        /** @var Filters $filters */
        $filters = $reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query);

        $this->assertCount(0, $filters->getAll());
        $this->assertCount(2, $filters->getAny());
        $this->assertCount(1, $filters->getNone());
    }

    public function testGetPaginationFromQuery(): void
    {
        $query = Query::create();

        /** @see SearchRequestProcessor::getPaginationFromQuery() */
        $reflectionMethod = new ReflectionMethod(SearchRequestProcessor::class, 'getPaginationFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no pagination is set
        $this->assertNull($reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query));

        // Set pagination and retest
        $query->setPagination(10, 0);

        /** @var PaginationNoTotals $pagination */
        $pagination = $reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query);

        $this->assertEquals(10, $pagination->getSize());
        $this->assertEquals(1, $pagination->getCurrent());

        // Set pagination and retest. Note: offset starts at 0, so an offset of 20 is page 3, not page 2
        $query->setPagination(10, 20);

        /** @var PaginationNoTotals $pagination */
        $pagination = $reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query);

        $this->assertEquals(10, $pagination->getSize());
        $this->assertEquals(3, $pagination->getCurrent());
    }

    public function testGetResultFieldsFromQuery(): void
    {
        $query = Query::create();

        /** @see SearchRequestProcessor::getResultFieldsFromQuery() */
        $reflectionMethod = new ReflectionMethod(SearchRequestProcessor::class, 'getResultFieldsFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no result fields are set
        $this->assertNull($reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query));

        // Set result fields and retest
        $query->addResultField('field1');
        $query->addResultField('field2', 0, true);
        $query->addResultField('field3', 10);
        // Raw and Snippet for field4
        $query->addResultField('field4', 100);
        $query->addResultField('field4', 20, true);

        /** @var ArrayObject $resultsFields */
        $resultsFields = $reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query);

        // Check that we have our two default result fields
        $this->assertArrayHasKey('record_base_class', $resultsFields);
        $this->assertArrayHasKey('record_id', $resultsFields);
        // Check that each of those default fields has a "raw" field
        $this->assertTrue($resultsFields['record_base_class']->isInitialized('raw'));
        $this->assertTrue($resultsFields['record_id']->isInitialized('raw'));
        $this->assertInstanceOf(SearchRequestResultFieldRaw::class, $resultsFields['record_base_class']->getRaw());
        $this->assertInstanceOf(SearchRequestResultFieldRaw::class, $resultsFields['record_id']->getRaw());
        // Check our custom result fields
        $this->assertArrayHasKey('field1', $resultsFields);
        $this->assertArrayHasKey('field2', $resultsFields);
        $this->assertArrayHasKey('field3', $resultsFields);
        $this->assertArrayHasKey('field4', $resultsFields);

        // No snippet defined
        $this->assertFalse($resultsFields['field1']->isInitialized('snippet'));
        // Raw should be defined
        $this->assertInstanceOf(SearchRequestResultFieldRaw::class, $resultsFields['field1']->getRaw());
        // But raw.size was not defined
        $this->assertFalse($resultsFields['field1']->getRaw()->isInitialized('size'));

        // No raw defined
        $this->assertFalse($resultsFields['field2']->isInitialized('raw'));
        // Snippet should be defined
        $this->assertInstanceOf(SearchRequestResultFieldSnippet::class, $resultsFields['field2']->getSnippet());
        // But snippet.size was not defined
        $this->assertFalse($resultsFields['field2']->getSnippet()->isInitialized('size'));

        // No snippet defined
        $this->assertFalse($resultsFields['field3']->isInitialized('snippet'));
        // Raw should be defined
        $this->assertInstanceOf(SearchRequestResultFieldRaw::class, $resultsFields['field3']->getRaw());
        // And raw.size was defined
        $this->assertEquals(10, $resultsFields['field3']->getRaw()->getSize());

        // Raw should be defined
        $this->assertInstanceOf(SearchRequestResultFieldRaw::class, $resultsFields['field4']->getRaw());
        // And raw.size was defined
        $this->assertEquals(100, $resultsFields['field4']->getRaw()->getSize());
        // Snippet should be defined
        $this->assertInstanceOf(SearchRequestResultFieldSnippet::class, $resultsFields['field4']->getSnippet());
        // And snippet.size was defined
        $this->assertEquals(20, $resultsFields['field4']->getSnippet()->getSize());
    }

    public function testGetSearchFieldsFromQuery(): void
    {
        $query = Query::create();

        /** @see SearchRequestProcessor::getSearchFieldsFromQuery() */
        $reflectionMethod = new ReflectionMethod(SearchRequestProcessor::class, 'getSearchFieldsFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no search fields are set
        $this->assertNull($reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query));

        // Set search fields and retest
        // No weight
        $query->addSearchField('field1');
        // Weight added
        $query->addSearchField('field2', 2);

        /** @var ArrayObject $searchFields */
        $searchFields = $reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query);

        $this->assertArrayHasKey('field1', $searchFields);
        $this->assertArrayHasKey('field2', $searchFields);

        $fieldOneExpected = new ArrayObject();
        $fieldTwoExpected = new ArrayObject();
        $fieldTwoExpected['weight'] = 2;

        $this->assertEquals($fieldOneExpected, $searchFields['field1']);
        $this->assertEquals($fieldTwoExpected, $searchFields['field2']);
    }

    public function testGetSortFromQuery(): void
    {
        $query = Query::create();

        /** @see SearchRequestProcessor::getSortFromQuery() */
        $reflectionMethod = new ReflectionMethod(SearchRequestProcessor::class, 'getSortFromQuery');
        $reflectionMethod->setAccessible(true);

        // First test that the value is null if no sorts are set
        $this->assertEquals([], $reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query));

        // Add sorts and retest
        $query->addSort('field1');
        $query->addSort('field2', Query::SORT_DESC);

        $sortOne = new ArrayObject();
        $sortOne['field1'] = 'asc';
        $sortTwo = new ArrayObject();
        $sortTwo['field2'] = 'desc';

        $expected = [
            $sortOne,
            $sortTwo,
        ];

        $this->assertEqualsCanonicalizing(
            $expected,
            $reflectionMethod->invoke(SearchRequestProcessor::singleton(), $query)
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

        // This test is really just checking that each method was invoked, as the individual methods are all tested
        // in depth above
        $request = SearchRequestProcessor::singleton()->getRequest($query);

        $this->assertEquals('search string', $request->getQuery());
        $this->assertInstanceOf(ArrayObject::class, $request->getFacets());
        $this->assertInstanceOf(Filters::class, $request->getFilters());
        $this->assertInstanceOf(ArrayObject::class, $request->getResultFields());
        $this->assertInstanceOf(ArrayObject::class, $request->getSearchFields());
        $this->assertInstanceOf(PaginationNoTotals::class, $request->getPage());
        $this->assertIsArray($request->getSort());
    }

    protected function setUp(): void
    {
        parent::setUp();

        Injector::inst()->registerService(new CriteriaAdaptor(), CriteriaAdaptorInterface::class);
        Injector::inst()->registerService(new CriterionAdaptor(), CriterionAdaptorInterface::class);
        Injector::inst()->registerService(new FacetAdaptor(), FacetAdaptorInterface::class);
    }

}
