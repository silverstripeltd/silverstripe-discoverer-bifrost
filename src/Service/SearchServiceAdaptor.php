<?php

namespace SilverStripe\DiscovererBifrost\Service;

use Elastic\EnterpriseSearch\Client;
use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Discoverer\Analytics\AnalyticsData;
use SilverStripe\Discoverer\Query\Query;
use SilverStripe\Discoverer\Service\Results\Results;
use SilverStripe\Discoverer\Service\SearchServiceAdaptor as SearchServiceAdaptorInterface;

class SearchServiceAdaptor implements SearchServiceAdaptorInterface
{

    use Injectable;

    private ?Client $client = null;

    private ?LoggerInterface $logger = null;

    private static array $dependencies = [
        'client' => '%$' . Client::class . '.searchClient',
        'logger' => '%$' . LoggerInterface::class . '.errorhandler',
    ];

    public function setClient(?Client $client): void
    {
        $this->client = $client;
    }

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @throws Exception
     */
    public function search(Query $query, string $indexName): Results
    {
        return Results::create($query);
    }

    public function processAnalytics(AnalyticsData $analyticsData): void
    {
        // To be implemented
    }

    private function environmentizeIndex(string $indexName): string
    {
        $variant = Environment::getEnv('ENTERPRISE_SEARCH_ENGINE_PREFIX');

        if ($variant) {
            return sprintf('%s-%s', $variant, $indexName);
        }

        return $indexName;
    }

}
