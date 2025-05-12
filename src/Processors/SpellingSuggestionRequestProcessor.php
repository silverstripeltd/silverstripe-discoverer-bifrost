<?php

namespace SilverStripe\DiscovererBifrost\Processors;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Discoverer\Query\Suggestion;
use Silverstripe\Search\Client\Model\SpellingSuggestionRequest;

class SpellingSuggestionRequestProcessor
{

    use Injectable;

    public function getRequest(Suggestion $suggestion): SpellingSuggestionRequest
    {
        $request = new SpellingSuggestionRequest();
        $request->setQuery($suggestion->getQueryString());
        $request->setFormatted($suggestion->isFormatted());

        $limit = $suggestion->getLimit();
        $fields = $suggestion->getFields();

        if ($limit) {
            $request->setSize($limit);
        }

        if ($fields) {
            $request->setFields($fields);
        }

        return $request;
    }

}
