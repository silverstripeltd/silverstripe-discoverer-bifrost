<?php

namespace SilverStripe\DiscovererBifrost\Query\Boost;

use InvalidArgumentException;
use SilverStripe\Discoverer\Query\Boost\Boost;
use SilverStripe\Discoverer\Query\Boost\BoostAdaptor as BoostAdaptorInterface;
use SilverStripe\Discoverer\Query\Boost\BoostCollection;
use SilverStripe\Discoverer\Query\Boost\ProximityBoost;
use SilverStripe\Discoverer\Query\Boost\ValueBoost;
use Silverstripe\Search\Client\Model\Field\Boost as ApiBoost;

/**
 * Translates Discoverer query-time boosts into Silverstripe Search Service boosts.
 *
 * Registered through the Injector against the Discoverer `BoostAdaptor` interface, so a project can replace it.
 */
class BoostAdaptor implements BoostAdaptorInterface
{

    /**
     * The lowest boost factor the Search Service accepts.
     */
    public const float MIN_FACTOR = 0.0;

    /**
     * The highest boost factor the Search Service accepts.
     */
    public const float MAX_FACTOR = 10.0;

    /**
     * Translate the boosts in a collection into Search Service boosts, grouped by field name.
     *
     * @param BoostCollection $boostCollection the boosts to translate, keyed by field name
     * @return array<int|string, ApiBoost[]> - the translated boosts, empty when the collection has none; PHP turns a
     *     numeric field name into an `int` key, so callers cast keys back to strings
     * @throws InvalidArgumentException When a factor is out of range or a boost cannot be translated
     */
    public function prepareBoosts(BoostCollection $boostCollection): mixed
    {
        $boosts = [];

        foreach ($boostCollection->getBoosts() as $fieldName => $fieldBoosts) {
            foreach ($fieldBoosts as $boost) {
                $boosts[$fieldName][] = $this->prepareBoost((string) $fieldName, $boost);
            }
        }

        return $boosts;
    }

    /**
     * Build a single Search Service boost from a Discoverer boost.
     *
     * @param string $fieldName the field being boosted, used in error messages
     * @param Boost $boost the Discoverer boost to translate
     * @return ApiBoost - the boost in the form the Search Service expects
     * @throws InvalidArgumentException When the factor is out of range or the boost type is unknown
     */
    private function prepareBoost(string $fieldName, Boost $boost): ApiBoost
    {
        $factor = $boost->getFactor();

        // Throw rather than clamp, as clamping would silently change ranking and hide a configuration mistake;
        // the negated range check also rejects `NAN`, which fails every comparison
        if ($factor !== null && !($factor >= self::MIN_FACTOR && $factor <= self::MAX_FACTOR)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Boost factor for field "%s" must be between 0 and 10, got %s',
                    $fieldName,
                    is_finite($factor) ? $this->formatFloat($factor) : (string) $factor
                )
            );
        }

        if ($boost instanceof ValueBoost) {
            $apiBoost = new ApiBoost('value');
            $apiBoost->setValue(array_map($this->formatScalar(...), $boost->getValues()));
        } elseif ($boost instanceof ProximityBoost) {
            $apiBoost = new ApiBoost('proximity');
            $apiBoost->setCenter($this->formatScalar($boost->getCenter()));
            $apiBoost->setFunction($this->getApiFunction($boost->getFunction()));
        } else {
            throw new InvalidArgumentException(
                sprintf('Unsupported boost type %s for field "%s"', get_debug_type($boost), $fieldName)
            );
        }

        // Leaving the factor unset keeps the client's default
        if ($factor !== null) {
            $apiBoost->setFactor($factor);
        }

        // `operation` stays unset, as it is not in the Search Service boost spec and the service ignores it
        return $apiBoost;
    }

    /**
     * Map a Discoverer proximity function constant to the string the Search Service API expects.
     *
     * @param string $function a `ProximityBoost::FUNCTION_` constant
     * @return string - the API boost function
     * @throws InvalidArgumentException When the function is not one the Search Service supports
     */
    private function getApiFunction(string $function): string
    {
        return match ($function) {
            ProximityBoost::FUNCTION_GAUSSIAN => 'gaussian',
            ProximityBoost::FUNCTION_EXPONENTIAL => 'exponential',
            ProximityBoost::FUNCTION_LINEAR => 'linear',
            default => throw new InvalidArgumentException(sprintf('Unknown boost function "%s"', $function)),
        };
    }

    /**
     * Convert a boost value or centre to the string the API expects, formatting floats as plain decimals.
     *
     * @param string|int|float $value the value to convert
     * @return string - the value as a string
     * @throws InvalidArgumentException When a float is not finite
     */
    private function formatScalar(string|int|float $value): string
    {
        return is_float($value) ? $this->formatFloat($value) : (string) $value;
    }

    /**
     * Format a float as a plain decimal string: no exponent, shortest round-trip form, no trailing zeros.
     *
     * @param float $value the float to format
     * @return string - the plain decimal representation
     * @throws InvalidArgumentException When the float is not finite
     */
    private function formatFloat(float $value): string
    {
        if (!is_finite($value)) {
            throw new InvalidArgumentException('Boost numbers must be finite');
        }

        // `var_export()` gives the shortest round-trip digits and ignores the locale, but may use an exponent
        $repr = var_export($value, true);

        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?E([+-]\d+)$/i', $repr, $m) === 1) {
            $digits = $m[2] . $m[3];
            $pointAt = strlen($m[2]) + (int) $m[4];

            if ($pointAt <= 0) {
                $repr = $m[1] . '0.' . str_repeat('0', -$pointAt) . $digits;
            } elseif ($pointAt >= strlen($digits)) {
                $repr = $m[1] . $digits . str_repeat('0', $pointAt - strlen($digits));
            } else {
                $repr = $m[1] . substr($digits, 0, $pointAt) . '.' . substr($digits, $pointAt);
            }
        }

        if (str_contains($repr, '.')) {
            $repr = rtrim(rtrim($repr, '0'), '.');
        }

        return $repr === '-0' ? '0' : $repr;
    }

}
