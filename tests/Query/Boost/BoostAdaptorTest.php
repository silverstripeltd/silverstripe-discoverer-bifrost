<?php

namespace SilverStripe\DiscovererBifrost\Tests\Query\Boost;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Discoverer\Query\Boost\Boost;
use SilverStripe\Discoverer\Query\Boost\BoostCollection;
use SilverStripe\Discoverer\Query\Boost\ProximityBoost;
use SilverStripe\Discoverer\Query\Boost\ValueBoost;
use SilverStripe\DiscovererBifrost\Query\Boost\BoostAdaptor;
use Silverstripe\Search\Client\Model\Field\Boost as ApiBoost;

/**
 * Covers the translation of Discoverer boosts into Search Service boosts.
 *
 * @see BoostAdaptor
 */
class BoostAdaptorTest extends SapphireTest
{

    /**
     * Proves every boost class and function constant maps to its lowercase Search Service string.
     *
     * @param callable(): Boost $makeBoost - builds the boost, lazily as the provider runs before the Injector manifest
     * @param string $type - the expected API boost type
     * @param string|null $function - the expected API boost function, `null` for a value boost
     */
    #[DataProvider('constantMappingProvider')]
    public function testConstantsMapToApiStrings(callable $makeBoost, string $type, ?string $function): void
    {
        $apiBoost = $this->prepareSingle($makeBoost());

        $this->assertSame($type, $apiBoost->getType());
        $this->assertSame($function, $apiBoost->getFunction());
    }

    /**
     * Proves float centres and values are sent as plain decimal strings, never in scientific notation.
     *
     * @param float $number - the float to format
     * @param string $expected - the expected string
     */
    #[DataProvider('floatProvider')]
    public function testFloatFormatting(float $number, string $expected): void
    {
        $proximity = $this->prepareSingle(Boost::proximity($number, ProximityBoost::FUNCTION_LINEAR));
        $value = $this->prepareSingle(Boost::value([$number]));

        $this->assertSame($expected, $proximity->getCenter());
        $this->assertSame([$expected], $value->getValue());
    }

    /**
     * Proves values and centres of every scalar type are cast to strings.
     */
    public function testScalarsCastToStrings(): void
    {
        $value = $this->prepareSingle(Boost::value(['a', 4, 2.5]));
        $proximity = $this->prepareSingle(Boost::proximity(50000, ProximityBoost::FUNCTION_GAUSSIAN));

        $this->assertSame(['a', '4', '2.5'], $value->getValue());
        $this->assertSame('50000', $proximity->getCenter());
    }

    /**
     * Proves values with gaps in their keys are sent as a list.
     */
    public function testFilteredValuesAreAList(): void
    {
        $value = $this->prepareSingle(Boost::value(array_filter(['a', '', 'b'])))->getValue();

        $this->assertTrue(array_is_list($value));
        $this->assertSame(['a', 'b'], $value);
    }

    /**
     * Proves a given factor passes through unchanged, and an omitted one uses the client default.
     */
    public function testFactorPassesThroughOrUsesClientDefault(): void
    {
        $this->assertSame(1.0, $this->prepareSingle(Boost::value(['x']))->getFactor());
        $this->assertSame(0.0, $this->prepareSingle(Boost::value(['x'], 0))->getFactor());
        $this->assertSame(10.0, $this->prepareSingle(Boost::value(['x'], 10))->getFactor());
        $this->assertSame(3.5, $this->prepareSingle(Boost::value(['x'], 3.5))->getFactor());
    }

    /**
     * Proves validation rejects a factor outside 0 to 10 rather than clamping it.
     *
     * @param callable(): Boost $makeBoost - builds the boost with the out-of-range factor
     */
    #[DataProvider('outOfRangeFactorProvider')]
    public function testValidateBoostRejectsOutOfRangeFactor(callable $makeBoost): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/popularity.*between 0 and 10/');

        (new BoostAdaptor())->validateBoost('popularity', $makeBoost());
    }

    /**
     * Proves validation rejects a `Boost` subclass the adaptor does not know.
     */
    public function testValidateBoostRejectsUnknownBoostSubclass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported boost type');

        (new BoostAdaptor())->validateBoost('field', new class extends Boost {
        });
    }

    /**
     * Proves preparing still rejects an out-of-range factor from a collection with no adaptor, which never
     * validated its boosts when they were added.
     *
     * @param callable(): Boost $makeBoost - builds the boost with the out-of-range factor
     */
    #[DataProvider('outOfRangeFactorProvider')]
    public function testFactorOutOfRangeThrows(callable $makeBoost): void
    {
        $collection = new BoostCollection();
        $collection->addBoost('popularity', $makeBoost());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/popularity.*between 0 and 10/');

        (new BoostAdaptor())->prepareBoosts($collection);
    }

    /**
     * Proves preparing still rejects an unknown `Boost` subclass from a collection with no adaptor, rather than
     * sending it half-built.
     */
    public function testUnknownBoostSubclassThrows(): void
    {
        $collection = new BoostCollection();
        $collection->addBoost('field', new class extends Boost {
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported boost type');

        (new BoostAdaptor())->prepareBoosts($collection);
    }

    /**
     * Proves an empty collection prepares to an empty array.
     */
    public function testNoBoostsReturnsEmptyArray(): void
    {
        $this->assertSame([], (new BoostAdaptor())->prepareBoosts(new BoostCollection()));
    }

    /**
     * Proves boosts stay grouped by field name in the order they were added, and never carry an `operation`.
     */
    public function testGroupsByFieldInOrder(): void
    {
        $collection = new BoostCollection();
        $collection->addBoost('category', Boost::value(['news'], 2));
        $collection->addBoost('popularity', Boost::proximity(100, ProximityBoost::FUNCTION_GAUSSIAN));
        $collection->addBoost('category', Boost::value(['events'], 1));

        $boosts = (new BoostAdaptor())->prepareBoosts($collection);

        $this->assertSame(['category', 'popularity'], array_keys($boosts));
        $this->assertCount(2, $boosts['category']);
        $this->assertSame(['news'], $boosts['category'][0]->getValue());
        $this->assertSame(['events'], $boosts['category'][1]->getValue());
        $this->assertContainsOnlyInstancesOf(ApiBoost::class, $boosts['popularity']);

        foreach ($boosts as $fieldBoosts) {
            foreach ($fieldBoosts as $boost) {
                $this->assertNull($boost->getOperation());
            }
        }
    }

    /**
     * Supplies one case per boost class and function constant.
     *
     * @return array<string, array{0: callable(): Boost, 1: string, 2: string|null}>
     */
    public static function constantMappingProvider(): array
    {
        return [
            'ValueBoost' => [static fn(): Boost => Boost::value(['x']), 'value', null],
            'FUNCTION_GAUSSIAN' => [
                static fn(): Boost => Boost::proximity(1, ProximityBoost::FUNCTION_GAUSSIAN),
                'proximity',
                'gaussian',
            ],
            'FUNCTION_EXPONENTIAL' => [
                static fn(): Boost => Boost::proximity(1, ProximityBoost::FUNCTION_EXPONENTIAL),
                'proximity',
                'exponential',
            ],
            'FUNCTION_LINEAR' => [
                static fn(): Boost => Boost::proximity(1, ProximityBoost::FUNCTION_LINEAR),
                'proximity',
                'linear',
            ],
        ];
    }

    /**
     * Supplies floats alongside their expected plain decimal strings.
     *
     * @return array<string, array{0: float, 1: string}>
     */
    public static function floatProvider(): array
    {
        return [
            'whole number' => [100.0, '100'],
            'fraction' => [0.5, '0.5'],
            'negative fraction' => [-2.5, '-2.5'],
            'negative zero' => [-0.0, '0'],
            'large, would use an exponent' => [1.0e15, '1000000000000000'],
            'small, would use an exponent' => [0.00001, '0.00001'],
            'many significant digits' => [1234567.123456789, '1234567.123456789'],
        ];
    }

    /**
     * Supplies boosts whose factor the Search Service would reject.
     *
     * @return array<string, array{0: callable(): Boost}>
     */
    public static function outOfRangeFactorProvider(): array
    {
        return [
            'above the maximum' => [static fn(): Boost => Boost::value(['x'], 10.5)],
            'just above the maximum' => [static fn(): Boost => Boost::value(['x'], 10.000001)],
            // Discoverer rejects these itself, so override the getter to prove the adaptor holds the line too
            'negative' => [static fn(): Boost => self::valueBoostWithFactor(-1.0)],
            'NAN' => [static fn(): Boost => self::valueBoostWithFactor(NAN)],
            'INF' => [static fn(): Boost => self::valueBoostWithFactor(INF)],
        ];
    }

    /**
     * Build a value boost that reports the given factor, bypassing Discoverer's own validation.
     *
     * @param float $factor - the factor the boost reports
     * @return ValueBoost - the boost
     */
    private static function valueBoostWithFactor(float $factor): ValueBoost
    {
        return new class (['x'], $factor) extends ValueBoost {

            /**
             * The factor reported in place of the validated one.
             */
            private float $reportedFactor;

            /**
             * Build the boost with a factor that skips validation.
             *
             * @param array<int, string> $values - the values to boost
             * @param float $reportedFactor - the factor to report
             */
            public function __construct(array $values, float $reportedFactor)
            {
                parent::__construct($values);

                $this->reportedFactor = $reportedFactor;
            }

            /**
             * Report the unvalidated factor.
             *
             * @return float|null - the factor given to the constructor
             */
            public function getFactor(): ?float
            {
                return $this->reportedFactor;
            }

        };
    }

    /**
     * Prepare a single boost and return its Search Service form.
     *
     * @param Boost $boost - the boost to prepare
     * @return ApiBoost - the translated boost
     */
    private function prepareSingle(Boost $boost): ApiBoost
    {
        $collection = new BoostCollection();
        $collection->addBoost('field', $boost);

        return (new BoostAdaptor())->prepareBoosts($collection)['field'][0];
    }

}
