<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use SilverStripe\Discoverer\Analytics\AnalyticsData;
use SilverStripe\Discoverer\Service\Interfaces\ProcessAnalyticsAdaptor as ProcessAnalyticsAdaptorInterface;
use Silverstripe\Search\Client\Request\Analytics\ClickRequest;
use Throwable;

class ProcessAnalyticsAdaptor extends BaseAdaptor implements ProcessAnalyticsAdaptorInterface
{

    /**
     * General rule: We never want to break the search request being made for analytic click data. So, if something
     * goes wrong, we will log and continue
     */
    public function process(AnalyticsData $analyticsData): void
    {
        try {
            $indexName = $analyticsData->getIndexName();
            $requestId = $analyticsData->getRequestId();
            $documentId = $analyticsData->getDocumentId();

            // Better safe than sorry, but these should be set if Analytics are enabled. If they aren't there (for
            // whatever reason), then silently log
            if (!$indexName || !$requestId || !$documentId) {
                // Log the error without breaking the page ("warning" is the highest level we can log without changing
                // the response code)
                $this->getLogger()->warning(
                    'Analytics data is missing required fields',
                    [
                        'indexName' => $indexName,
                        'requestId' => $requestId,
                        'documentId' => $documentId,
                    ]
                );

                return;
            }

            $request = new ClickRequest((string) $requestId, (string) $documentId);

            $response = $this->getClient()->clickPost($indexName, $request);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 500) {
                // Log the error without breaking the page ("warning" is the highest level we can log without changing
                // the response code)
                $this->getLogger()->warning(
                    sprintf('Analytics click request failed with status %d', $statusCode),
                    [
                        'responseBody' => (string) $response->getBody(),
                    ]
                );
            }
        } catch (Throwable $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing
            // the response code)
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);
        }
    }

}
