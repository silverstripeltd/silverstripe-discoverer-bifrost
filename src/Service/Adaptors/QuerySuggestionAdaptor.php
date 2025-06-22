<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use SilverStripe\Discoverer\Query\Suggestion;
use SilverStripe\Discoverer\Service\Interfaces\QuerySuggestionAdaptor as QuerySuggestionAdaptorInterface;
use SilverStripe\Discoverer\Service\Results\Suggestions;
use SilverStripe\DiscovererBifrost\Processors\QuerySuggestionRequestProcessor;
use SilverStripe\DiscovererBifrost\Processors\QuerySuggestionsProcessor;
use Silverstripe\Search\Client\Exception\QuerySuggestionPostUnprocessableEntityException;
use Throwable;

class QuerySuggestionAdaptor extends BaseAdaptor implements QuerySuggestionAdaptorInterface
{

    public function process(Suggestion $suggestion, string $indexName): Suggestions
    {
        // Instantiate our Suggestions class with empty data. This will still be returned if there is an Exception
        // during communication with Bifröst (so that the page doesn't seriously break)
        $suggestions = Suggestions::create();

        try {
            $engine = $this->environmentizeIndex($indexName);
            $request = QuerySuggestionRequestProcessor::singleton()->getRequest($suggestion);
            $response = $this->getClient()->querySuggestionPost($engine, $request);

            QuerySuggestionsProcessor::singleton()->getProcessedSuggestions($suggestions, $response);
            // If we got this far, then the request was a success
            $suggestions->setSuccess(true);
        } catch (QuerySuggestionPostUnprocessableEntityException $e) {
            // Log the error without breaking the page
            $this->getLogger()->error(
                sprintf((string) $e->getResponse()->getBody(), $e->getMessage()),
                ['bifrost' => $e]
            );
            // Our request was not a success
            $suggestions->setSuccess(false);
        } catch (Throwable $e) {
            // Log the error without breaking the page
            $this->getLogger()->error(sprintf('Bifrost error: %s', $e->getMessage()), ['bifrost' => $e]);
            // Our request was not a success
            $suggestions->setSuccess(false);
        } finally {
            return $suggestions;
        }
    }

}
