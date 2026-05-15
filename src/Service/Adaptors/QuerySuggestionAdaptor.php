<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use SilverStripe\Discoverer\Query\Suggestion;
use SilverStripe\Discoverer\Service\Interfaces\QuerySuggestionAdaptor as QuerySuggestionAdaptorInterface;
use SilverStripe\Discoverer\Service\Results\Suggestions;
use SilverStripe\Discoverer\Service\SearchService;
use SilverStripe\DiscovererBifrost\Processors\QuerySuggestionRequestProcessor;
use SilverStripe\DiscovererBifrost\Processors\QuerySuggestionsProcessor;
use Throwable;

class QuerySuggestionAdaptor extends BaseAdaptor implements QuerySuggestionAdaptorInterface
{

    public function process(Suggestion $suggestion, string $indexSuffix): Suggestions
    {
        try {
            $request = QuerySuggestionRequestProcessor::singleton()->getRequest($suggestion);
            $response = $this->getClient()->querySuggestion(
                SearchService::singleton()->environmentizeIndex($indexSuffix),
                $request
            );

            $statusCode = $response->getStatusCode();
            $suggestions = Suggestions::create($statusCode);

            // Valid response; process and return
            if ($statusCode >= 200 && $statusCode < 300) {
                QuerySuggestionsProcessor::singleton()->getProcessedSuggestions(
                    $suggestions,
                    json_decode((string) $response->getBody())
                );

                return $suggestions;
            }

            if ($statusCode >= 500) {
                // Log the error without breaking the page ("warning" is the highest level we can log without changing
                // the client response code)
                $this->getLogger()->warning(
                    sprintf('Query suggestion request failed with status %d', $statusCode),
                    [
                        'responseBody' => (string) $response->getBody(),
                    ]
                );
            }

            return $suggestions;
        } catch (Throwable $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing the
            // client response code)
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);

            return Suggestions::create(500);
        }
    }

}
