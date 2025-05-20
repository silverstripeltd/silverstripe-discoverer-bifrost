<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use SilverStripe\Discoverer\Analytics\AnalyticsData;
use SilverStripe\Discoverer\Service\Interfaces\ProcessAnalyticsAdaptor as ProcessAnalyticsAdaptorInterface;

class ProcessAnalyticsAdaptor implements ProcessAnalyticsAdaptorInterface
{

    public function process(AnalyticsData $analyticsData): void
    {
        // Silently do nothing. Silverstripe Search does not support analytics
    }

}
