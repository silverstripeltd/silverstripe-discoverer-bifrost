<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests;

use Elastic\EnterpriseSearch\AppSearch\Request\QuerySuggestion as AppSearchQuerySuggestion;
use Elastic\EnterpriseSearch\AppSearch\Schema\QuerySuggestionRequest;

class QuerySuggestion extends AppSearchQuerySuggestion
{

    public function __construct(string $engineName, ?QuerySuggestionRequest $query_suggestion_request = null)
    {
        parent::__construct($engineName, $query_suggestion_request);

        $this->path = sprintf('/api/v1/%s/query_suggestion', $engineName);
    }

}
