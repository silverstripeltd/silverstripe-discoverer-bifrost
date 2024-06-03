<?php

namespace SilverStripe\DiscovererBifrost\Tests\Query\Filter;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Discoverer\Query\Filter\Criteria;
use SilverStripe\Discoverer\Query\Filter\CriteriaAdaptor as CriteriaAdaptorInterface;
use SilverStripe\Discoverer\Query\Filter\Criterion;
use SilverStripe\Discoverer\Query\Filter\CriterionAdaptor as CriterionAdaptorInterface;
use SilverStripe\DiscovererBifrost\Query\Filter\CriteriaAdaptor;
use SilverStripe\DiscovererBifrost\Query\Filter\CriterionAdaptor;

class CriteriaAdaptorTest extends SapphireTest
{

    public function testBasicAllConjunction(): void
    {
        $criteria = Criteria::createAll();
        $criterionOne = Criterion::create('field1', 'value1', Criterion::EQUAL);
        $criterionTwo = Criterion::create('field2', 'value2', Criterion::EQUAL);

        $criteria->addClause($criterionOne);
        $criteria->addClause($criterionTwo);

        $expected = [
            'all' => [
                'field1' => 'value1',
                'field2' => 'value2',
            ],
            'any' => [],
            'none' => [],
        ];

        $this->assertEquals($expected, $criteria->getPreparedClause());
    }

    public function testBasicAnyConjunction(): void
    {
        $criteria = Criteria::createAny();
        $criterionOne = Criterion::create('field1', 'value1', Criterion::EQUAL);
        $criterionTwo = Criterion::create('field2', 'value2', Criterion::EQUAL);

        $criteria->addClause($criterionOne);
        $criteria->addClause($criterionTwo);

        $expected = [
            'all' => [],
            'any' => [
                'field1' => 'value1',
                'field2' => 'value2',
            ],
            'none' => [],
        ];

        $this->assertEquals($expected, $criteria->getPreparedClause());
    }

    public function testBasicAllConjunctionWithNone(): void
    {
        $criteria = Criteria::createAll();
        // These two should end up in the "none" array
        $criterionOne = Criterion::create('field1', 'value1', Criterion::NOT_EQUAL);
        $criterionTwo = Criterion::create('field2', 'value2', Criterion::NOT_EQUAL);
        // This should end up in the "all" array
        $criterionThree = Criterion::create('field3', 'value3', Criterion::EQUAL);

        $criteria->addClause($criterionOne);
        $criteria->addClause($criterionTwo);
        $criteria->addClause($criterionThree);

        $expected = [
            'all' => [
                'field3' => 'value3',
            ],
            'any' => [],
            'none' => [
                'field1' => 'value1',
                'field2' => 'value2',
            ],
        ];

        $this->assertEquals($expected, $criteria->getPreparedClause());
    }

    public function testBasicAnyConjunctionWithNone(): void
    {
        $criteria = Criteria::createAny();
        // These two should end up in the "none" array
        $criterionOne = Criterion::create('field1', 'value1', Criterion::NOT_EQUAL);
        $criterionTwo = Criterion::create('field2', 'value2', Criterion::NOT_EQUAL);
        // This should end up in the "any" array
        $criterionThree = Criterion::create('field3', 'value3', Criterion::EQUAL);

        $criteria->addClause($criterionOne);
        $criteria->addClause($criterionTwo);
        $criteria->addClause($criterionThree);

        $expected = [
            'all' => [],
            'any' => [
                'field3' => 'value3',
            ],
            'none' => [
                'field1' => 'value1',
                'field2' => 'value2',
            ],
        ];

        $this->assertEquals($expected, $criteria->getPreparedClause());
    }

    public function testAnyConjunctionNestedClauses(): void
    {
        $criteriaParent = Criteria::createAny();

        $childCriteriaAny = Criteria::createAny();
        // These two should end up in a nested "any" array
        $childCriterionAnyOne = Criterion::create('field1', 'value1', Criterion::EQUAL);
        $childCriterionAnyTwo = Criterion::create('field2', 'value2', Criterion::EQUAL);
        // This should end up in a nested "none" array
        $childCriterionAnyThree = Criterion::create('field3', 'value3', Criterion::NOT_EQUAL);
        // Add these child criterion
        $childCriteriaAny->addClause($childCriterionAnyOne);
        $childCriteriaAny->addClause($childCriterionAnyTwo);
        $childCriteriaAny->addClause($childCriterionAnyThree);

        $childCriteriaAll = Criteria::createAll();
        // These two should end up in a nested "all" array
        $childCriterionAllOne = Criterion::create('field4', 'value4', Criterion::EQUAL);
        $childCriterionAllTwo = Criterion::create('field5', 'value5', Criterion::EQUAL);
        // This should end up in a nested "none" array
        $childCriterionAllThree = Criterion::create('field6', 'value6', Criterion::NOT_EQUAL);
        // Add these child criterion
        $childCriteriaAll->addClause($childCriterionAllOne);
        $childCriteriaAll->addClause($childCriterionAllTwo);
        $childCriteriaAll->addClause($childCriterionAllThree);

        // Add all the child Criteria to the parent
        $criteriaParent->addClause($childCriteriaAny);
        $criteriaParent->addClause($childCriteriaAll);

        $expected = [
            'all' => [],
            'any' => [
                [
                    'all' => [],
                    'any' => [
                        'field1' => 'value1',
                        'field2' => 'value2',
                    ],
                    'none' => [
                        'field3' => 'value3',
                    ],
                ],
                [
                    'all' => [
                        'field4' => 'value4',
                        'field5' => 'value5',
                    ],
                    'any' => [],
                    'none' => [
                        'field6' => 'value6',
                    ],
                ],
            ],
            'none' => [],
        ];

        $this->assertEquals($expected, $criteriaParent->getPreparedClause());
    }

    public function testAllConjunctionNestedClauses(): void
    {
        $criteriaParent = Criteria::createAll();

        $childCriteriaAny = Criteria::createAny();
        // These two should end up in a nested "any" array
        $childCriterionAnyOne = Criterion::create('field1', 'value1', Criterion::EQUAL);
        $childCriterionAnyTwo = Criterion::create('field2', 'value2', Criterion::EQUAL);
        // This should end up in a nested "none" array
        $childCriterionAnyThree = Criterion::create('field3', 'value3', Criterion::NOT_EQUAL);
        // Add these child criterion
        $childCriteriaAny->addClause($childCriterionAnyOne);
        $childCriteriaAny->addClause($childCriterionAnyTwo);
        $childCriteriaAny->addClause($childCriterionAnyThree);

        $childCriteriaAll = Criteria::createAll();
        // These two should end up in a nested "all" array
        $childCriterionAllOne = Criterion::create('field4', 'value4', Criterion::EQUAL);
        $childCriterionAllTwo = Criterion::create('field5', 'value5', Criterion::EQUAL);
        // This should end up in a nested "none" array
        $childCriterionAllThree = Criterion::create('field6', 'value6', Criterion::NOT_EQUAL);
        // Add these child criterion
        $childCriteriaAll->addClause($childCriterionAllOne);
        $childCriteriaAll->addClause($childCriterionAllTwo);
        $childCriteriaAll->addClause($childCriterionAllThree);

        // Add all the child Criteria to the parent
        $criteriaParent->addClause($childCriteriaAny);
        $criteriaParent->addClause($childCriteriaAll);

        $expected = [
            'all' => [
                [
                    'all' => [],
                    'any' => [
                        'field1' => 'value1',
                        'field2' => 'value2',
                    ],
                    'none' => [
                        'field3' => 'value3',
                    ],
                ],
                [
                    'all' => [
                        'field4' => 'value4',
                        'field5' => 'value5',
                    ],
                    'any' => [],
                    'none' => [
                        'field6' => 'value6',
                    ],
                ],
            ],
            'any' => [],
            'none' => [],
        ];

        $this->assertEquals($expected, $criteriaParent->getPreparedClause());
    }

    protected function setUp(): void
    {
        parent::setUp();

        Injector::inst()->registerService(new CriteriaAdaptor(), CriteriaAdaptorInterface::class);
        Injector::inst()->registerService(new CriterionAdaptor(), CriterionAdaptorInterface::class);
    }

}
