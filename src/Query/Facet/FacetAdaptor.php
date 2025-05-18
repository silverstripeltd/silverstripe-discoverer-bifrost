<?php

namespace SilverStripe\DiscovererBifrost\Query\Facet;

use ArrayObject;
use SilverStripe\Discoverer\Query\Facet\Facet;
use SilverStripe\Discoverer\Query\Facet\FacetAdaptor as FacetAdaptorInterface;
use SilverStripe\Discoverer\Query\Facet\FacetCollection;

class FacetAdaptor implements FacetAdaptorInterface
{

    public const string TYPE_VALUE = 'value';
    public const string TYPE_RANGE = 'range';

    private const array TYPE_CONVERSION = [
        Facet::TYPE_VALUE => self::TYPE_VALUE,
        Facet::TYPE_RANGE => self::TYPE_RANGE,
    ];

    public function prepareFacets(FacetCollection $facetCollection): mixed
    {
        $facets = new ArrayObject();

        foreach ($facetCollection->getFacets() as $facet) {
            $fieldName = $facet->getFieldName();

            if (!isset($facets[$fieldName])) {
                $facets[$fieldName] = [];
            }

            $facets[$fieldName][] = $this->prepareFacet($facet);
        }

        return $facets;
    }

    private function prepareFacet(Facet $facet): ArrayObject
    {
        $preparedFacet = new ArrayObject();
        $preparedFacet['type'] = self::TYPE_CONVERSION[$facet->getType()];

        if ($facet->getName()) {
            $preparedFacet['name'] = $facet->getName();
        }

        if ($facet->getType() === Facet::TYPE_VALUE) {
            if ($facet->getLimit()) {
                $preparedFacet['size'] = $facet->getLimit();
            }

            return $preparedFacet;
        }

        $ranges = $this->prepareRanges($facet);

        if ($ranges) {
            $preparedFacet['ranges'] = $ranges;
        }

        return $preparedFacet;
    }

    private function prepareRanges(Facet $facet): ?array
    {
        if (!$facet->getRanges()) {
            return null;
        }

        $ranges = [];

        foreach ($facet->getRanges() as $range) {
            $preparedRange = [];
            $from = $range->getFrom();
            $to = $range->getTo();
            $name = $range->getName();

            if ($from) {
                $preparedRange['from'] = $from;
            }

            if ($to) {
                $preparedRange['to'] = $to;
            }

            if ($name) {
                $preparedRange['name'] = $name;
            }

            if (count($preparedRange) === 0) {
                continue;
            }

            $ranges[] = $preparedRange;
        }

        if (count($ranges) === 0) {
            return null;
        }

        return $ranges;
    }

}
