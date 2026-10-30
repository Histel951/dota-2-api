<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure;

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzHttpClientFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StratzHttpClientFactoryTest extends TestCase
{
    private const string URL = 'https://api.stratz.com/graphql';

    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    public function test_requestGoesPostToBaseUrl()
    {
        $client = $this->createClient([new MockResponse('{}')]);

        $client->request('POST', '', ['json' => ['query' => '{}']])->getContent();

        $this->assertCount(1, $this->requests);
        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame(self::URL, $this->requests[0]['url']);
    }

    public function test_requestAddsBearerTokenAndUserAgent()
    {
        $client = $this->createClient([new MockResponse('{}')], 'secret');

        $client->request('POST', '')->getContent();

        $headers = $this->requests[0]['options']['normalized_headers'];
        $this->assertSame(['Authorization: Bearer secret'], $headers['authorization']);
        $this->assertSame(['User-Agent: STRATZ_API'], $headers['user-agent']);
    }

    public function test_requestHasNoAuthorizationWithoutToken()
    {
        $client = $this->createClient([new MockResponse('{}')], null);

        $client->request('POST', '')->getContent();

        $headers = $this->requests[0]['options']['normalized_headers'];
        $this->assertArrayNotHasKey('authorization', $headers);
        $this->assertArrayHasKey('user-agent', $headers);
    }

    public function test_requestPassesTimeout()
    {
        $client = $this->createClient([new MockResponse('{}')], null, 7);

        $client->request('POST', '')->getContent();

        $this->assertEquals(7, $this->requests[0]['options']['timeout']);
    }

    public function test_postIsRetriedOnServerError()
    {
        $client = $this->createClient([
            new MockResponse('', ['http_code' => 500]),
            new MockResponse('{"ok":true}'),
        ]);

        $this->assertSame(200, $client->request('POST', '')->getStatusCode());
        $this->assertCount(2, $this->requests);
        $this->assertSame('POST', $this->requests[1]['method']);
    }

    public function test_postIsRetriedOnTooManyRequests()
    {
        $client = $this->createClient([
            new MockResponse('', ['http_code' => 429]),
            new MockResponse('{"ok":true}'),
        ]);

        $this->assertSame(200, $client->request('POST', '')->getStatusCode());
        $this->assertCount(2, $this->requests);
    }

    public function test_postIsRetriedOnTransportError()
    {
        $client = $this->createClient([
            new MockResponse('', ['error' => 'connection reset']),
            new MockResponse('{"ok":true}'),
        ]);

        $this->assertSame(200, $client->request('POST', '')->getStatusCode());
        $this->assertCount(2, $this->requests);
    }

    public function test_retriesStopAfterMaxRetries()
    {
        $client = $this->createClient([
            new MockResponse('', ['http_code' => 500]),
            new MockResponse('', ['http_code' => 500]),
            new MockResponse('{"ok":true}'),
        ], null, null, 1);

        $this->assertSame(500, $client->request('POST', '')->getStatusCode());
        $this->assertCount(2, $this->requests);
    }

    public function test_badRequestIsNotRetried()
    {
        $client = $this->createClient([
            new MockResponse('', ['http_code' => 400]),
            new MockResponse('{"ok":true}'),
        ]);

        $this->assertSame(400, $client->request('POST', '')->getStatusCode());
        $this->assertCount(1, $this->requests);
    }

    /**
     * @param MockResponse[] $responses
     */
    private function createClient(
        array $responses,
        ?string $token = null,
        ?int $timeout = null,
        int $maxRetries = 2,
    ): HttpClientInterface {
        $mock = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($responses);
        });

        return StratzHttpClientFactory::create(
            $mock,
            new ClientConfig(baseUrl: self::URL, apiKey: $token, timeout: $timeout, maxRetries: $maxRetries),
        );
    }
}
