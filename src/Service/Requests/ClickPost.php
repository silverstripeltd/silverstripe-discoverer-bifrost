<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests;

use Elastic\EnterpriseSearch\AppSearch\Request\LogClickthrough as AppSearchLogClickthrough;
use Elastic\EnterpriseSearch\AppSearch\Schema\ClickParams;

/**
 * @deprecated 3.0
 */
class ClickPost extends AppSearchLogClickthrough
{

    public function __construct(string $engineName, ?ClickParams $params = null)
    {
        parent::__construct($engineName, $params);

        $this->path = sprintf('/api/v1/%s/click', $engineName);
    }

}
