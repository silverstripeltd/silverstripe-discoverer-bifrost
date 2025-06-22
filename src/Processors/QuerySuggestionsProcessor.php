<?php

namespace SilverStripe\DiscovererBifrost\Processors;

use Exception;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Discoverer\Service\Results\Field;
use SilverStripe\Discoverer\Service\Results\Suggestions;
use Silverstripe\Search\Client\Model\QuerySuggestionResponse;

class QuerySuggestionsProcessor
{

    use Injectable;

    /**
     * @throws Exception
     */
    public function getProcessedSuggestions(Suggestions $suggestions, QuerySuggestionResponse $response): void
    {
        $results = $response->getResults() ?? [];

        foreach ($results as $result) {
            $suggestions->addSuggestion(Field::create(
                $result->getRaw() ?? null,
            ));
        }
    }

}
