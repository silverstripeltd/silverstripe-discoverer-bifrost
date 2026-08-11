<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use Elastic\EnterpriseSearch\Client;
use Psr\Log\LoggerInterface;
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
                $this->logWarning(
                    'Analytics data is missing required fields',
                    [
                        'engineName' => $engineName,
                        'requestId' => $requestId,
                        'documentId' => $documentId,
                    ]
                );

                return;
            }

            $request = ClickPost::create($engineName, (string) $requestId, (string) $documentId);

            $response = $this->getSearchClient()->appSearch()->getTransport()->sendRequest($request->getRequest());
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 500) {
                // Log the error without breaking the page ("warning" is the highest level we can log without changing
                // the response code)
                $this->logWarning(
                    sprintf('Analytics click request failed with status %d', $statusCode),
                    [
                        'responseBody' => (string) $response->getBody(),
                    ]
                );
            }
        } catch (Throwable $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing
            // the response code)
            $this->logWarning($e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * The middleware that reaches this adaptor runs on every request, including ones where this object was built
     * without its BaseAdaptor dependencies (a config manifest still holding an older version of this class does it).
     * Fall back to the service directly rather than calling a method on null, and let an unresolvable service throw
     * so that process() can log why
     */
    private function getSearchClient(): Client
    {
        return $this->getClient() ?? Injector::inst()->get(Client::class . '.searchClient');
    }

    /**
     * Analytics must never break the page it was collected on, so this has to hold up even when there is no logger to
     * report to - see getSearchClient() for how that happens
     */
    private function logWarning(string $message, array $context = []): void
    {
        try {
            $logger = $this->getLogger() ?? Injector::inst()->get(LoggerInterface::class . '.errorhandler');

            $logger->warning($message, $context);
        } catch (Throwable) {
            // There is nowhere left to report this
        }
    }

}
