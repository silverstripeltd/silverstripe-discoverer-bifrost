<?php

namespace SilverStripe\DiscovererBifrost\Service\Adaptors;

use SilverStripe\Discoverer\Query\Suggestion;
use SilverStripe\Discoverer\Service\Interfaces\QuerySuggestionAdaptor as QuerySuggestionAdaptorInterface;
use SilverStripe\Discoverer\Service\Results\Suggestions;
use SilverStripe\Discoverer\Service\SearchService;
use SilverStripe\DiscovererBifrost\Processors\QuerySuggestionRequestProcessor;
use SilverStripe\DiscovererBifrost\Processors\QuerySuggestionsProcessor;
use Silverstripe\Search\Client\Exception\QuerySuggestionPostNotFoundException;
use Silverstripe\Search\Client\Exception\QuerySuggestionPostUnprocessableEntityException;
use Silverstripe\Search\Client\Exception\UnexpectedStatusCodeException;
use Throwable;

class QuerySuggestionAdaptor extends BaseAdaptor implements QuerySuggestionAdaptorInterface
{

    public function process(Suggestion $suggestion, string $indexSuffix): Suggestions
    {
        try {
            $request = QuerySuggestionRequestProcessor::singleton()->getRequest($suggestion);
            $response = $this->getClient()->querySuggestionPost(
                SearchService::singleton()->environmentizeIndex($indexSuffix),
                $request
            );

            $suggestions = Suggestions::create(200);
            QuerySuggestionsProcessor::singleton()->getProcessedSuggestions($suggestions, $response);
        } catch (QuerySuggestionPostNotFoundException | QuerySuggestionPostUnprocessableEntityException $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing the
            // client response to a 500)
            $this->getLogger()->warning(
                $e->getMessage(),
                [
                    'exception' => $e,
                    'responseBody' => (string) $e->getResponse()->getBody(),
                ]
            );

            $suggestions = Suggestions::create($e->getResponse()->getStatusCode());
        } catch (UnexpectedStatusCodeException $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing the
            // client response to a 500)
            $this->getLogger()->warning(
                $e->getMessage(),
                [
                    'exception' => $e,
                    'responseBody' => $e->getMessage(),
                ]
            );

            $suggestions = Suggestions::create($e->getCode());
        } catch (Throwable $e) {
            // Log the error without breaking the page ("warning" is the highest level we can log without changing the
            // client response to a 500)
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);
            $suggestions = Suggestions::create(500);
        } finally {
            return $suggestions;
        }
    }

}
