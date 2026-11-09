<?php

namespace SilverStripe\DiscovererBifrost\Tests\Processors;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Discoverer\Query\Boost;
use SilverStripe\Discoverer\Query\Query;
use SilverStripe\DiscovererBifrost\Processors\SearchRequestProcessor;

/**
 * Covers the query-time boosts sent on the Search Service request. The data provider is the payload spec.
 *
 * @see SearchRequestProcessor::getRequest()
 */
class SearchRequestProcessorBoostTest extends SapphireTest
{

    /**
     * Proves each set of Discoverer boosts serialises to the expected Search Service `boosts` payload.
     *
     * @param array<int, array{0: string, 1: callable(): Boost}> $calls - field and boost factory pairs; boosts are
     *     built lazily, as the provider runs before the Injector manifest exists
     * @param array<string, mixed> $expected - the expected serialised `boosts` payload
     */
    #[DataProvider('boostPayloadProvider')]
    public function testBoostPayload(array $calls, array $expected): void
    {
        $query = Query::create('search string');

        foreach ($calls as [$field, $makeBoost]) {
            $query->addBoost($field, $makeBoost());
        }

        $request = SearchRequestProcessor::singleton()->getRequest($query);
        $payload = json_decode(json_encode($request), true);

        $this->assertEquals($expected, $payload['boosts']);

        // assertEquals treats '4' and 4 as equal, so check the string-typed leaves strictly
        foreach ($payload['boosts'] as $fieldBoosts) {
            foreach ($fieldBoosts as $boost) {
                if (isset($boost['value'])) {
                    $this->assertContainsOnly('string', $boost['value']);
                }

                if (isset($boost['center'])) {
                    $this->assertIsString($boost['center']);
                }
            }
        }

        $this->assertStringNotContainsString('"operation"', json_encode($payload['boosts']));
    }

    /**
     * Proves values with gaps in their keys still serialise as a JSON list rather than an object.
     */
    public function testFilteredValuesSerialiseAsJsonList(): void
    {
        $query = Query::create('search string');
        $query->addBoost('featured', Boost::value(array_filter(['a', '', 'b'])));

        $request = SearchRequestProcessor::singleton()->getRequest($query);
        $payload = json_decode(json_encode($request), true);
        $value = $payload['boosts']['featured'][0]['value'];

        $this->assertTrue(array_is_list($value));
        $this->assertSame(['a', 'b'], $value);
    }

    /**
     * Proves a query without boosts sends no `boosts` key at all.
     */
    public function testNoBoostsOmitsKey(): void
    {
        $request = SearchRequestProcessor::singleton()->getRequest(Query::create('search string'));
        $payload = json_decode(json_encode($request), true);

        $this->assertArrayNotHasKey('boosts', $payload);
        $this->assertNull($request->getBoosts());
    }

    /**
     * Proves a factor above the accepted range throws rather than being clamped.
     */
    public function testFactorAboveRangeThrows(): void
    {
        $query = Query::create('search string');
        $query->addBoost('popularity', Boost::value(['x'], 10.5));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('popularity');
        $this->expectExceptionMessage('between 0 and 10');

        SearchRequestProcessor::singleton()->getRequest($query);
    }

    /**
     * Proves a numeric field name, which PHP turns into an int array key, is still sent as a string key.
     */
    public function testNumericFieldNameSerialisesAsStringKey(): void
    {
        $query = Query::create('search string');
        $query->addBoost('2024', Boost::value(['x']));

        $request = SearchRequestProcessor::singleton()->getRequest($query);
        $payload = json_decode(json_encode($request), true);

        $this->assertSame(['2024'], array_map('strval', array_keys($payload['boosts'])));
        $this->assertSame('value', $payload['boosts']['2024'][0]['type']);
        $this->assertSame(['x'], $payload['boosts']['2024'][0]['value']);
    }

    /**
     * Proves every Discoverer boost type and function constant maps to its lowercase Search Service string.
     *
     * @param callable(): Boost $makeBoost - builds the boost, lazily as the provider runs before the Injector manifest
     * @param string $key - the payload key holding the mapped string
     * @param string $expected - the lowercase API string
     */
    #[DataProvider('constantMappingProvider')]
    public function testConstantsMapToApiStrings(callable $makeBoost, string $key, string $expected): void
    {
        $query = Query::create('search string');
        $query->addBoost('field', $makeBoost());

        $request = SearchRequestProcessor::singleton()->getRequest($query);
        $payload = json_decode(json_encode($request), true);

        $this->assertSame($expected, $payload['boosts']['field'][0][$key]);
    }

    /**
     * Proves floats are sent as plain decimal strings, never in scientific notation.
     *
     * @param float $center - the proximity centre
     * @param string $expected - the expected serialised centre
     */
    #[DataProvider('floatCenterProvider')]
    public function testFloatCenterFormatting(float $center, string $expected): void
    {
        $query = Query::create('search string');
        $query->addBoost('field', Boost::proximity($center, Boost::FUNCTION_LINEAR));

        $request = SearchRequestProcessor::singleton()->getRequest($query);
        $payload = json_decode(json_encode($request), true);

        $this->assertSame($expected, $payload['boosts']['field'][0]['center']);
    }

    /**
     * Supplies one case per Discoverer type and function constant.
     *
     * @return array<string, array{0: callable(): Boost, 1: string, 2: string}>
     */
    public static function constantMappingProvider(): array
    {
        return [
            'TYPE_VALUE' => [static fn(): Boost => Boost::value(['x']), 'type', 'value'],
            'TYPE_PROXIMITY' => [
                static fn(): Boost => Boost::proximity(1, Boost::FUNCTION_LINEAR),
                'type',
                'proximity',
            ],
            'FUNCTION_GAUSSIAN' => [
                static fn(): Boost => Boost::proximity(1, Boost::FUNCTION_GAUSSIAN),
                'function',
                'gaussian',
            ],
            'FUNCTION_EXPONENTIAL' => [
                static fn(): Boost => Boost::proximity(1, Boost::FUNCTION_EXPONENTIAL),
                'function',
                'exponential',
            ],
            'FUNCTION_LINEAR' => [
                static fn(): Boost => Boost::proximity(1, Boost::FUNCTION_LINEAR),
                'function',
                'linear',
            ],
        ];
    }

    /**
     * Supplies float centres alongside their expected plain decimal strings.
     *
     * @return array<string, array{0: float, 1: string}>
     */
    public static function floatCenterProvider(): array
    {
        return [
            'whole number' => [100.0, '100'],
            'fraction' => [0.5, '0.5'],
            'negative fraction' => [-2.5, '-2.5'],
            'large, would use an exponent' => [1.0e15, '1000000000000000'],
            'small, would use an exponent' => [0.00001, '0.00001'],
            'many significant digits' => [1234567.123456789, '1234567.123456789'],
        ];
    }

    /**
     * Supplies each set of boosts alongside the payload it must serialise to.
     *
     * @return array<string, array{0: array<int, array{0: string, 1: callable(): Boost}>, 1: array<string, mixed>}>
     */
    public static function boostPayloadProvider(): array
    {
        return [
            'value boost, default factor' => [
                [['featured', static fn(): Boost => Boost::value(['true'])]],
                ['featured' => [['type' => 'value', 'factor' => 1.0, 'value' => ['true']]]],
            ],
            'value boost, numeric values cast to strings, explicit factor' => [
                [['rating', static fn(): Boost => Boost::value([4, 5], 3)]],
                ['rating' => [['type' => 'value', 'factor' => 3.0, 'value' => ['4', '5']]]],
            ],
            'proximity on number, gaussian, int center' => [
                [['popularity', static fn(): Boost => Boost::proximity(50000, Boost::FUNCTION_GAUSSIAN, 0.5)]],
                [
                    'popularity' => [
                        ['type' => 'proximity', 'factor' => 0.5, 'center' => '50000', 'function' => 'gaussian'],
                    ],
                ],
            ],
            'proximity on number, whole-number float center' => [
                [['popularity', static fn(): Boost => Boost::proximity(100.0, Boost::FUNCTION_LINEAR)]],
                [
                    'popularity' => [
                        ['type' => 'proximity', 'factor' => 1.0, 'center' => '100', 'function' => 'linear'],
                    ],
                ],
            ],
            'proximity on date, relative center, exponential' => [
                [['published_date', static fn(): Boost => Boost::proximity('now-7d', Boost::FUNCTION_EXPONENTIAL)]],
                [
                    'published_date' => [
                        ['type' => 'proximity', 'factor' => 1.0, 'center' => 'now-7d', 'function' => 'exponential'],
                    ],
                ],
            ],
            'proximity on geolocation, linear' => [
                [
                    [
                        'location',
                        static fn(): Boost => Boost::proximity('-36.8485,174.7633', Boost::FUNCTION_LINEAR, 10),
                    ],
                ],
                [
                    'location' => [
                        [
                            'type' => 'proximity',
                            'factor' => 10.0,
                            'center' => '-36.8485,174.7633',
                            'function' => 'linear',
                        ],
                    ],
                ],
            ],
            'two boosts on one field keep order' => [
                [
                    ['category', static fn(): Boost => Boost::value(['news'], 2)],
                    ['category', static fn(): Boost => Boost::value(['events'], 1)],
                ],
                [
                    'category' => [
                        ['type' => 'value', 'factor' => 2.0, 'value' => ['news']],
                        ['type' => 'value', 'factor' => 1.0, 'value' => ['events']],
                    ],
                ],
            ],
            'boosts across fields' => [
                [
                    ['featured', static fn(): Boost => Boost::value(['true'], 5)],
                    ['popularity', static fn(): Boost => Boost::proximity(100, Boost::FUNCTION_GAUSSIAN)],
                ],
                [
                    'featured' => [['type' => 'value', 'factor' => 5.0, 'value' => ['true']]],
                    'popularity' => [
                        ['type' => 'proximity', 'factor' => 1.0, 'center' => '100', 'function' => 'gaussian'],
                    ],
                ],
            ],
            'factor at bounds' => [
                [
                    ['a', static fn(): Boost => Boost::value(['x'], 0)],
                    ['b', static fn(): Boost => Boost::value(['y'], 10)],
                ],
                [
                    'a' => [['type' => 'value', 'factor' => 0.0, 'value' => ['x']]],
                    'b' => [['type' => 'value', 'factor' => 10.0, 'value' => ['y']]],
                ],
            ],
        ];
    }

}
