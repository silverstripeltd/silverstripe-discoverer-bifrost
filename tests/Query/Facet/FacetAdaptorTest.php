<?php

namespace SilverStripe\DiscovererBifrost\Tests\Query\Facet;

use ArrayObject;
use Elastic\EnterpriseSearch\AppSearch\Schema\SimpleObject;
use ReflectionMethod;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Discoverer\Query\Facet\Facet;
use SilverStripe\Discoverer\Query\Facet\FacetCollection;
use SilverStripe\DiscovererBifrost\Query\Facet\FacetAdaptor;

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

        $expected = [
            [
                'from' => 1,
                'to' => 2,
                'name' => 'test1',
            ],
            [
                'from' => 3,
            ],
            [
                'to' => 4,
            ],
            [
                'name' => 'test2',
            ],
        ];

        /** @see FacetAdaptor::prepareRanges() */
        $reflectionMethod = new ReflectionMethod($adaptor, 'prepareRanges');
        $reflectionMethod->setAccessible(true);

        $this->assertEqualsCanonicalizing($expected, $reflectionMethod->invoke($adaptor, $facet));
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

        $expected = [
            'type' => FacetAdaptor::TYPE_VALUE,
            'name' => 'facetName1',
            'size' => 3,
        ];
        $expected = new ArrayObject($expected);

        $this->assertEqualsCanonicalizing($expected, $reflectionMethod->invoke($adaptor, $facet));
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

        $expected = [
            'type' => FacetAdaptor::TYPE_RANGE,
            'name' => 'facetName1',
            'ranges' => [
                [
                    'from' => 1,
                    'to' => 2,
                    'name' => 'test1',
                ],
            ],
        ];
        $expected = new ArrayObject($expected);

        $this->assertEqualsCanonicalizing($expected, $reflectionMethod->invoke($adaptor, $facet));
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

        /** @var ArrayObject $simpleObject */
        $simpleObject = $reflectionMethod->invoke($adaptor, $facetCollection);

        // Check that we have the expected properties
        $this->assertArrayHasKey('fieldName1', $simpleObject);
        $this->assertArrayHasKey('fieldName2', $simpleObject);
        // And that those properties have the expected number of facet records
        $this->assertCount(2, $simpleObject['fieldName1']);
        $this->assertCount(1, $simpleObject['fieldName2']);
    }

}
