<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use Elastic\EnterpriseSearch\AppSearch\Schema\ClickParams;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Discoverer\Analytics\AnalyticsData;
use SilverStripe\Discoverer\Service\Interfaces\ProcessAnalyticsAdaptor as ProcessAnalyticsAdaptorInterface;
use SilverStripe\DiscovererBifrost\Service\Requests\ClickPost;
use SilverStripe\DiscovererElasticEnterprise\Service\Adaptors\BaseAdaptor;
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
            // The engine name comes from the search response, so it already includes the environment prefix
            $engineName = $analyticsData->getEngineName();
            $requestId = $analyticsData->getRequestId();
            $documentId = $analyticsData->getDocumentId();

            // Better safe than sorry, but these should be set if Analytics are enabled. If they aren't there (for
            // whatever reason), then silently log
            if (!$engineName || !$requestId || !$documentId) {
                // Log the error without breaking the page ("warning" is the highest level we can log without changing
                // the response code)
                $this->getLogger()->warning(
                    'Analytics data is missing required fields',
                    [
                        'engineName' => $engineName,
                        'requestId' => $requestId,
                        'documentId' => $documentId,
                    ]
                );

                return;
            }

            $params = Injector::inst()->create(
                ClickParams::class,
                (string) $analyticsData->getQueryString(),
                (string) $documentId
            );
            $params->request_id = (string) $requestId;

            $request = ClickPost::create($engineName, $params);

            $response = $this->getClient()->appSearch()->getTransport()->sendRequest($request->getRequest());
            $statusCode = $response->getStatusCode();

            // Report every unsuccessful response, not just server errors. A query API key without the analytics_click
            // permission, or an engine the key cannot reach, comes back as a 4xx, and those are exactly the
            // misconfigurations we want to see rather than silently drop
            if ($statusCode < 200 || $statusCode >= 300) {
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
