<?php

namespace SilverStripe\DiscovererBifrost\Query\Facet;

use SilverStripe\Discoverer\Query\Facet\Facet;
use SilverStripe\Discoverer\Query\Facet\FacetAdaptor as FacetAdaptorInterface;
use SilverStripe\Discoverer\Query\Facet\FacetCollection;
use Silverstripe\Search\Client\Model\Search\FacetRange;
use Silverstripe\Search\Client\Model\Search\FacetRangeObject;
use Silverstripe\Search\Client\Model\Search\FacetValue;

class FacetAdaptor implements FacetAdaptorInterface
{

    public function prepareFacets(FacetCollection $facetCollection): mixed
    {
        $facets = [];

        foreach ($facetCollection->getFacets() as $facet) {
            $fieldName = $facet->getFieldName();

            if (!isset($facets[$fieldName])) {
                $facets[$fieldName] = [];
            }

            $facets[$fieldName][] = $this->prepareFacet($facet);
        }

        return $facets;
    }

    private function prepareFacet(Facet $facet): FacetValue|FacetRange
    {
        if ($facet->getType() === Facet::TYPE_VALUE) {
            $preparedFacet = new FacetValue();

            if ($facet->getName()) {
                $preparedFacet->setName($facet->getName());
            }

            if ($facet->getLimit()) {
                $preparedFacet->setSize($facet->getLimit());
            }

            return $preparedFacet;
        }

        $ranges = $this->prepareRanges($facet);
        $preparedFacet = new FacetRange($ranges ?? []);

        if ($facet->getName()) {
            $preparedFacet->setName($facet->getName());
        }

        return $preparedFacet;
    }

    /**
     * @return FacetRangeObject[]|null
     */
    private function prepareRanges(Facet $facet): ?array
    {
        if (!$facet->getRanges()) {
            return null;
        }

        $ranges = [];

        foreach ($facet->getRanges() as $range) {
            $from = $range->getFrom();
            $to = $range->getTo();
            $name = $range->getName();

            if ($from === null && $to === null && $name === null) {
                continue;
            }

            $ranges[] = new FacetRangeObject($from, $to, $name);
        }

        if (count($ranges) === 0) {
            return null;
        }

        return $ranges;
    }

}
