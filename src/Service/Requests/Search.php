<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests;

use Elastic\EnterpriseSearch\AppSearch\Request\Search as AppSearchSearch;
use Elastic\EnterpriseSearch\AppSearch\Schema\SearchRequestParams;

class Search extends AppSearchSearch
{

    public function __construct(string $engineName, ?SearchRequestParams $params = null)
    {
        parent::__construct($engineName, $params);

        $this->path = sprintf('/api/v1/%s/search', $engineName);
    }

}
