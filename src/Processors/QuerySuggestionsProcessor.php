<?php

namespace SilverStripe\DiscovererBifrost\Processors;

use Exception;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Discoverer\Service\Results\Field;
use SilverStripe\Discoverer\Service\Results\Suggestions;
use stdClass;

class QuerySuggestionsProcessor
{

    use Injectable;

    /**
     * @throws Exception
     */
    public function getProcessedSuggestions(Suggestions $suggestions, stdClass $response): void
    {
        $results = $response->results ?? [];

        foreach ($results as $result) {
            $suggestions->addSuggestion(Field::create(
                $result->raw ?? null,
            ));
        }
    }

}
