<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests\Params;

use SilverStripe\Core\Injector\Injectable;

class SuggestionParams
{

    use Injectable;

    public ?string $query = null;

    public array $fields = [];

    public ?int $size = null;

    public bool $formatted = false;

}
