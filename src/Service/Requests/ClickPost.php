<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests;

use Elastic\EnterpriseSearch\Request\Request;
use SilverStripe\Core\Injector\Injectable;

/**
 * Bifröst expects only the request and document IDs, so we build the body ourselves rather than extending Elastic's
 * LogClickthrough (whose ClickParams schema also carries a query string and tags)
 */
class ClickPost extends Request
{

    use Injectable;

    public function __construct(string $engineName, string $requestId, string $documentId)
    {
        $this->method = 'POST';
        $this->path = sprintf('/api/v1/%s/click', $engineName);
        $this->headers['Content-Type'] = 'application/json';
        $this->body = [
            'request_id' => $requestId,
            'document_id' => $documentId,
        ];
    }

}
