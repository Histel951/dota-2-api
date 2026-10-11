<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Http;

use Histel951\Dota2Api\Infrastructure\Http\ConfigurationHttpClient;
use Histel951\Dota2Api\Infrastructure\Http\Enums\ApiSource;
use Histel951\Dota2Api\Infrastructure\OpenDota\Http\OpenDotaHttpClientFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenDotaHttpClientFactoryTest extends TestCase
{
    /** @var string[] запрошенные url, включая повторы */
    private array $requests = [];

    public function test_requestRetriesTooManyRequests()
    {
        $client = $this->createClient([
            new MockResponse('', ['http_code' => 429]),
            new MockResponse('{"ok":true}'),
        ]);

        $response = $client->request('GET', 'matches/1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $this->requests);
    }

    public function test_requestRetriesServerError()
    {
        $client = $this->createClient([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('{"ok":true}'),
        ]);

        $response = $client->request('GET', 'matches/1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $this->requests);
    }

    public function test_requestDoesNotRetryNotFound()
    {
        $client = $this->createClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('{"ok":true}'),
        ]);

        $response = $client->request('GET', 'matches/1');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertCount(1, $this->requests);
    }

    public function test_requestAddsBaseUriAndApiKey()
    {
        $client = $this->createClient([new MockResponse('{}')], 'secret');

        $client->request('GET', 'matches/1', ['query' => ['page' => 2]])->getContent();

        $this->assertSame(['https://api.opendota.com/api/matches/1?page=2&api_key=secret'], $this->requests);
    }

    /**
     * @param MockResponse[] $responses
     */
    private function createClient(array $responses, ?string $apiKey = null): HttpClientInterface
    {
        $mock = new MockHttpClient(function (string $method, string $url) use (&$responses): MockResponse {
            $this->requests[] = $url;

            return array_shift($responses);
        });

        return OpenDotaHttpClientFactory::create(
            $mock,
            new ConfigurationHttpClient(
                baseUrl: 'https://api.opendota.com/api/',
                apiSource: ApiSource::OPENDOTA,
                apiKey: $apiKey,
            ),
        );
    }
}
