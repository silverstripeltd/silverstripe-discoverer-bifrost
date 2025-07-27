<?php

namespace SilverStripe\DiscovererBifrost\Tests\Processors;

use ReflectionMethod;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Discoverer\Analytics\AnalyticsData;
use SilverStripe\Discoverer\Analytics\AnalyticsMiddleware;
use SilverStripe\Discoverer\Query\Query;
use SilverStripe\Discoverer\Service\Results\Field;
use SilverStripe\Discoverer\Service\Results\Record;
use SilverStripe\Discoverer\Service\Results\Results;
use SilverStripe\DiscovererBifrost\Processors\SearchResultsProcessor;
use stdClass;

class ResultsProcessorTest extends SapphireTest
{

    public function testValidateResponseTest(): void
    {
        // The only assertion we are making in this test is that no Exceptions are thrown when we invoke our method
        $this->expectNotToPerformAssertions();

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::validateResponse() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'validateResponse');
        $reflectionMethod->setAccessible(true);

        $reflectionMethod->invoke($resultsProcessor, $this->getResponseWithRecords());
    }

    public function testValidateResponseNoMeta(): void
    {
        $this->expectExceptionMessage('Missing required top level fields: meta');

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::validateResponse() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'validateResponse');
        $reflectionMethod->setAccessible(true);

        $response = $this->getResponseWithRecords();
        unset($response->meta);

        $reflectionMethod->invoke($resultsProcessor, $response);
    }

    public function testValidateResponseNoRecords(): void
    {
        $this->expectExceptionMessage('Missing required top level fields: results');

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::validateResponse() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'validateResponse');
        $reflectionMethod->setAccessible(true);

        $response = $this->getResponseWithRecords();
        unset($response->results);

        $reflectionMethod->invoke($resultsProcessor, $response);
    }

    public function testValidateResponseNoMetaAndRecords(): void
    {
        $this->expectExceptionMessage('Missing required top level fields: meta, results');

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::validateResponse() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'validateResponse');
        $reflectionMethod->setAccessible(true);

        $response = $this->getResponseWithRecords();
        unset($response->meta);
        unset($response->results);

        $reflectionMethod->invoke($resultsProcessor, $response);
    }

    public function testValidateResponseNoRequestId(): void
    {
        $this->expectExceptionMessage('Expected value for meta.request_id');

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::validateResponse() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'validateResponse');
        $reflectionMethod->setAccessible(true);

        $response = $this->getResponseWithRecords();
        unset($response->meta->request_id);

        $reflectionMethod->invoke($resultsProcessor, $response);
    }

    public function testValidateResponseNoEngine(): void
    {
        $this->expectExceptionMessage('Expected value for meta.engine.name');

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::validateResponse() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'validateResponse');
        $reflectionMethod->setAccessible(true);

        $response = $this->getResponseWithRecords();
        unset($response->meta->engine);

        $reflectionMethod->invoke($resultsProcessor, $response);
    }

    public function testValidateResponseNoEngineName(): void
    {
        $this->expectExceptionMessage('Expected value for meta.engine.name');

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::validateResponse() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'validateResponse');
        $reflectionMethod->setAccessible(true);

        $response = $this->getResponseWithRecords();
        unset($response->meta->engine->name);

        $reflectionMethod->invoke($resultsProcessor, $response);
    }

    public function testValidateResponseNoPage(): void
    {
        $this->expectExceptionMessage('Missing structure for meta.page in Bifröst search response');

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::validateResponse() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'validateResponse');
        $reflectionMethod->setAccessible(true);

        $response = $this->getResponseWithRecords();
        unset($response->meta->page);

        $reflectionMethod->invoke($resultsProcessor, $response);
    }

    public function testValidateResponseNoPagination(): void
    {
        $this->expectExceptionMessage('Missing required pagination fields: current, size, total_pages, total_results');

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::validateResponse() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'validateResponse');
        $reflectionMethod->setAccessible(true);

        $response = $this->getResponseWithRecords();
        // Remove all the pagination fields
        $response->meta->page = new stdClass();

        $reflectionMethod->invoke($resultsProcessor, $response);
    }

    public function testProcessMetaData(): void
    {
        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::processMetaData() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'processMetaData');
        $reflectionMethod->setAccessible(true);

        // Empty Results object
        $results = Results::create(200, Query::create());

        // This should hydrate our Results object
        $reflectionMethod->invoke($resultsProcessor, $results, $this->getResponseWithRecords());

        $this->assertEquals(1, $results->getRecords()->CurrentPage());
        $this->assertEquals(10, $results->getRecords()->getPageLength());
        $this->assertEquals(10, $results->getRecords()->TotalPages());
        $this->assertEquals(100, $results->getRecords()->getTotalItems());
    }

    public function testProcessRecords(): void
    {
        // Make sure Analytics is disabled
        Environment::setEnv(AnalyticsMiddleware::ENV_ANALYTICS_ENABLED, false);
        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::processRecords() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'processRecords');
        $reflectionMethod->setAccessible(true);

        // Empty Results object
        $results = Results::create(200, Query::create());

        // This should hydrate our Results object with 2 records
        $reflectionMethod->invoke($resultsProcessor, $results, $this->getResponseWithRecords(2));

        $this->assertEquals(2, $results->getRecords()->getTotalItems());

        /** @var Record $record */
        $record = $results->getRecords()->getList()->first();

        // Start testing that the snake_case fields from Bifröst are converted to Silverstripe PascalCase equivalents,
        // and that we have our expected Raw and Snippet values
        $this->assertTrue($record->hasDynamicData('Title'));
        $this->assertTrue($record->hasDynamicData('Description'));
        $this->assertTrue($record->hasDynamicData('Id'));
        $this->assertTrue($record->hasDynamicData('RecordId'));
        $this->assertTrue($record->hasDynamicData('SourceClass'));

        /** @var Field $title */
        $title = $record->Title;
        /** @var Field $description */
        $description = $record->Description;
        /** @var Field $id */
        $id = $record->Id;
        /** @var Field $recordId */
        $recordId = $record->RecordId;
        /** @var Field $sourceClass */
        $sourceClass = $record->SourceClass;

        // Analytics is not turned on during this test
        $this->assertNull($record->getAnalyticsData());
        // Start checking our fields are as expected
        $this->assertEquals('Search term highlighted in title: Record 1', $title->getRaw());
        $this->assertEquals('<em>Search</em> <em>term</em> highlighted in title: Record 1', $title->getFormatted());
        $this->assertEquals('<em>Search</em> <em>term</em> highlighted in title: Record 1', $title->forTemplate());
        $this->assertEquals('Search term highlighted in description: Record 1', $description->getRaw());
        $this->assertEquals(
            '<em>Search</em> <em>term</em> highlighted in description: Record 1',
            $description->getFormatted()
        );
        $this->assertEquals(
            '<em>Search</em> <em>term</em> highlighted in description: Record 1',
            $description->forTemplate()
        );
        $this->assertEquals('app_pages_blockpage_1', $id->getRaw());
        $this->assertNull($id->getFormatted());
        $this->assertEquals('app_pages_blockpage_1', $id->forTemplate());
        $this->assertEquals('1', $recordId->getRaw());
        $this->assertNull($recordId->getFormatted());
        $this->assertEquals('1', $recordId->forTemplate());
        $this->assertEquals('App\\Pages\\BlockPage', $sourceClass->getRaw());
        $this->assertNull($recordId->getFormatted());
        $this->assertEquals('App\\Pages\\BlockPage', $sourceClass->forTemplate());
    }

    public function testProcessRecordsWithAnalytics(): void
    {
        // Make sure Analytics is enabled
        Environment::setEnv(AnalyticsMiddleware::ENV_ANALYTICS_ENABLED, true);
        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::processRecords() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'processRecords');
        $reflectionMethod->setAccessible(true);

        // Empty Results object
        $results = Results::create(200, Query::create('query string'));

        // This should hydrate our Results object with 2 records
        $reflectionMethod->invoke($resultsProcessor, $results, $this->getResponseWithRecords(2));

        $this->assertEquals(2, $results->getRecords()->getTotalItems());

        /** @var Record $record */
        $record = $results->getRecords()->getList()->first();
        $analyticsData = $record->getAnalyticsData();

        // Analytics is not turned on during this test
        $this->assertInstanceOf(AnalyticsData::class, $analyticsData);
        $this->assertEquals('query string', $analyticsData->getQueryString());
        $this->assertEquals('bifrost-main', $analyticsData->getIndexName());
        $this->assertEquals('app_pages_blockpage_1', $analyticsData->getDocumentId());
        $this->assertEquals('123abc', $analyticsData->getRequestId());
    }

    public function testProcessRecordsNoRequestId(): void
    {
        $this->expectExceptionMessage('Expected values for: meta.request_id');

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::processRecords() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'processRecords');
        $reflectionMethod->setAccessible(true);

        // Empty Results object
        $results = Results::create(200, Query::create());

        $response = $this->getResponseWithRecords();
        unset($response->meta->request_id);

        $reflectionMethod->invoke($resultsProcessor, $results, $response);
    }

    public function testProcessRecordsNoEngineName(): void
    {
        $this->expectExceptionMessage('Expected values for: meta.engine.name');

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::processRecords() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'processRecords');
        $reflectionMethod->setAccessible(true);

        // Empty Results object
        $results = Results::create(200, Query::create());

        $response = $this->getResponseWithRecords();
        unset($response->meta->engine->name);

        $reflectionMethod->invoke($resultsProcessor, $results, $response);
    }

    public function testProcessRecordsMissingRequiredFields(): void
    {
        $this->expectExceptionMessage('Expected values for: meta.request_id, meta.engine.name');

        $resultsProcessor = SearchResultsProcessor::singleton();

        /** @see SearchResultsProcessor::processRecords() */
        $reflectionMethod = new ReflectionMethod($resultsProcessor, 'processRecords');
        $reflectionMethod->setAccessible(true);

        // Empty Results object
        $results = Results::create(200, Query::create());

        $response = $this->getResponseWithRecords();
        unset($response->meta->request_id);
        unset($response->meta->engine->name);

        $reflectionMethod->invoke($resultsProcessor, $results, $response);
    }

    private function getResponseWithRecords(int $numRecords = 1): stdClass
    {
        $records = [];

        for ($i = 1; $i <= $numRecords; $i++) {
            $records[] = [
                'title' => [
                    'raw' => sprintf('Search term highlighted in title: Record %s', $i),
                    'snippet' => sprintf('<em>Search</em> <em>term</em> highlighted in title: Record %s', $i),
                ],
                'description' => [
                    'raw' => sprintf('Search term highlighted in description: Record %s', $i),
                    'snippet' => sprintf('<em>Search</em> <em>term</em> highlighted in description: Record %s', $i),
                ],
                'record_id' => [
                    'raw' => sprintf('%s', $i),
                ],
                'source_class' => [
                    'raw' => 'App\\Pages\\BlockPage',
                ],
                'id' => [
                    'raw' => sprintf('app_pages_blockpage_%s', $i),
                ],
            ];
        }

        $response = [
            'meta' => [
                'alerts' => [],
                'warnings' => [],
                'precision' => 2,
                'engine' => [
                    'name' => 'bifrost-main',
                    'type' => 'default',
                ],
                'page' => [
                    'current' => 1,
                    'total_pages' => 10,
                    'total_results' => 100,
                    'size' => 10,
                ],
                'request_id' => '123abc',
            ],
            'results' => $records,
        ];

        // Convert objects to stdClass
        return json_decode(json_encode($response), false);
    }

}
