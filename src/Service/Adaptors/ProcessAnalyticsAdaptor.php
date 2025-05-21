<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use Psr\Log\LoggerInterface;
use SilverStripe\Discoverer\Analytics\AnalyticsData;
use SilverStripe\Discoverer\Service\Interfaces\ProcessAnalyticsAdaptor as ProcessAnalyticsAdaptorInterface;

/**
 * @deprecated Removed in version 3.0. We recommend that you remove SEARCH_ANALYTICS_ENABLED
 */
class ProcessAnalyticsAdaptor implements ProcessAnalyticsAdaptorInterface
{

    private ?LoggerInterface $logger = null;

    private static array $dependencies = [
        'logger' => '%$' . LoggerInterface::class . '.errorhandler',
    ];

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function process(AnalyticsData $analyticsData): void
    {
        $this->logger->info(
            'SilverStripe\DiscovererBifrost\Service\Adaptors\ProcessAnalyticsAdaptor is deprecated and will be removed'
            . ' in version 3.0. We recommend that you remove SEARCH_ANALYTICS_ENABLED'
        );
    }

}
