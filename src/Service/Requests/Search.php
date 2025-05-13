<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests;

class Search
{

    public function __construct(string $engineName, $params = null)
    {
        $this->path = sprintf('/api/v1/%s/search', $engineName);
    }

}
