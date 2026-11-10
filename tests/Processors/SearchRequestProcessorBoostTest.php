<?php

namespace SilverStripe\DiscovererBifrost\Tests\Processors;

use ArrayObject;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Discoverer\Query\Boost\Boost;
use SilverStripe\Discoverer\Query\Boost\BoostAdaptor as BoostAdaptorInterface;
use SilverStripe\Discoverer\Query\Boost\BoostCollection;
use SilverStripe\Discoverer\Query\Boost\NullBoostAdaptor;
use SilverStripe\Discoverer\Query\Boost\ProximityBoost;
use SilverStripe\Discoverer\Query\Query;
use SilverStripe\DiscovererBifrost\Processors\SearchRequestProcessor;
use SilverStripe\DiscovererBifrost\Query\Boost\BoostAdaptor;
use SilverStripe\DiscovererBifrost\Tests\Query\Boost\BoostAdaptorTest;
use TypeError;

/**
 * Covers the query-time boosts sent on the Search Service request. The data provider is the payload spec.
 *
 * @see SearchRequestProcessor::getRequest()
 * @see BoostAdaptorTest for the translation rules of individual boosts
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
     * Proves a query without boosts never asks the adaptor to prepare them.
     */
    public function testNoBoostsSkipsAdaptor(): void
    {
        Injector::inst()->registerService(new class extends NullBoostAdaptor {

            /**
             * Fail the test if the processor asks for prepared boosts.
             *
             * @param BoostCollection $boostCollection - the boosts the processor asked to prepare
             * @return mixed - never returns
             */
            public function prepareBoosts(BoostCollection $boostCollection): mixed
            {
                throw new LogicException('The adaptor should not be called for a query without boosts');
            }

        }, BoostAdaptorInterface::class);

        $request = SearchRequestProcessor::singleton()->getRequest(Query::create('search string'));

        $this->assertNull($request->getBoosts());
    }

    /**
     * Proves an adaptor returning something other than an array or `null` fails loudly instead of sending no boosts.
     */
    public function testNonArrayAdaptorResultThrows(): void
    {
        Injector::inst()->registerService(new class extends NullBoostAdaptor {

            /**
             * Return prepared boosts in the wrong shape.
             *
             * @param BoostCollection $boostCollection - the boosts to prepare
             * @return mixed - an `ArrayObject` where an array is expected
             */
            public function prepareBoosts(BoostCollection $boostCollection): mixed
            {
                return new ArrayObject();
            }

        }, BoostAdaptorInterface::class);

        $query = Query::create('search string');
        $query->addBoost('featured', Boost::value(['true']));

        $this->expectException(TypeError::class);

        SearchRequestProcessor::singleton()->getRequest($query);
    }

    /**
     * Proves a provider adaptor that ignores boosts, returning `null`, sends no `boosts` key.
     */
    public function testNullAdaptorOmitsKey(): void
    {
        Injector::inst()->registerService(new NullBoostAdaptor(), BoostAdaptorInterface::class);

        $query = Query::create('search string');
        $query->addBoost('featured', Boost::value(['true']));

        $request = SearchRequestProcessor::singleton()->getRequest($query);
        $payload = json_decode(json_encode($request), true);

        $this->assertArrayNotHasKey('boosts', $payload);
        $this->assertNull($request->getBoosts());
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
     * Proves an out-of-range factor throws when it is added to the query, naming the field and the allowed range,
     * rather than when the request is built inside the search's catch-all.
     */
    public function testOutOfRangeFactorThrowsWhenAdded(): void
    {
        $query = Query::create('search string');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/popularity.*between 0 and 10/');

        $query->addBoost('popularity', Boost::value(['x'], 10.5));
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
                [
                    [
                        'popularity',
                        static fn(): Boost => Boost::proximity(50000, ProximityBoost::FUNCTION_GAUSSIAN, 0.5),
                    ],
                ],
                [
                    'popularity' => [
                        ['type' => 'proximity', 'factor' => 0.5, 'center' => '50000', 'function' => 'gaussian'],
                    ],
                ],
            ],
            'proximity on number, whole-number float center' => [
                [['popularity', static fn(): Boost => Boost::proximity(100.0, ProximityBoost::FUNCTION_LINEAR)]],
                [
                    'popularity' => [
                        ['type' => 'proximity', 'factor' => 1.0, 'center' => '100', 'function' => 'linear'],
                    ],
                ],
            ],
            'proximity on date, relative center, exponential' => [
                [
                    [
                        'published_date',
                        static fn(): Boost => Boost::proximity('now-7d', ProximityBoost::FUNCTION_EXPONENTIAL),
                    ],
                ],
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
                        static fn(): Boost => Boost::proximity(
                            '-36.8485,174.7633',
                            ProximityBoost::FUNCTION_LINEAR,
                            10
                        ),
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
                    ['popularity', static fn(): Boost => Boost::proximity(100, ProximityBoost::FUNCTION_GAUSSIAN)],
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

    /**
     * Register the Search Service boost adaptor, as its config only applies when the service is configured.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Injector::inst()->registerService(new BoostAdaptor(), BoostAdaptorInterface::class);
    }

}
