<?php

namespace SilverStripe\DiscovererBifrost\Service\Requests\Params;

use SilverStripe\Core\Injector\Injectable;

/**
 * The Elastic requests that we use turn properties into arguments for the request, so we are stuck with this paradigm
 * for now
 *
 * @phpcs:disable SlevomatCodingStandard.Classes.ForbiddenPublicProperty.ForbiddenPublicProperty
 */
class SuggestionParams
{

    use Injectable;

    public ?string $query = null;

    public array $fields = [];

    public ?int $size = null;

    public bool $formatted = false;

}
