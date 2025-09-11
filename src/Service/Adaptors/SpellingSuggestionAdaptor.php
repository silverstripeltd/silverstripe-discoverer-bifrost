<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use SilverStripe\Discoverer\Query\Suggestion;
use SilverStripe\Discoverer\Service\Interfaces\SpellingSuggestionAdaptor as SpellingSuggestionAdaptorInterface;
use SilverStripe\Discoverer\Service\Results\Suggestions;
use SilverStripe\Discoverer\Service\SearchService;
use SilverStripe\DiscovererBifrost\Processors\SpellingSuggestionRequestProcessor;
use SilverStripe\DiscovererBifrost\Processors\SpellingSuggestionsProcessor;
use Silverstripe\Search\Client\Exception\SpellingSuggestionPostNotFoundException;
use Silverstripe\Search\Client\Exception\SpellingSuggestionPostUnprocessableEntityException;
use Silverstripe\Search\Client\Exception\UnexpectedStatusCodeException;
use Throwable;

class SpellingSuggestionAdaptor extends BaseAdaptor implements SpellingSuggestionAdaptorInterface
{

    public function process(Suggestion $suggestion, string $indexSuffix): Suggestions
    {
        try {
            $request = SpellingSuggestionRequestProcessor::singleton()->getRequest($suggestion);
            $response = $this->getClient()->spellingSuggestionPost(
                SearchService::singleton()->environmentizeIndex($indexSuffix),
                $request
            );

            $suggestions = Suggestions::create(200);
            SpellingSuggestionsProcessor::singleton()->getProcessedSuggestions($suggestions, $response);
        } catch (SpellingSuggestionPostNotFoundException | SpellingSuggestionPostUnprocessableEntityException $e) {
            $this->getLogger()->warning(
                $e->getMessage(),
                [
                    'exception' => $e,
                    'responseBody' => (string) $e->getResponse()->getBody(),
                ]
            );
            $suggestions = Suggestions::create($e->getResponse()->getStatusCode());
        } catch (UnexpectedStatusCodeException $e) {
            $this->getLogger()->warning(
                $e->getMessage(),
                [
                    'exception' => $e,
                    'responseBody' => $e->getMessage(),
                ]
            );
            $suggestions = Suggestions::create($e->getCode());
        } catch (Throwable $e) {
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);
            $suggestions = Suggestions::create(500);
        } finally {
            return $suggestions;
        }
    }

}
