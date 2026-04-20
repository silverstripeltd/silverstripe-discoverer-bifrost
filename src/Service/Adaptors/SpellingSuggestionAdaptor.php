<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use SilverStripe\Discoverer\Query\Suggestion;
use SilverStripe\Discoverer\Service\Interfaces\SpellingSuggestionAdaptor as SpellingSuggestionAdaptorInterface;
use SilverStripe\Discoverer\Service\Results\Suggestions;
use SilverStripe\Discoverer\Service\SearchService;
use SilverStripe\DiscovererBifrost\Processors\SpellingSuggestionRequestProcessor;
use SilverStripe\DiscovererBifrost\Processors\SpellingSuggestionsProcessor;
use Throwable;

class SpellingSuggestionAdaptor extends BaseAdaptor implements SpellingSuggestionAdaptorInterface
{

    public function process(Suggestion $suggestion, string $indexSuffix): Suggestions
    {
        try {
            $request = SpellingSuggestionRequestProcessor::singleton()->getRequest($suggestion);
            $response = $this->getClient()->spellingSuggestion(
                SearchService::singleton()->environmentizeIndex($indexSuffix),
                $request
            );

            $statusCode = $response->getStatusCode();
            $suggestions = Suggestions::create($statusCode);

            // Valid response; process and return
            if ($statusCode >= 200 && $statusCode < 300) {
                SpellingSuggestionsProcessor::singleton()->getProcessedSuggestions(
                    $suggestions,
                    json_decode((string) $response->getBody())
                );

                return $suggestions;
            }

            if ($statusCode >= 500) {
                // Log the error without breaking the page ("warning" is the highest level we can log without changing
                // the client response code)
                $this->getLogger()->warning(
                    sprintf('Spelling suggestion request failed with status %d', $statusCode),
                    [
                        'responseBody' => (string) $response->getBody(),
                    ]
                );
            }

            return $suggestions;
        } catch (Throwable $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing
            // the client response code)
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);

            return Suggestions::create(500);
        }
    }

}
