<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\Http;

use Histel951\Dota2Api\Infrastructure\Http\ConfigurationHttpClient;
use Histel951\Dota2Api\Infrastructure\Http\Enums\ApiSource;
use Histel951\Dota2Api\Infrastructure\Http\OpenDotaHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenDotaHttpClientTest extends TestCase
{
    public function test_requestRetriesTooManyRequests()
    {
        $mock = new MockHttpClient([
            new MockResponse('', ['http_code' => 429]),
            new MockResponse('{"ok":true}'),
        ]);

        $response = $this->createClient($mock)->request('GET', 'matches/1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $mock->getRequestsCount());
    }

    public function test_requestRetriesServerError()
    {
        $mock = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('{"ok":true}'),
        ]);

        $response = $this->createClient($mock)->request('GET', 'matches/1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $mock->getRequestsCount());
    }

    public function test_requestDoesNotRetryNotFound()
    {
        $mock = new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('{"ok":true}'),
        ]);

        $response = $this->createClient($mock)->request('GET', 'matches/1');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(1, $mock->getRequestsCount());
    }

    public function test_requestAddsBaseUriAndApiKey()
    {
        $mock = new MockHttpClient(static function (string $method, string $url): MockResponse {
            return new MockResponse($url);
        });

        $response = $this->createClient($mock, 'secret')->request('GET', 'matches/1');

        $this->assertSame('https://api.opendota.com/api/matches/1?api_key=secret', $response->getContent());
    }

    private function createClient(MockHttpClient $mock, ?string $apiKey = null): OpenDotaHttpClient
    {
        return new OpenDotaHttpClient(
            $mock,
            new ConfigurationHttpClient(
                baseUrl: 'https://api.opendota.com/api/',
                apiSource: ApiSource::OPENDOTA,
                apiKey: $apiKey,
            ),
        );
    }
}
