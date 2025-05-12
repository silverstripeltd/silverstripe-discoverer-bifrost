<?php

namespace SilverStripe\DiscovererBifrost\Tests\Service;

use GuzzleHttp\Client as GuzzleClient;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Discoverer\Service\SearchService;
use SilverStripe\DiscovererBifrost\Service\ClientFactory;
use Silverstripe\Search\Client\Client;

class ClientFactoryTest extends SapphireTest
{

    public function testCreateTest(): void
    {
        $clientFactory = new ClientFactory();
        $client = $clientFactory->create(
            SearchService::class,
            [
                'host' => 'https://abc123.com',
                'token' => 'abc123',
                'httpClient' => new GuzzleClient(),
            ]
        );

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testCreateMissingEnvVars(): void
    {
        Environment::setEnv('BIFROST_ENDPOINT', null);
        Environment::setEnv('BIFROST_QUERY_API_KEY', null);

        $this->expectExceptionMessage('Required ENV vars missing: BIFROST_ENDPOINT, BIFROST_QUERY_API_KEY');

        $clientFactory = new ClientFactory();
        // Expect this to throw our Exception as no params have been passed
        $clientFactory->create(SearchService::class);
    }

}
