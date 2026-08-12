<?php

namespace SilverStripe\DiscovererBifrost\Tests\Service\Requests;

use Elastic\EnterpriseSearch\AppSearch\Schema\ClickParams;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\DiscovererBifrost\Service\Requests\ClickPost;

class ClickPostTest extends SapphireTest
{

    public function testRequestTargetsTheBifrostClickEndpoint(): void
    {
        $request = ClickPost::create('my-engine', $this->createParams())->getRequest();

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/v1/my-engine/click', $request->getUri()->getPath());
        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testEngineNameIsEncoded(): void
    {
        // The engine name reaches us from the _searchAnalytics query param, so it must not be able to escape its path
        // segment
        $request = ClickPost::create('../../not-my-engine', $this->createParams())->getRequest();

        $this->assertEquals('/api/v1/..%2F..%2Fnot-my-engine/click', $request->getUri()->getPath());
    }

    public function testBodyContainsOnlyTheIdsBifrostAccepts(): void
    {
        $params = $this->createParams();
        // Neither of these are part of the Bifröst contract, and both should be dropped
        $params->query = 'some search term';
        $params->tags = ['tag-one'];

        $request = ClickPost::create('my-engine', $params)->getRequest();

        $this->assertEquals(
            [
                'request_id' => 'request-123',
                'document_id' => 'document-456',
            ],
            json_decode((string) $request->getBody(), true)
        );
    }

    public function testBodyOmitsIdsThatWereNeverSet(): void
    {
        // ClickParams types request_id as a non-nullable string but does not initialise it, so reading it unguarded
        // would be a fatal error rather than a missing key
        $request = ClickPost::create('my-engine', new ClickParams('some search term', 'document-456'))->getRequest();

        $this->assertEquals(['document_id' => 'document-456'], json_decode((string) $request->getBody(), true));
    }

    private function createParams(): ClickParams
    {
        $params = new ClickParams('', 'document-456');
        $params->request_id = 'request-123';

        return $params;
    }

}
