<?php

namespace SilverStripe\DiscovererBifrost\Processors;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Discoverer\Query\Suggestion;
use SilverStripe\DiscovererBifrost\Service\Requests\Params\SuggestionParams;

class SuggestionParamsProcessor
{

    use Injectable;

    public function getQueryParams(Suggestion $suggestion): SuggestionParams
    {
        $suggestionParams = SuggestionParams::create();
        $suggestionParams->query = $suggestion->getQueryString();

        $limit = $suggestion->getLimit();
        $fields = $suggestion->getFields();

        if ($limit) {
            $suggestionParams->size = $limit;
        }

        if ($fields) {
            $suggestionParams->fields = $fields;
        }

        return $suggestionParams;
    }

}
