<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests;

use Elastic\EnterpriseSearch\Request\Request;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\DiscovererBifrost\Service\Requests\Params\SuggestionParams;

class SpellingSuggestion extends Request
{

    use Injectable;

    public function __construct(string $engineName, SuggestionParams $params)
    {
        $this->method = 'POST';
        $this->path = sprintf('/api/v1/%s/spelling_suggestion', $engineName);
        $this->headers['Content-Type'] = 'application/json';
        $this->body = $params;
    }

}
