<?php

namespace SilverStripe\DiscovererBifrost\Tests\Query\Filter;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Discoverer\Query\Filter\Criterion;
use SilverStripe\Discoverer\Query\Filter\CriterionAdaptor as CriterionAdaptorInterface;
use SilverStripe\DiscovererBifrost\Query\Filter\CriterionAdaptor;

class CriterionAdaptorTest extends SapphireTest
{

    /**
     * @dataProvider provideBasicComparisons
     */
    public function testBasicComparison(string $comparison): void
    {
        $criterion = Criterion::create('fieldName', 'fieldValue', $comparison);
        // Not using injector, because I'm testing this specific class
        $adaptor = new CriterionAdaptor();
        $expected = [
            'fieldName' => 'fieldValue',
        ];

        $this->assertEquals($expected, $adaptor->prepareCriterion($criterion));
    }

    public function provideBasicComparisons(): array
    {
        return [
            [Criterion::EQUAL],
            [Criterion::NOT_EQUAL],
            [Criterion::GREATER_EQUAL],
            [Criterion::LESS_EQUAL],
        ];
    }

    /**
     * @dataProvider provideUnsupportedComparisons
     */
    public function testUnsupportedComparison(string $comparison): void
    {
        $this->expectExceptionMessage(sprintf('Unsupported Elastic comparison "%s"', $comparison));

        $criterion = Criterion::create('fieldName', 'fieldValue', $comparison);
        // Not using injector, because I'm testing this specific class
        $adaptor = new CriterionAdaptor();
        // Should throw our Exception
        $adaptor->prepareCriterion($criterion);
    }

    public function provideUnsupportedComparisons(): array
    {
        return [
            [Criterion::LESS_THAN],
            [Criterion::GREATER_THAN],
            [Criterion::IS_NULL],
            [Criterion::IS_NOT_NULL],
        ];
    }

    public function testRangeToFromComparison(): void
    {
        $range = [
            'from' => 1,
            'to' => 2,
        ];
        $criterion = Criterion::create('fieldName', $range, Criterion::RANGE);
        // Not using injector, because I'm testing this specific class
        $adaptor = new CriterionAdaptor();
        $expected = [
            'fieldName' => $range,
        ];

        $this->assertEquals($expected, $adaptor->prepareCriterion($criterion));
    }

    public function testRangeToComparison(): void
    {
        $range = [
            'to' => 2,
        ];
        $criterion = Criterion::create('fieldName', $range, Criterion::RANGE);
        // Not using injector, because I'm testing this specific class
        $adaptor = new CriterionAdaptor();
        $expected = [
            'fieldName' => $range,
        ];

        $this->assertEquals($expected, $adaptor->prepareCriterion($criterion));
    }

    public function testRangeFromComparison(): void
    {
        $range = [
            'from' => 1,
        ];
        $criterion = Criterion::create('fieldName', $range, Criterion::RANGE);
        // Not using injector, because I'm testing this specific class
        $adaptor = new CriterionAdaptor();
        $expected = [
            'fieldName' => $range,
        ];

        $this->assertEquals($expected, $adaptor->prepareCriterion($criterion));
    }

    /**
     * @dataProvider provideInComparisons
     */
    public function testValidInComparison(string $comparison): void
    {
        $range = [
            1,
            2,
        ];
        $criterion = Criterion::create('fieldName', $range, $comparison);
        // Not using injector, because I'm testing this specific class
        $adaptor = new CriterionAdaptor();
        $expected = [
            'fieldName' => $range,
        ];

        $this->assertEquals($expected, $adaptor->prepareCriterion($criterion));
    }

    public function provideInComparisons(): array
    {
        return [
            [Criterion::IN],
            [Criterion::NOT_IN],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Injector::inst()->registerService(new CriterionAdaptor(), CriterionAdaptorInterface::class);
    }

}
