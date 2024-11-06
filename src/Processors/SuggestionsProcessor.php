<?php

namespace SilverStripe\DiscovererBifrost\Processors;

use Exception;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Discoverer\Service\Results\Field;
use SilverStripe\Discoverer\Service\Results\Suggestions;

class SuggestionsProcessor
{

    use Injectable;

    /**
     * @throws Exception
     */
    public function getProcessedSuggestions(Suggestions $suggestions, array $response): void
    {
        // Check that we have all critical fields in our Elastic response
        $this->validateResponse($response);

        $results = $response['results'] ?? [];

        foreach ($results as $result) {
            $suggestions->addSuggestion(Field::create(
                $result['raw'] ?? null,
                $result['snippet'] ?? null,
            ));
        }
    }

    private function validateResponse(array $response): void
    {
        // If any errors are present, then let's throw and track what they were
        if (array_key_exists('errors', $response)) {
            throw new Exception(sprintf('Elastic response contained errors: %s', json_encode($response['errors'])));
        }

        // The top level fields that we expect to receive from Elastic for each search
        $results = $response['results'] ?? null;

        // Specifically checking is_array(), because an empty results array is a valid response
        if (!is_array($results)) {
            throw new Exception('Missing required top level fields for query suggestions: results');
        }
    }

}
