<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Match;

use Closure;
use Exception;
use Histel951\Dota2Api\Client\Config\ApiSource;
use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Exception\Dota2ApiExceptionInterface;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Infrastructure\Http\ApiGateway;
use Histel951\Dota2Api\Infrastructure\Http\HttpMethod;
use Histel951\Dota2Api\Infrastructure\OpenDota\Match\MatchExtractor;
use Histel951\Dota2Api\Infrastructure\OpenDota\Match\MatchMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Match\OpenDotaMatchSource;
use Histel951\Dota2Api\Infrastructure\OpenDota\OpenDotaHttpClientFactory;
use Histel951\Dota2Api\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Match\Domain\Player\PlayerRole;
use Histel951\Dota2Api\SharedKernel\MatchId;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OpenDotaMatchSourceTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';
    private const int FIXTURE_ID = 8794744726;

    /** @var string[] url запросов в порядке отправки */
    private array $requested = [];

    public function test_getMatchReturnsMatchDetail()
    {
        $source = $this->createSource(fn () => new MockResponse($this->body(self::FIXTURE_ID)));

        $match = $source->getMatch(new MatchId(self::FIXTURE_ID));

        $this->assertSame(self::FIXTURE_ID, $match->getId()->getValue());
        $this->assertSame(['https://api.opendota.com/api/matches/8794744726'], $this->requested);
    }

    public function test_getMatchDoesNotResolveRoles()
    {
        // роли приходят из position_est; без него остаются UNKNOWN — резолв в MatchService
        $source = $this->createSource(function () {
            $data = $this->data(self::FIXTURE_ID);
            foreach ($data['players'] as &$player) {
                unset($player['position_est']);
            }

            return new MockResponse(json_encode($data));
        });

        $match = $source->getMatch(new MatchId(self::FIXTURE_ID));

        foreach ($match->getPlayers()->getValue() as $player) {
            $this->assertSame(PlayerRole::UNKNOWN, $player->getRole()->getValue());
        }
    }

    public function test_getMatchThrowsRequestExceptionOnNotFound()
    {
        $source = $this->createSource(fn () => new MockResponse('{"error":"Not Found"}', ['http_code' => 404]));

        $this->expectException(SourceRequestException::class);
        $this->expectExceptionMessage('Object not found');

        $source->getMatch(new MatchId(1));
    }

    #[DataProvider('failedStatusProvider')]
    public function test_getMatchThrowsRequestExceptionOnUnexpectedStatus(int $status)
    {
        $source = $this->createSource(fn () => new MockResponse('', ['http_code' => $status]), maxRetries: 0);

        $this->expectException(SourceRequestException::class);
        $this->expectExceptionMessage((string)$status);

        $source->getMatch(new MatchId(1));
    }

    public static function failedStatusProvider(): array
    {
        return ['too many requests' => [429], 'server error' => [500], 'bad gateway' => [502], 'forbidden' => [403]];
    }

    public function test_getMatchThrowsRequestExceptionOnTransportError()
    {
        $source = $this->createSource(
            fn () => new MockResponse('', ['error' => 'Connection reset by peer']),
            maxRetries: 0,
        );

        try {
            $source->getMatch(new MatchId(1));
            $this->fail('SourceRequestException expected');
        } catch (SourceRequestException $e) {
            $this->assertStringContainsString('Connection reset by peer', $e->getMessage());
        }
    }

    public function test_getMatchThrowsDataExceptionOnInvalidJson()
    {
        $source = $this->createSource(fn () => new MockResponse('not a json'));

        try {
            $source->getMatch(new MatchId(1));
            $this->fail('SourceDataException expected');
        } catch (SourceDataException $e) {
            $this->assertInstanceOf(JsonException::class, $e->getPrevious());
        }
    }

    #[DataProvider('notAnObjectBodyProvider')]
    public function test_getMatchThrowsDataExceptionWhenBodyIsNotArrayOfData(string $body)
    {
        $source = $this->createSource(fn () => new MockResponse($body));

        $this->expectException(SourceDataException::class);

        $source->getMatch(new MatchId(1));
    }

    public static function notAnObjectBodyProvider(): array
    {
        return ['scalar' => ['"text"'], 'null' => ['null'], 'empty array' => ['[]'], 'garbage object' => ['{"a":1}']];
    }

    public function test_getMatchThrowsDataExceptionOnInvalidData()
    {
        $source = $this->createSource(function () {
            $data = $this->data(self::FIXTURE_ID);
            $data['duration'] = -1;

            return new MockResponse(json_encode($data));
        });

        $this->expectException(SourceDataException::class);

        $source->getMatch(new MatchId(self::FIXTURE_ID));
    }

    public function test_getMatchesYieldsAllMatches()
    {
        $source = $this->createSource($this->respondWithMatch());
        $ids = [new MatchId(11), new MatchId(22), new MatchId(33)];

        $results = $this->collect($source->getMatches($ids, 5));

        $this->assertCount(3, $results);
        foreach ($results as [$id, $outcome]) {
            $this->assertInstanceOf(MatchDetail::class, $outcome);
            $this->assertSame($id->getValue(), $outcome->getId()->getValue());
        }
        $this->assertEqualsCanonicalizing([11, 22, 33], $this->idValues($results));
    }

    public function test_getMatchesYieldsNothingForEmptyIds()
    {
        $source = $this->createSource($this->respondWithMatch());

        $this->assertSame([], $this->collect($source->getMatches([], 5)));
        $this->assertSame([], $this->requested);
    }

    public function test_getMatchesReturnsNotFoundAsValueAndKeepsStreaming()
    {
        $source = $this->createSource(
            $this->respondWithMatch(['22' => static fn () => new MockResponse('{}', ['http_code' => 404])])
        );

        $results = $this->byId($this->collect($source->getMatches([new MatchId(11), new MatchId(22), new MatchId(33)], 5)));

        $this->assertInstanceOf(MatchDetail::class, $results[11]);
        $this->assertInstanceOf(SourceRequestException::class, $results[22]);
        $this->assertSame('Object not found', $results[22]->getMessage());
        $this->assertInstanceOf(MatchDetail::class, $results[33]);
    }

    public function test_getMatchesReturnsServerErrorAsValueAndKeepsStreaming()
    {
        $source = $this->createSource(
            $this->respondWithMatch(['22' => static fn () => new MockResponse('', ['http_code' => 500])]),
            maxRetries: 0,
        );

        $results = $this->byId($this->collect($source->getMatches([new MatchId(11), new MatchId(22), new MatchId(33)], 5)));

        $this->assertInstanceOf(MatchDetail::class, $results[11]);
        $this->assertInstanceOf(SourceRequestException::class, $results[22]);
        $this->assertStringContainsString('500', $results[22]->getMessage());
        $this->assertInstanceOf(MatchDetail::class, $results[33]);
    }

    public function test_getMatchesReturnsTooManyRequestsAsValueAndKeepsStreaming()
    {
        $source = $this->createSource(
            $this->respondWithMatch(['11' => static fn () => new MockResponse('', ['http_code' => 429])]),
            maxRetries: 0,
        );

        $results = $this->byId($this->collect($source->getMatches([new MatchId(11), new MatchId(22)], 5)));

        $this->assertInstanceOf(SourceRequestException::class, $results[11]);
        $this->assertStringContainsString('429', $results[11]->getMessage());
        $this->assertInstanceOf(MatchDetail::class, $results[22]);
    }

    public function test_getMatchesReturnsInvalidJsonAsDataExceptionValue()
    {
        $source = $this->createSource(
            $this->respondWithMatch(['22' => static fn () => new MockResponse('not a json')])
        );

        $results = $this->byId($this->collect($source->getMatches([new MatchId(11), new MatchId(22), new MatchId(33)], 5)));

        $this->assertInstanceOf(MatchDetail::class, $results[11]);
        $this->assertInstanceOf(SourceDataException::class, $results[22]);
        $this->assertInstanceOf(JsonException::class, $results[22]->getPrevious());
        $this->assertInstanceOf(MatchDetail::class, $results[33]);
    }

    public function test_getMatchesReturnsUnmappableDataAsDataExceptionValue()
    {
        $source = $this->createSource($this->respondWithMatch([
            '22' => function () {
                $data = $this->data(22);
                unset($data['players']);

                return new MockResponse(json_encode($data));
            },
        ]));

        $results = $this->byId($this->collect($source->getMatches([new MatchId(11), new MatchId(22)], 5)));

        $this->assertInstanceOf(MatchDetail::class, $results[11]);
        $this->assertInstanceOf(SourceDataException::class, $results[22]);
    }

    public function test_getMatchesReturnsTransportErrorAsValueAndKeepsStreaming()
    {
        // ErrorChunk при обрыве соединения бросает из isLast(): генератор не должен падать
        $source = $this->createSource(
            $this->respondWithMatch(['22' => static fn () => new MockResponse('', ['error' => 'Connection reset by peer'])]),
            maxRetries: 0,
        );

        $results = $this->byId($this->collect($source->getMatches([new MatchId(11), new MatchId(22), new MatchId(33)], 5)));

        $this->assertInstanceOf(MatchDetail::class, $results[11]);
        $this->assertInstanceOf(SourceRequestException::class, $results[22]);
        $this->assertStringContainsString('Connection reset by peer', $results[22]->getMessage());
        $this->assertInstanceOf(MatchDetail::class, $results[33]);
    }

    public function test_getMatchesReturnsTimeoutAsValueAndKeepsStreaming()
    {
        // пустая строка в теле MockResponse имитирует таймаут получения данных
        $source = $this->createSource(
            $this->respondWithMatch(['22' => static fn () => new MockResponse(['{"match_id":', ''])]),
            maxRetries: 0,
        );

        $results = $this->byId($this->collect($source->getMatches([new MatchId(11), new MatchId(22), new MatchId(33)], 5)));

        $this->assertInstanceOf(MatchDetail::class, $results[11]);
        $this->assertInstanceOf(SourceRequestException::class, $results[22]);
        $this->assertInstanceOf(MatchDetail::class, $results[33]);
    }

    public function test_getMatchesReturnsEveryFailureKindInOneStream()
    {
        $source = $this->createSource(
            $this->respondWithMatch([
                '2' => static fn () => new MockResponse('{}', ['http_code' => 404]),
                '3' => static fn () => new MockResponse('', ['http_code' => 503]),
                '4' => static fn () => new MockResponse('garbage'),
                '5' => static fn () => new MockResponse('', ['error' => 'boom']),
            ]),
            maxRetries: 0,
        );
        $ids = array_map(static fn (int $id) => new MatchId($id), [1, 2, 3, 4, 5, 6]);

        $results = $this->byId($this->collect($source->getMatches($ids, 3)));

        $this->assertCount(6, $results);
        $this->assertInstanceOf(MatchDetail::class, $results[1]);
        $this->assertInstanceOf(SourceRequestException::class, $results[2]);
        $this->assertInstanceOf(SourceRequestException::class, $results[3]);
        $this->assertInstanceOf(SourceDataException::class, $results[4]);
        $this->assertInstanceOf(SourceRequestException::class, $results[5]);
        $this->assertInstanceOf(MatchDetail::class, $results[6]);
    }

    public function test_getMatchesKeepsDuplicateIds()
    {
        $source = $this->createSource($this->respondWithMatch());
        $shared = new MatchId(11);

        // и один и тот же объект, и разные объекты с одним значением
        $results = $this->collect($source->getMatches([$shared, $shared, new MatchId(11), new MatchId(22)], 5));

        $this->assertCount(4, $results);
        $this->assertEqualsCanonicalizing([11, 11, 11, 22], $this->idValues($results));
        foreach ($results as [, $outcome]) {
            $this->assertInstanceOf(MatchDetail::class, $outcome);
        }
    }

    public function test_getMatchesKeepsDuplicateIdsWithErrors()
    {
        $source = $this->createSource(
            $this->respondWithMatch(['11' => static fn () => new MockResponse('{}', ['http_code' => 404])]),
        );

        $results = $this->collect($source->getMatches([new MatchId(11), new MatchId(11)], 5));

        $this->assertCount(2, $results);
        foreach ($results as [, $outcome]) {
            $this->assertInstanceOf(SourceRequestException::class, $outcome);
        }
    }

    public function test_getMatchesLimitsConcurrencyWindow()
    {
        $source = $this->createSource($this->respondWithMatch());
        $ids = array_map(static fn (int $id) => new MatchId($id), range(1, 8));
        $concurrency = 3;

        $yielded = 0;
        foreach ($source->getMatches($ids, $concurrency) as $outcome) {
            // запрошенных, но ещё не отданных ответов не больше окна
            $this->assertLessThanOrEqual($concurrency, count($this->requested) - $yielded);
            $this->assertInstanceOf(MatchDetail::class, $outcome);
            $yielded++;
        }

        $this->assertSame(8, $yielded);
        $this->assertCount(8, $this->requested);
    }

    public function test_getMatchesWithConcurrencyOneRequestsSequentially()
    {
        $source = $this->createSource($this->respondWithMatch());
        $ids = array_map(static fn (int $id) => new MatchId($id), [1, 2, 3]);

        $yielded = 0;
        foreach ($source->getMatches($ids, 1) as $outcome) {
            $this->assertSame($yielded + 1, count($this->requested));
            $yielded++;
        }

        $this->assertSame(3, $yielded);
    }

    public function test_getMatchesStartsOnlyWindowBeforeFirstResult()
    {
        $source = $this->createSource($this->respondWithMatch());
        $ids = array_map(static fn (int $id) => new MatchId($id), range(1, 6));

        $generator = $source->getMatches($ids, 2);
        $generator->current();

        $this->assertLessThanOrEqual(2, count($this->requested));
    }

    public function test_getMatchesYieldsRejectedRequestAsValue()
    {
        // клиент отверг запрос сразу, ещё до ожидания ответа
        $client = new MockHttpClient($this->respondWithMatch());
        $gateway = new class($client) extends ApiGateway {
            public function request(HttpMethod $method, string $endpoint): ResponseInterface
            {
                if ('matches/22' === $endpoint) {
                    throw new SourceRequestException('rejected');
                }

                return parent::request($method, $endpoint);
            }
        };
        $source = new OpenDotaMatchSource($gateway, new MatchExtractor(), new MatchMapper());

        $results = $this->byId($this->collect($source->getMatches([new MatchId(11), new MatchId(22), new MatchId(33)], 2)));

        $this->assertInstanceOf(MatchDetail::class, $results[11]);
        $this->assertSame('rejected', $results[22]->getMessage());
        $this->assertInstanceOf(MatchDetail::class, $results[33]);
    }

    /**
     * Шлюз как в проде: RetryableHttpClient поверх MockHttpClient.
     *
     * @param Closure $responder fn(string $method, string $url, array $options): MockResponse
     */
    private function createSource(Closure $responder, int $maxRetries = 3): OpenDotaMatchSource
    {
        $mock = new MockHttpClient(function (string $method, string $url, array $options) use ($responder) {
            $this->requested[] = $url;

            return $responder($method, $url, $options);
        });

        $client = OpenDotaHttpClientFactory::create(
            $mock,
            new ClientConfig(
                baseUrl: 'https://api.opendota.com/api/',
                apiSource: ApiSource::OPENDOTA,
                maxRetries: $maxRetries,
            ),
        );

        return new OpenDotaMatchSource(new ApiGateway($client), new MatchExtractor(), new MatchMapper());
    }

    /**
     * Ответ с фикстурой, у которой match_id подменён на запрошенный; точечные подмены по id.
     *
     * @param array<string, Closure> $overrides id => fn(): MockResponse
     */
    private function respondWithMatch(array $overrides = []): Closure
    {
        return function (string $method, string $url) use ($overrides): MockResponse {
            $id = substr($url, strrpos($url, '/') + 1);

            if (isset($overrides[$id])) {
                return $overrides[$id]();
            }

            return new MockResponse($this->body((int)$id));
        };
    }

    private function body(int $matchId): string
    {
        return json_encode($this->data($matchId));
    }

    private function data(int $matchId): array
    {
        static $fixture = null;
        $fixture ??= json_decode(file_get_contents(self::FIXTURE), true);

        return [...$fixture, 'match_id' => $matchId];
    }

    /**
     * @param iterable<MatchId, MatchDetail|Dota2ApiExceptionInterface> $generator
     * @return list<array{MatchId, MatchDetail|Dota2ApiExceptionInterface}>
     */
    private function collect(iterable $generator): array
    {
        $results = [];
        foreach ($generator as $id => $outcome) {
            $results[] = [$id, $outcome];
        }

        return $results;
    }

    /**
     * @param list<array{MatchId, mixed}> $results
     * @return array<int, mixed> значение id => результат
     */
    private function byId(array $results): array
    {
        $byId = [];
        foreach ($results as [$id, $outcome]) {
            $this->assertArrayNotHasKey($id->getValue(), $byId);
            $byId[$id->getValue()] = $outcome;
        }

        return $byId;
    }

    /**
     * @param list<array{MatchId, mixed}> $results
     * @return list<int>
     */
    private function idValues(array $results): array
    {
        return array_map(static fn (array $pair): int => $pair[0]->getValue(), $results);
    }
}
