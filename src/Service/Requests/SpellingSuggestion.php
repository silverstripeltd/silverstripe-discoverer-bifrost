<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests;

use SilverStripe\Core\Injector\Injectable;

class SpellingSuggestion
{

    use Injectable;

    public function __construct(string $engineName, $params)
    {
        $this->method = 'POST';
        $this->path = sprintf('/api/v1/%s/spelling_suggestion', $engineName);
        $this->headers['Content-Type'] = 'application/json';
        $this->body = $params;
    }

}
