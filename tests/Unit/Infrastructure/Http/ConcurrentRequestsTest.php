<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\Http;

use Closure;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Infrastructure\Http\ConcurrentRequests;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ConcurrentRequestsTest extends TestCase
{
    private const string BASE_URL = 'https://api.test/';

    /** Запущенные (не отвергнутые) запросы в порядке отправки. */
    private array $started = [];

    /** Сколько результатов уже отдано потребителю. */
    private int $received = 0;

    private int $maxInFlight = 0;

    /** @var list<int> число запущенных запросов в момент каждого результата */
    private array $startedAtResult = [];

    public function test_emptyInputMakesNoRequests()
    {
        $client = $this->client(fn (): MockResponse => new MockResponse('{}'));

        $this->assertSame([], $this->collect($client, [], 3));
        $this->assertSame([], $this->started);
    }

    public function test_keepsWindowFullAndRefillsAfterEachResult()
    {
        $client = $this->client(fn (): MockResponse => new MockResponse('{}'));
        $batches = range(1, 10);

        $results = $this->collect($client, $batches, 3);

        $this->assertCount(10, $results);
        $this->assertCount(10, $this->started);
        $this->assertSame(3, $this->maxInFlight);
        // до первого результата — ровно окно, дальше по одному новому запросу на каждый результат
        $this->assertSame([3, 4, 5, 6, 7, 8, 9, 10, 10, 10], $this->startedAtResult);
        $this->assertEqualsCanonicalizing($batches, array_column($results, 0));
        foreach ($results as [$batch, $outcome]) {
            $this->assertInstanceOf(ResponseInterface::class, $outcome);
            $this->assertSame(self::BASE_URL . $batch, $outcome->getInfo('url'));
        }
    }

    public function test_concurrencyOneIsStrictlySequential()
    {
        $client = $this->client(fn (): MockResponse => new MockResponse('{}'));

        $results = $this->collect($client, ['a', 'b', 'c'], 1);

        $this->assertSame(['a', 'b', 'c'], array_column($results, 0));
        $this->assertSame([1, 2, 3], $this->startedAtResult);
        $this->assertSame(1, $this->maxInFlight);
    }

    public function test_startsAtMostWindowBeforeFirstResult()
    {
        $client = $this->client(fn (): MockResponse => new MockResponse(['{', '}']));

        $this->collect($client, range(1, 8), 5);

        $this->assertSame(5, $this->startedAtResult[0]);
    }

    public function test_windowLargerThanInputStartsEverything()
    {
        $client = $this->client(fn (): MockResponse => new MockResponse('{}'));

        $results = $this->collect($client, [1, 2], 10);

        $this->assertCount(2, $results);
        $this->assertSame(2, $this->startedAtResult[0]);
    }

    public function test_rejectedSendIsYieldedAsValueWithoutTakingSlot()
    {
        $client = $this->client(fn (): MockResponse => new MockResponse('{}'));
        $rejection = new SourceRequestException('request rejected');

        $results = $this->collect($client, ['a', 'b', 'c', 'd'], 2, rejected: ['b' => $rejection]);
        $byBatch = array_column($results, 1, 0);

        $this->assertCount(4, $results);
        $this->assertSame($rejection, $byBatch['b']);
        $this->assertInstanceOf(ResponseInterface::class, $byBatch['a']);
        $this->assertInstanceOf(ResponseInterface::class, $byBatch['c']);
        $this->assertInstanceOf(ResponseInterface::class, $byBatch['d']);
        // отвергнутый слот не занял: в первом окне запущены a и c
        $this->assertSame([self::BASE_URL . 'a', self::BASE_URL . 'c'], array_slice($this->started, 0, 2));
        $this->assertSame(2, $this->maxInFlight);
    }

    public function test_otherExceptionsFromSendAreNotCaught()
    {
        $client = $this->client(fn (): MockResponse => new MockResponse('{}'));

        $this->expectException(\LogicException::class);

        $this->collect($client, ['a', 'b'], 2, rejected: ['b' => new \LogicException('bug')]);
    }

    public function test_errorStatusesAreYieldedAsResponses()
    {
        $client = $this->retryableClient(fn (string $url): MockResponse => match ($url) {
            self::BASE_URL . '404' => new MockResponse('{"error":"Not Found"}', ['http_code' => 404]),
            self::BASE_URL . '429' => new MockResponse('', ['http_code' => 429]),
            self::BASE_URL . '500' => new MockResponse('', ['http_code' => 500]),
            default => new MockResponse('{}'),
        });

        $results = $this->collect($client, ['200', '404', '429', '500'], 4);
        $byBatch = array_column($results, 1, 0);

        $this->assertCount(4, $results);
        foreach ($byBatch as $batch => $outcome) {
            $this->assertInstanceOf(ResponseInterface::class, $outcome);
            $this->assertSame((int) $batch, $outcome->getStatusCode());
        }
    }

    public function test_brokenAndTimedOutResponsesBecomeRequestExceptions()
    {
        $client = $this->client(fn (string $url): MockResponse => match ($url) {
            self::BASE_URL . 'reset' => new MockResponse('', ['error' => 'connection reset']),
            self::BASE_URL . 'timeout' => new MockResponse(['{"x":', '']),
            default => new MockResponse('{}'),
        });

        $results = $this->collect($client, ['ok', 'reset', 'timeout'], 3);
        $byBatch = array_column($results, 1, 0);

        $this->assertCount(3, $results);
        $this->assertInstanceOf(ResponseInterface::class, $byBatch['ok']);
        foreach (['reset', 'timeout'] as $batch) {
            $this->assertInstanceOf(SourceRequestException::class, $byBatch[$batch]);
            $this->assertInstanceOf(ExceptionInterface::class, $byBatch[$batch]->getPrevious());
            $this->assertSame($byBatch[$batch]->getPrevious()->getMessage(), $byBatch[$batch]->getMessage());
        }
        $this->assertStringContainsString('connection reset', $byBatch['reset']->getMessage());
    }

    public function test_duplicateBatchesYieldEveryOne()
    {
        $client = $this->client(fn (): MockResponse => new MockResponse('{}'));
        $object = new stdClass();

        $results = $this->collect($client, [$object, [1, 2], $object, [1, 2]], 2);

        $this->assertCount(4, $results);
        $this->assertCount(4, $this->started);
        $this->assertSame(2, count(array_filter($results, static fn (array $r): bool => $r[0] === $object)));
        $this->assertSame(2, count(array_filter($results, static fn (array $r): bool => $r[0] === [1, 2])));
    }

    public function test_arrayBatchesOfDifferentSizesKeepTheirResponses()
    {
        $client = $this->client(fn (): MockResponse => new MockResponse('{}'));
        $batches = [[1, 2, 3], [4], [5, 6]];

        $results = $this->collect($client, $batches, 2);

        $this->assertCount(3, $results);
        $this->assertEqualsCanonicalizing($batches, array_column($results, 0));
        foreach ($results as [$batch, $response]) {
            $this->assertSame(self::BASE_URL . implode('-', $batch), $response->getInfo('url'));
        }
    }

    public function test_concurrencyBelowOneThrowsOnFirstIteration()
    {
        $client = $this->client(fn (): MockResponse => new MockResponse('{}'));
        $generator = ConcurrentRequests::fetch(['a'], 0, $this->send($client, []), $client->stream(...));

        try {
            $generator->current();
            $this->fail('InvalidArgumentException expected');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $this->started);
        }
    }

    public function test_earlyBreakCancelsUnfinishedRequestsWithoutThrowing()
    {
        // без отмены деструкторы незавершённых 500-ответов бросили бы ServerException
        $client = $this->client(fn (): MockResponse => new MockResponse(['{', '}'], ['http_code' => 500]));
        $responses = [];
        $send = function (int $batch) use ($client, &$responses): ResponseInterface {
            return $responses[$batch] = $client->request('GET', self::BASE_URL . $batch);
        };

        $generator = ConcurrentRequests::fetch([1, 2, 3, 4, 5], 3, $send, $client->stream(...));
        $first = $generator->key();
        unset($generator);

        $this->assertCount(3, $responses);
        foreach ($responses as $batch => $response) {
            if ($batch === $first) {
                $this->assertSame(500, $response->getStatusCode());
                continue;
            }
            $this->assertTrue($response->getInfo('canceled'));
        }
        unset($responses, $response);
    }

    /**
     * @param Closure(string): MockResponse $responder
     */
    private function client(Closure $responder): HttpClientInterface
    {
        return new MockHttpClient(function (string $method, string $url) use ($responder): MockResponse {
            $this->started[] = $url;
            $this->maxInFlight = max($this->maxInFlight, count($this->started) - $this->received);

            return $responder($url);
        });
    }

    /**
     * Как в проде: RetryableHttpClient бросает Client/ServerException из потока, если статус не проверен.
     *
     * @param Closure(string): MockResponse $responder
     */
    private function retryableClient(Closure $responder): HttpClientInterface
    {
        return new RetryableHttpClient($this->client($responder), maxRetries: 0);
    }

    /**
     * @param array<string, \Throwable> $rejected партия => исключение, которое бросит send()
     */
    private function send(HttpClientInterface $client, array $rejected): Closure
    {
        return function (mixed $batch) use ($client, $rejected): ResponseInterface {
            $path = match (true) {
                is_array($batch) => implode('-', $batch),
                is_object($batch) => 'object',
                default => (string) $batch,
            };

            if (isset($rejected[$path])) {
                throw $rejected[$path];
            }

            return $client->request('GET', self::BASE_URL . $path);
        };
    }

    /**
     * @param list<mixed> $batches
     * @param array<string, \Throwable> $rejected
     * @return list<array{mixed, ResponseInterface|SourceRequestException}>
     */
    private function collect(HttpClientInterface $client, array $batches, int $concurrency, array $rejected = []): array
    {
        $results = [];

        foreach (ConcurrentRequests::fetch($batches, $concurrency, $this->send($client, $rejected), $client->stream(...)) as $batch => $outcome) {
            $this->startedAtResult[] = count($this->started);
            if ($outcome instanceof ResponseInterface) {
                ++$this->received;
            }
            $results[] = [$batch, $outcome];
        }

        return $results;
    }
}
