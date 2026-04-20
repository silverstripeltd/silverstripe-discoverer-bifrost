<?php

namespace SilverStripe\DiscovererBifrost\Tests\Query\Facet;

use ReflectionMethod;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Discoverer\Query\Facet\Facet;
use SilverStripe\Discoverer\Query\Facet\FacetCollection;
use SilverStripe\DiscovererBifrost\Query\Facet\FacetAdaptor;
use Silverstripe\Search\Client\Model\Search\FacetRange;
use Silverstripe\Search\Client\Model\Search\FacetRangeObject;
use Silverstripe\Search\Client\Model\Search\FacetValue;

class FacetAdaptorTest extends SapphireTest
{

    public function testPrepareRanges(): void
    {
        $adaptor = new FacetAdaptor();

        $facet = Facet::create();
        $facet->setFieldName('fieldName1');
        $facet->addRange(1, 2, 'test1');
        $facet->addRange(3);
        $facet->addRange(to: 4);
        $facet->addRange(name: 'test2');

        /** @see FacetAdaptor::prepareRanges() */
        $reflectionMethod = new ReflectionMethod($adaptor, 'prepareRanges');
        $reflectionMethod->setAccessible(true);

        /** @var FacetRangeObject[] $ranges */
        $ranges = $reflectionMethod->invoke($adaptor, $facet);

        $this->assertCount(4, $ranges);

        $this->assertInstanceOf(FacetRangeObject::class, $ranges[0]);
        $this->assertEquals(1, $ranges[0]->getFrom());
        $this->assertEquals(2, $ranges[0]->getTo());
        $this->assertEquals('test1', $ranges[0]->getName());

        $this->assertEquals(3, $ranges[1]->getFrom());
        $this->assertNull($ranges[1]->getTo());
        $this->assertNull($ranges[1]->getName());

        $this->assertNull($ranges[2]->getFrom());
        $this->assertEquals(4, $ranges[2]->getTo());
        $this->assertNull($ranges[2]->getName());

        $this->assertNull($ranges[3]->getFrom());
        $this->assertNull($ranges[3]->getTo());
        $this->assertEquals('test2', $ranges[3]->getName());
    }

    public function testPrepareRangesNoRange(): void
    {
        $adaptor = new FacetAdaptor();

        $facet = Facet::create();
        $facet->setFieldName('fieldName1');

        /** @see FacetAdaptor::prepareRanges() */
        $reflectionMethod = new ReflectionMethod($adaptor, 'prepareRanges');
        $reflectionMethod->setAccessible(true);

        $this->assertNull($reflectionMethod->invoke($adaptor, $facet));
    }

    public function testPrepareRangesEmptyRange(): void
    {
        $adaptor = new FacetAdaptor();

        $facet = Facet::create();
        $facet->setFieldName('fieldName1');
        // An empty ranges
        $facet->addRange();
        $facet->addRange();

        /** @see FacetAdaptor::prepareRanges() */
        $reflectionMethod = new ReflectionMethod($adaptor, 'prepareRanges');
        $reflectionMethod->setAccessible(true);

        $this->assertNull($reflectionMethod->invoke($adaptor, $facet));
    }

    public function testPrepareFacetValue(): void
    {
        $adaptor = new FacetAdaptor();

        $facet = Facet::create();
        $facet->setFieldName('fieldName1');
        $facet->setName('facetName1');
        $facet->setLimit(3);

        /** @see FacetAdaptor::prepareFacet() */
        $reflectionMethod = new ReflectionMethod($adaptor, 'prepareFacet');
        $reflectionMethod->setAccessible(true);

        /** @var FacetValue $result */
        $result = $reflectionMethod->invoke($adaptor, $facet);

        $this->assertInstanceOf(FacetValue::class, $result);
        $this->assertSame('value', $result->getType());
        $this->assertSame('facetName1', $result->getName());
        $this->assertSame(3, $result->getSize());
    }

    public function testPrepareFacetRange(): void
    {
        $adaptor = new FacetAdaptor();

        $facet = Facet::create();
        $facet->setFieldName('fieldName1');
        $facet->setName('facetName1');
        $facet->addRange(1, 2, 'test1');

        /** @see FacetAdaptor::prepareFacet() */
        $reflectionMethod = new ReflectionMethod($adaptor, 'prepareFacet');
        $reflectionMethod->setAccessible(true);

        /** @var FacetRange $result */
        $result = $reflectionMethod->invoke($adaptor, $facet);

        $this->assertInstanceOf(FacetRange::class, $result);
        $this->assertSame('range', $result->getType());
        $this->assertSame('facetName1', $result->getName());
        $this->assertCount(1, $result->getRanges());
        $this->assertInstanceOf(FacetRangeObject::class, $result->getRanges()[0]);
    }

    public function testPrepareFacets(): void
    {
        $adaptor = new FacetAdaptor();

        // Creating two facets that target the same field name
        $facetOne = Facet::create();
        $facetOne->setFieldName('fieldName1');
        $facetTwo = Facet::create();
        $facetTwo->setFieldName('fieldName1');
        // And one facet targeting a different field name
        $facetThree = Facet::create();
        $facetThree->setFieldName('fieldName2');

        $facetCollection = FacetCollection::create();
        $facetCollection->addFacet($facetOne);
        $facetCollection->addFacet($facetTwo);
        $facetCollection->addFacet($facetThree);

        /** @see FacetAdaptor::prepareFacets() */
        $reflectionMethod = new ReflectionMethod($adaptor, 'prepareFacets');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke($adaptor, $facetCollection);

        $this->assertIsArray($result);
        // Check that we have the expected properties
        $this->assertArrayHasKey('fieldName1', $result);
        $this->assertArrayHasKey('fieldName2', $result);
        // And that those properties have the expected number of facet records
        $this->assertCount(2, $result['fieldName1']);
        $this->assertCount(1, $result['fieldName2']);
    }

}
