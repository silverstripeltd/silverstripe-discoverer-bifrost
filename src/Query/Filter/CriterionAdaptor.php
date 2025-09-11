<?php

namespace SilverStripe\DiscovererBifrost\Query\Filter;

use Exception;
use SilverStripe\Discoverer\Query\Filter\Criterion;
use SilverStripe\Discoverer\Query\Filter\CriterionAdaptor as CriterionAdaptorInterface;

class CriterionAdaptor implements CriterionAdaptorInterface
{

    private const array UNSUPPORTED_COMPARISONS = [
        Criterion::LESS_THAN,
        Criterion::GREATER_THAN,
        Criterion::IS_NULL,
        Criterion::IS_NOT_NULL,
    ];

    /**
     * @throws Exception
     */
    public function prepareCriterion(Criterion $criterion): array
    {
        $comparison = $criterion->getComparison();

        if (in_array($comparison, self::UNSUPPORTED_COMPARISONS, true)) {
            throw new Exception(sprintf('Unsupported Bifröst comparison "%s"', $comparison));
        }

        switch ($comparison) {
            case Criterion::RANGE:
                $value = $criterion->getValue();
                $from = $value['from'] ?? null;
                $to = $value['to'] ?? null;
                $range = [];

                if ($from) {
                    $range['from'] = $from;
                }

                if ($to) {
                    $range['to'] = $to;
                }

                if (!$range) {
                    throw new Exception('Range comparison $value must contain one (or both) of keys "from" and "to"');
                }

                return [
                    $criterion->getTarget() => $range,
                ];

            case Criterion::GREATER_EQUAL:
                return [
                    $criterion->getTarget() => [
                        'from' => $criterion->getValue(),
                    ],
                ];

            case Criterion::LESS_EQUAL:
                return [
                    $criterion->getTarget() => [
                        'to' => $criterion->getValue(),
                    ],
                ];

            default:
                return [
                    $criterion->getTarget() => $criterion->getValue(),
                ];
        }
    }

}
