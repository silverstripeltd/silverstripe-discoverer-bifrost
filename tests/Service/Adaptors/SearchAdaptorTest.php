<?php

namespace SilverStripe\DiscovererBifrost\Tests\Service\Adaptors;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Http\Client\Common\Plugin\AddHostPlugin;
use Http\Client\Common\Plugin\AddPathPlugin;
use Http\Client\Common\Plugin\HeaderAppendPlugin;
use Http\Client\Common\PluginClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Discoverer\Query\Query;
use SilverStripe\Discoverer\Service\SearchService;
use SilverStripe\DiscovererBifrost\Service\Adaptors\SearchAdaptor;
use SilverStripe\DiscovererBifrost\Tests\Logger\QuietLogger;
use Silverstripe\Search\Client\Client;
use stdClass;

class SearchAdaptorTest extends SapphireTest
{

    protected ?MockHandler $mock;

    public function testSearchSuccess(): void
    {
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        $body = json_encode($this->getResponseWithRecords(2));

        $this->mock->append(new Response(200, $headers, $body));

        // Instantiate a new Query
        $query = Query::create('query string');
        // Set pagination purely so that we can check that our query params are applied
        $query->setPagination(10, 20);
        // Instantiate our service
        $service = SearchService::create();
        // Perform our search against the 'main' index
        $results = $service->search($query, 'main');

        $this->assertTrue($results->isSuccess());
        $this->assertCount(2, $results->getRecords());
        // Check that those query params were set as part of our search
        $this->assertEquals(10, $results->getQuery()->getPaginationLimit());
        $this->assertEquals(20, $results->getQuery()->getPaginationOffset());
    }

    public function testSearchFailure(): void
    {
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        $body = $this->getResponseWithRecords(2);
        // Remove a required field in order to create an error
        unset($body->meta);
        $body = json_encode($body);

        $this->mock->append(new Response(200, $headers, $body));

        $query = Query::create('query string');
        $service = SearchService::create();
        $results = $service->search($query, 'main');

        $this->assertFalse($results->isSuccess());
    }

    protected function setUp(): void
    {
        parent::setUp();

        Environment::setEnv('BIFROST_ENGINE_PREFIX', 'bifrost');

        // Set up a mock handler/client so that we can feed in mock responses that we expected to get from the API
        $this->mock = new MockHandler([]);
        $handler = HandlerStack::create($this->mock);
        $httpClient = new GuzzleClient(['handler' => $handler]);

        $plugins = [
            new AddHostPlugin(Psr17FactoryDiscovery::findUriFactory()->createUri('https://bifrost.io')),
            new AddPathPlugin(Psr17FactoryDiscovery::findUriFactory()->createUri('/api/v1')),
            new HeaderAppendPlugin([
                'Authorization' => 'Bearer fakeToken',
            ]),
        ];

        $client = Client::create(new PluginClient($httpClient, $plugins));

        Injector::inst()->registerService($client, Client::class . '.searchClient');
        // Add our quiet logger, so that our API calls don't create any noise in our test report
        Injector::inst()->registerService(new QuietLogger(), LoggerInterface::class . '.errorhandler');
    }

    private function getResponseWithRecords(int $numRecords = 1): stdClass
    {
        $records = [];

        for ($i = 1; $i <= $numRecords; $i++) {
            $records[] = [
                'title' => [
                    'raw' => sprintf('Search term highlighted in title: Record %s', $i),
                    'snippet' => sprintf('<em>Search</em> <em>term</em> highlighted in title: Record %s', $i),
                ],
                'description' => [
                    'raw' => sprintf('Search term highlighted in description: Record %s', $i),
                    'snippet' => sprintf('<em>Search</em> <em>term</em> highlighted in description: Record %s', $i),
                ],
                'record_id' => [
                    'raw' => sprintf('%s', $i),
                ],
                'source_class' => [
                    'raw' => 'App\\Pages\\BlockPage',
                ],
                'id' => [
                    'raw' => sprintf('app_pages_blockpage_%s', $i),
                ],
            ];
        }

        $response = [
            'meta' => [
                'alerts' => [],
                'warnings' => [],
                'precision' => 2,
                'engine' => [
                    'name' => 'bifrost-main',
                    'type' => 'default',
                ],
                'page' => [
                    'current' => 1,
                    'total_pages' => 10,
                    'total_results' => 100,
                    'size' => 10,
                ],
                'request_id' => '123abc',
            ],
            'results' => $records,
        ];

        // Convert response to stdClass
        return json_decode(json_encode($response), false);
    }

}
