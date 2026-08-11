<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests;

use Elastic\EnterpriseSearch\AppSearch\Request\LogClickthrough as AppSearchLogClickthrough;
use Elastic\EnterpriseSearch\AppSearch\Schema\ClickParams;
use SilverStripe\Core\Injector\Injectable;

class ClickPost extends AppSearchLogClickthrough
{

    use Injectable;

    public function __construct(string $engineName, ?ClickParams $params = null)
    {
        parent::__construct($engineName, $params);

        $this->path = sprintf('/api/v1/%s/click', urlencode($engineName));

        // Bifröst accepts only the request and document IDs, so we narrow the body rather than sending the whole
        // ClickParams schema (which also carries a query string and tags). ClickParams types both IDs as non-nullable
        // strings without initialising them, so isset() is the only safe way to read them
        $body = [];

        if (isset($params->request_id)) {
            $body['request_id'] = $params->request_id;
        }

        if (isset($params->document_id)) {
            $body['document_id'] = $params->document_id;
        }

        $this->body = $body;
    }

}
