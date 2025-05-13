<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\DiscovererBifrost\Service\Requests\Params\SuggestionParams;

class QuerySuggestion
{

    use Injectable;

    public function __construct(string $engineName, SuggestionParams $params)
    {
        $this->method = 'POST';
        $this->path = sprintf('/api/v1/%s/query_suggestion', $engineName);
        $this->headers['Content-Type'] = 'application/json';
        $this->body = $params;
    }

}
