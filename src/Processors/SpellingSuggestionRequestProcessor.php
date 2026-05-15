<?php

namespace SilverStripe\DiscovererBifrost\Processors;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Discoverer\Query\Suggestion;
use Silverstripe\Search\Client\Request\Search\SpellingSuggestionRequest;

class SpellingSuggestionRequestProcessor
{

    use Injectable;

    public function getRequest(Suggestion $suggestion): SpellingSuggestionRequest
    {
        $fields = $suggestion->getFields() ?? [];
        $request = new SpellingSuggestionRequest($suggestion->getQueryString(), $fields);
        $request->setFormatted($suggestion->isFormatted());

        $limit = $suggestion->getLimit();

        if ($limit) {
            $request->setSize($limit);
        }

        return $request;
    }

}
