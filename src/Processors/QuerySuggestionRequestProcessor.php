<?php

namespace SilverStripe\DiscovererBifrost\Processors;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Discoverer\Query\Suggestion;
use Silverstripe\Search\Client\Model\QuerySuggestionRequest;

class QuerySuggestionRequestProcessor
{

    use Injectable;

    public function getRequest(Suggestion $suggestion): QuerySuggestionRequest
    {
        $request = new QuerySuggestionRequest();
        $request->setQuery($suggestion->getQueryString());

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
