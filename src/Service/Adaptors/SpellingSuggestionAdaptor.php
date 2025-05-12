<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use SilverStripe\Discoverer\Query\Suggestion;
use SilverStripe\Discoverer\Service\Interfaces\SpellingSuggestionAdaptor as SpellingSuggestionAdaptorInterface;
use SilverStripe\Discoverer\Service\Results\Suggestions;
use SilverStripe\Discoverer\Service\SearchService;
use SilverStripe\DiscovererBifrost\Processors\SpellingSuggestionRequestProcessor;
use SilverStripe\DiscovererBifrost\Processors\SpellingSuggestionsProcessor;
use Silverstripe\Search\Client\Exception\SpellingSuggestionPostUnprocessableEntityException;
use Throwable;

class SpellingSuggestionAdaptor extends BaseAdaptor implements SpellingSuggestionAdaptorInterface
{

    public function process(Suggestion $suggestion, string $indexSuffix): Suggestions
    {
        // Instantiate our Suggestions class with empty data. This will still be returned if there is an Exception
        // during communication with Bifröst (so that the page doesn't seriously break)
        $suggestions = Suggestions::create();

        try {
            $request = SpellingSuggestionRequestProcessor::singleton()->getRequest($suggestion);
            $response = $this->getClient()->spellingSuggestionPost(
                SearchService::singleton()->environmentizeIndex($indexSuffix),
                $request
            );

            SpellingSuggestionsProcessor::singleton()->getProcessedSuggestions($suggestions, $response);
            // If we got this far, then the request was a success
            $suggestions->setSuccess(true);
        } catch (SpellingSuggestionPostUnprocessableEntityException $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing the
            // client response to a 500)
            $this->getLogger()->warning(
                $e->getMessage(),
                [
                    'exception' => $e,
                    'responseBody' => (string) $e->getResponse()->getBody(),
                ]
            );
            // Our request was not a success
            $suggestions->setSuccess(false);
        } catch (Throwable $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing the
            // client response to a 500)
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);
            // Our request was not a success
            $suggestions->setSuccess(false);
        } finally {
            return $suggestions;
        }
    }

}
