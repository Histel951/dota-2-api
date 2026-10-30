<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure;

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzHttpClientFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ThrottlingHttpClient;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StratzHttpClientFactoryTest extends TestCase
{
    private const string URL = 'https://api.stratz.com/graphql';

    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    public static function setUpBeforeClass(): void
    {
        // ожидание лимитера (usleep) и его часы (microtime/time) — на ClockMock, без реальных минут
        foreach ([
            ThrottlingHttpClient::class,
            AsyncContext::class,
            RateLimiterFactory::class,
            SlidingWindowLimiter::class,
            InMemoryStorage::class,
        ] as $class) {
            ClockMock::register($class);
        }
    }

    protected function tearDown(): void
    {
        ClockMock::withClockMock(false);
    }

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

    public function test_requestsAreNotThrottledWithoutRateLimit()
    {
        ClockMock::withClockMock(1000.0);
        $client = $this->createClient($this->okResponses(5));

        for ($i = 0; $i < 5; ++$i) {
            $client->request('POST', '')->getContent();
        }

        $this->assertCount(5, $this->requests);
        $this->assertSame(1000.0, ClockMock::microtime(true));
    }

    public function test_requestOverRateLimitWaitsForWindow()
    {
        ClockMock::withClockMock(1000.0);
        $client = $this->createClient($this->okResponses(3), rateLimit: 2);

        $client->request('POST', '')->getContent();
        $client->request('POST', '')->getContent();
        $this->assertSame(1000.0, ClockMock::microtime(true));

        $client->request('POST', '')->getContent();
        $waited = ClockMock::microtime(true) - 1000.0;

        $this->assertCount(3, $this->requests);
        $this->assertGreaterThan(1.0, $waited);
        $this->assertLessThanOrEqual(60.0, $waited);
    }

    public function test_retriesStillWorkAndConsumeRateLimit()
    {
        ClockMock::withClockMock(1000.0);
        $client = $this->createClient([
            new MockResponse('', ['http_code' => 500]),
            new MockResponse('{"ok":true}'),
            new MockResponse('{"ok":true}'),
        ], rateLimit: 2);

        $this->assertSame(200, $client->request('POST', '')->getStatusCode());
        $this->assertCount(2, $this->requests);

        // ретрай израсходовал второй слот — следующий запрос ждёт окна
        $before = ClockMock::microtime(true);
        $this->assertSame(200, $client->request('POST', '')->getStatusCode());

        $this->assertCount(3, $this->requests);
        $this->assertGreaterThan(1.0, ClockMock::microtime(true) - $before);
    }

    /**
     * @param MockResponse[] $responses
     */
    private function createClient(
        array $responses,
        ?string $token = null,
        ?int $timeout = null,
        int $maxRetries = 2,
        ?int $rateLimit = null,
    ): HttpClientInterface {
        $mock = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($responses);
        });

        return StratzHttpClientFactory::create(
            $mock,
            new ClientConfig(
                baseUrl: self::URL,
                apiKey: $token,
                timeout: $timeout,
                maxRetries: $maxRetries,
                rateLimitPerMinute: $rateLimit,
            ),
        );
    }

    /**
     * @return list<MockResponse>
     */
    private function okResponses(int $count): array
    {
        return array_map(static fn (): MockResponse => new MockResponse('{}'), range(1, $count));
    }
}
