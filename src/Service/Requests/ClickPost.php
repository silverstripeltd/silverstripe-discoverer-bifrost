<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests;

use Elastic\EnterpriseSearch\AppSearch\Request\LogClickthrough as AppSearchLogClickthrough;
use Elastic\EnterpriseSearch\AppSearch\Schema\ClickParams;

class ClickPost extends AppSearchLogClickthrough
{

    public function __construct(string $engineName, ?ClickParams $click_params = null)
    {
        parent::__construct($engineName, $click_params);

        $this->path = sprintf('/api/v1/%s/click', $engineName);
    }

}
