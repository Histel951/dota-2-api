<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure\Match;

use Closure;
use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlGateway;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\MatchMapper;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\MatchQuery;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\StratzMatchSource;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzHttpClientFactory;
use Histel951\Dota2Api\Stratz\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Tests\Support\Stratz\StratzFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class StratzMatchSourceTest extends TestCase
{
    /** @var list<array{ids: list<int>, query: string}> запросы, как их увидел транспорт */
    private array $requests = [];

    /** Сколько результатов потребитель уже получил (для подсчёта запросов «в полёте») */
    private int $received = 0;

    private int $maxInFlight = 0;

    public function test_getMatchReturnsMappedMatch()
    {
        $source = $this->createSource(fn(int $id): MockResponse => $this->matchResponse($id));

        $match = $source->getMatch(new MatchId(9026055036));

        $this->assertSame(9026055036, $match->getId()->getValue());
        $this->assertTrue($match->isParsed());
        $this->assertCount(10, $match->getPlayers());
        $this->assertCount(1, $this->requests);
        $this->assertSame([9026055036], $this->requests[0]['ids']);
    }

    public function test_getMatchMapsUnparsedMatch()
    {
        $source = $this->createSource(
            fn(int $id): MockResponse => new MockResponse(
                StratzFixtures::matchJson(StratzFixtures::UNPARSED_MATCH_ID),
            ),
        );

        $match = $source->getMatch(new MatchId(8794744726));

        $this->assertFalse($match->isParsed());
        $this->assertNull($match->getDraft());
    }

    public function test_getMatchNotFoundThrowsObjectNotFound()
    {
        $source = $this->createSource(fn(int $id): MockResponse => new MockResponse('{"data":{"m0":null}}'));

        try {
            $source->getMatch(new MatchId(1));
            $this->fail('SourceRequestException expected');
        } catch (SourceRequestException $e) {
            $this->assertSame('Object not found', $e->getMessage());
        }
    }

    public function test_getMatchNullWithFieldErrorKeepsErrorMessage()
    {
        $source = $this->createSource(
            fn(int $id): MockResponse => new MockResponse(
                '{"data":{"m0":null},"errors":[{"message":"match is private","path":["m0"]}]}',
            ),
        );

        $this->expectException(SourceRequestException::class);
        $this->expectExceptionMessage('match is private');

        $source->getMatch(new MatchId(1));
    }

    public function test_getMatchForbiddenThrowsRequestException()
    {
        $source = $this->createSource(fn(int $id): MockResponse => new MockResponse('', ['http_code' => 403]));

        $this->expectException(SourceRequestException::class);

        $source->getMatch(new MatchId(1));
    }

    public function test_getMatchErrorsWithoutDataThrowsRequestException()
    {
        $source = $this->createSource(
            fn(int $id): MockResponse => new MockResponse('{"errors":[{"message":"Unauthorized"}]}'),
        );

        $this->expectException(SourceRequestException::class);
        $this->expectExceptionMessage('Unauthorized');

        $source->getMatch(new MatchId(1));
    }

    public function test_getMatchNotJsonThrowsDataException()
    {
        $source = $this->createSource(fn(int $id): MockResponse => new MockResponse('<html>oops</html>'));

        $this->expectException(SourceDataException::class);

        $source->getMatch(new MatchId(1));
    }

    public function test_getMatchWithBrokenDataThrowsDataException()
    {
        $source = $this->createSource(function (int $id): MockResponse {
            $match = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
            unset($match['durationSeconds']);

            return new MockResponse(json_encode(['data' => ['m0' => $match]], JSON_THROW_ON_ERROR));
        });

        $this->expectException(SourceDataException::class);

        $source->getMatch(new MatchId(1));
    }

    public function test_getMatchTransportErrorThrowsRequestException()
    {
        $source = $this->createSource(fn(int $id): MockResponse => new MockResponse('', ['error' => 'timeout']));

        $this->expectException(SourceRequestException::class);

        $source->getMatch(new MatchId(1));
    }

    public function test_getMatchesReturnsNothingForEmptyList()
    {
        $source = $this->createSource(fn(int $id): MockResponse => $this->matchResponse($id));

        $this->assertSame([], $this->collect($source, [], 2));
        $this->assertSame([], $this->requests);
    }

    public function test_getMatchesRequestsEveryIdWithinConcurrencyLimit()
    {
        $source = $this->createSource(fn(int $id): MockResponse => $this->matchResponse($id));
        $ids = range(101, 107);

        $outcomes = $this->collect($source, $ids, 2);

        $this->assertCount(count($ids), $outcomes);
        foreach ($outcomes as [$id, $outcome]) {
            $this->assertInstanceOf(MatchDetail::class, $outcome);
            $this->assertSame($id, $outcome->getId()->getValue());
        }
        $this->assertEqualsCanonicalizing($ids, array_map(static fn(array $o): int => $o[0], $outcomes));
        $this->assertCount((int) ceil(count($ids) / MatchQuery::BATCH_SIZE_WITH_PLAYBACK), $this->requests);
        $this->assertLessThanOrEqual(2, $this->maxInFlight);
        $this->assertGreaterThanOrEqual(1, $this->maxInFlight);
    }

    public function test_getMatchesWithConcurrencyOneIsSequential()
    {
        $source = $this->createSource(fn(int $id): MockResponse => $this->matchResponse($id));

        $outcomes = $this->collect($source, [1, 2, 3], 1);

        $this->assertCount(3, $outcomes);
        $this->assertSame(1, $this->maxInFlight);
    }

    public function test_getMatchesKeepsDuplicateIds()
    {
        $source = $this->createSource(fn(int $id): MockResponse => $this->matchResponse($id));

        $outcomes = $this->collect($source, [7, 8, 7], 2);

        $this->assertSame([7, 7, 8], $this->sortedIds($outcomes));
        foreach ($outcomes as [, $outcome]) {
            $this->assertInstanceOf(MatchDetail::class, $outcome);
        }
    }

    public function test_getMatchesIsolatesFailuresPerId()
    {
        $source = $this->createSource(function (int $id): MockResponse {
            return match ($id) {
                2 => new MockResponse('{"data":{"m0":null}}'),
                3 => $this->brokenResponse(),
                4 => new MockResponse('', ['error' => 'connection reset']),
                5 => new MockResponse('', ['http_code' => 500]),
                6 => new MockResponse('not json'),
                7 => new MockResponse('', ['http_code' => 429]),
                default => $this->matchResponse($id),
            };
        });

        $outcomes = $this->collect($source, [1, 2, 3, 4, 5, 6, 7, 8], 3);
        $byId = [];
        foreach ($outcomes as [$id, $outcome]) {
            $byId[$id] = $outcome;
        }

        $this->assertCount(8, $outcomes);
        $this->assertInstanceOf(MatchDetail::class, $byId[1]);
        $this->assertInstanceOf(MatchDetail::class, $byId[8]);
        $this->assertInstanceOf(SourceRequestException::class, $byId[2]);
        $this->assertSame('Object not found', $byId[2]->getMessage());
        $this->assertInstanceOf(SourceDataException::class, $byId[3]);
        $this->assertInstanceOf(SourceRequestException::class, $byId[4]);
        $this->assertInstanceOf(SourceRequestException::class, $byId[5]);
        $this->assertInstanceOf(SourceDataException::class, $byId[6]);
        $this->assertInstanceOf(SourceRequestException::class, $byId[7]);
    }

    public function test_getMatchesYieldsRejectedRequestAsValueAndContinues()
    {
        $source = $this->createSource(function (int $id): MockResponse {
            if (2 === $id) {
                throw new TransportException('request rejected');
            }

            return $this->matchResponse($id);
        });

        $outcomes = $this->collect($source, [1, 2, 3], 2);
        $byId = [];
        foreach ($outcomes as [$id, $outcome]) {
            $byId[$id] = $outcome;
        }

        $this->assertCount(3, $outcomes);
        $this->assertInstanceOf(MatchDetail::class, $byId[1]);
        $this->assertInstanceOf(SourceRequestException::class, $byId[2]);
        $this->assertSame('request rejected', $byId[2]->getMessage());
        $this->assertInstanceOf(MatchDetail::class, $byId[3]);
    }

    public function test_getMatchesDoesNotThrowOnTotalFailure()
    {
        $source = $this->createSource(fn(int $id): MockResponse => new MockResponse('', ['http_code' => 403]));

        $outcomes = $this->collect($source, [1, 2], 2);

        $this->assertCount(2, $outcomes);
        foreach ($outcomes as [, $outcome]) {
            $this->assertInstanceOf(SourceRequestException::class, $outcome);
        }
    }

    public function test_getMatchWithoutPlaybackDataOmitsPlaybackFieldsAndBuybacks()
    {
        $source = $this->createBatchSource(fn(array $ids): MockResponse => $this->batchResponse($ids));

        $match = $source->getMatch(new MatchId(9026055036), false);

        $this->assertSame(9026055036, $match->getId()->getValue());
        $this->assertCount(1, $this->requests);
        $this->assertStringNotContainsString('playbackData', $this->requests[0]['query']);
        foreach ($match->getPlayers() as $player) {
            $this->assertNull($player->getParsedStats()?->getBuybacks());
        }
    }

    public function test_getMatchesWithPlaybackDataRequestsOneMatchPerQuery()
    {
        $source = $this->createSource(fn(int $id): MockResponse => $this->matchResponse($id));

        $this->collect($source, [1, 2, 3], 2);

        $this->assertCount(3, $this->requests);
        foreach ($this->requests as $request) {
            $this->assertCount(1, $request['ids']);
            $this->assertStringContainsString(MatchQuery::PLAYBACK_FIELDS, $request['query']);
        }
    }

    public function test_getMatchesWithoutFlagDefaultsToNoPlaybackDataInBatchesOfFive()
    {
        $source = $this->createBatchSource(fn(array $ids): MockResponse => $this->batchResponse($ids));
        $ids = array_map(static fn(int $id): MatchId => new MatchId($id), range(301, 307));

        iterator_to_array($source->getMatches($ids, 2), false);

        $this->assertSame([5, 2], array_map(static fn(array $r): int => count($r['ids']), $this->requests));
        foreach ($this->requests as $request) {
            $this->assertStringNotContainsString('playbackData', $request['query']);
        }
    }

    public function test_getMatchesWithoutPlaybackDataBatchesByFive()
    {
        $source = $this->createBatchSource(fn(array $ids): MockResponse => $this->batchResponse($ids));
        $ids = range(201, 212);

        $outcomes = $this->collect($source, $ids, 2, false);

        $this->assertCount(12, $outcomes);
        $this->assertCount(3, $this->requests);
        $this->assertSame([5, 5, 2], array_map(static fn(array $r): int => count($r['ids']), $this->requests));
        $this->assertEqualsCanonicalizing($ids, array_merge(...array_map(static fn(array $r): array => $r['ids'], $this->requests)));
        $this->assertLessThanOrEqual(2, $this->maxInFlight);

        foreach ($this->requests as $request) {
            $this->assertStringNotContainsString('playbackData', $request['query']);
        }

        foreach ($outcomes as [$id, $outcome]) {
            $this->assertInstanceOf(MatchDetail::class, $outcome);
            $this->assertSame($id, $outcome->getId()->getValue());
            $this->assertTrue($outcome->isParsed());
            $this->assertNotNull($outcome->getDraft());
            $this->assertCount(10, $outcome->getPlayers());

            foreach ($outcome->getPlayers() as $player) {
                $stats = $player->getParsedStats();
                $this->assertNotNull($stats);
                $this->assertNull($stats->getBuybacks());
                $this->assertNotNull($player->getIdentity()->getPosition());
            }
        }

        // остальные показатели заполнены как в режиме с playbackData
        $reference = (new MatchMapper())->toEntity(StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID));
        $first = $outcomes[0][1];
        foreach ($first->getPlayers() as $index => $player) {
            $expected = $reference->getPlayers()[$index];
            $this->assertEquals($expected->getKda(), $player->getKda());
            $this->assertEquals($expected->getFarm(), $player->getFarm());
            $this->assertEquals($expected->getParsedStats()?->getStunDuration(), $player->getParsedStats()?->getStunDuration());
            $this->assertEquals($expected->getParsedStats()?->getObserversPlaced(), $player->getParsedStats()?->getObserversPlaced());
        }
    }

    public function test_getMatchesWithoutPlaybackDataIsolatesFailuresWithinBatch()
    {
        $source = $this->createBatchSource(fn(array $ids): MockResponse => $this->batchResponse(
            $ids,
            static function (int $id, array $match): ?array {
                if (2 === $id) {
                    return null;
                }
                if (4 === $id) {
                    $match['players'][0]['steamAccountId'] = null;
                }

                return $match;
            },
        ));

        $outcomes = $this->collect($source, [1, 2, 3, 4, 5], 1, false);
        $byId = [];
        foreach ($outcomes as [$id, $outcome]) {
            $byId[$id] = $outcome;
        }

        $this->assertCount(1, $this->requests);
        $this->assertCount(5, $outcomes);
        $this->assertInstanceOf(SourceRequestException::class, $byId[2]);
        $this->assertSame('Object not found', $byId[2]->getMessage());
        $this->assertInstanceOf(SourceDataException::class, $byId[4]);
        foreach ([1, 3, 5] as $id) {
            $this->assertInstanceOf(MatchDetail::class, $byId[$id]);
            $this->assertSame($id, $byId[$id]->getId()->getValue());
        }
    }

    public function test_getMatchesWithoutPlaybackDataFieldErrorStaysWithItsId()
    {
        $source = $this->createBatchSource(function (array $ids): MockResponse {
            $data = [];
            foreach ($ids as $index => $id) {
                $data[MatchQuery::alias($index)] = 3 === $id ? null : $this->stripPlayback($this->fixtureMatch($id));
            }

            return new MockResponse(json_encode([
                'data' => $data,
                'errors' => [['message' => 'match is private', 'path' => [MatchQuery::alias(array_search(3, $ids, true))]]],
            ], JSON_THROW_ON_ERROR));
        });

        $outcomes = $this->collect($source, [1, 2, 3, 4, 5], 1, false);
        $byId = [];
        foreach ($outcomes as [$id, $outcome]) {
            $byId[$id] = $outcome;
        }

        $this->assertCount(5, $outcomes);
        $this->assertInstanceOf(SourceRequestException::class, $byId[3]);
        $this->assertSame('match is private', $byId[3]->getMessage());
        foreach ([1, 2, 4, 5] as $id) {
            $this->assertInstanceOf(MatchDetail::class, $byId[$id]);
        }
    }

    public function test_getMatchesWithoutPlaybackDataRequestFailureGoesToWholeBatch()
    {
        $source = $this->createBatchSource(fn(array $ids): MockResponse => in_array(6, $ids, true)
            ? new MockResponse('', ['http_code' => 500])
            : $this->batchResponse($ids));

        $outcomes = $this->collect($source, range(1, 7), 2, false);
        $byId = [];
        foreach ($outcomes as [$id, $outcome]) {
            $byId[$id] = $outcome;
        }

        $this->assertCount(7, $outcomes);
        foreach (range(1, 5) as $id) {
            $this->assertInstanceOf(MatchDetail::class, $byId[$id]);
        }
        $this->assertInstanceOf(SourceRequestException::class, $byId[6]);
        $this->assertSame($byId[6], $byId[7]);
    }

    /**
     * Gateway собирается как в проде (RetryableHttpClient поверх MockHttpClient); ответ выбирается по id,
     * переданному в переменной m0 (запросы по одному матчу).
     *
     * @param Closure(int): MockResponse $responder
     */
    private function createSource(Closure $responder): StratzMatchSource
    {
        return $this->createBatchSource(static fn(array $ids): MockResponse => $responder($ids[0]));
    }

    /**
     * Как createSource, но ответ выбирается по всем id пачки (в порядке алиасов m0, m1, ...).
     *
     * @param Closure(list<int>): MockResponse $responder
     */
    private function createBatchSource(Closure $responder): StratzMatchSource
    {
        $mock = new MockHttpClient(function (string $method, string $url, array $options) use ($responder): MockResponse {
            $body = json_decode((string) ($options['body'] ?? ''), true);
            $ids = array_map('intval', array_values($body['variables']));

            $this->requests[] = ['ids' => $ids, 'query' => (string) $body['query']];
            $this->maxInFlight = max($this->maxInFlight, count($this->requests) - $this->received);

            return $responder($ids);
        });

        return new StratzMatchSource(
            new GraphQlGateway(StratzHttpClientFactory::create(
                $mock,
                new ClientConfig(baseUrl: 'https://api.stratz.com/graphql', maxRetries: 0),
            )),
            new MatchMapper(),
        );
    }

    /**
     * @param list<int> $ids
     *
     * @return list<array{int, MatchDetail|SourceRequestException|SourceDataException}>
     */
    private function collect(StratzMatchSource $source, array $ids, int $concurrency, bool $withPlaybackData = true): array
    {
        $outcomes = [];
        $matchIds = array_map(static fn(int $id): MatchId => new MatchId($id), $ids);

        foreach ($source->getMatches($matchIds, $concurrency, $withPlaybackData) as $id => $outcome) {
            $this->received++;
            $outcomes[] = [$id->getValue(), $outcome];
        }

        return $outcomes;
    }

    /**
     * @param list<array{int, mixed}> $outcomes
     *
     * @return list<int>
     */
    private function sortedIds(array $outcomes): array
    {
        $ids = array_map(static fn(array $o): int => $o[0], $outcomes);
        sort($ids);

        return $ids;
    }

    private function matchResponse(int $id): MockResponse
    {
        $match = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $match['id'] = $id;

        return new MockResponse(json_encode(['data' => ['m0' => $match]], JSON_THROW_ON_ERROR));
    }

    /**
     * Ответ пачки без playbackData (как его отдаёт STRATZ на запрос без этих полей): алиас m{i} — матч
     * фикстуры с id из пачки; $override может подменить данные матча или вернуть null (матч не найден).
     *
     * @param list<int> $ids
     * @param (Closure(int, array<string, mixed>): ?array<string, mixed>)|null $override
     */
    private function batchResponse(array $ids, ?Closure $override = null): MockResponse
    {
        $data = [];
        foreach ($ids as $index => $id) {
            $match = $this->stripPlayback($this->fixtureMatch($id));
            $data[MatchQuery::alias($index)] = null === $override ? $match : $override($id, $match);
        }

        return new MockResponse(json_encode(['data' => $data], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureMatch(int $id): array
    {
        $match = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $match['id'] = $id;

        return $match;
    }

    /**
     * @param array<string, mixed> $match
     *
     * @return array<string, mixed>
     */
    private function stripPlayback(array $match): array
    {
        foreach ($match['players'] as &$player) {
            unset($player['playbackData']);
        }

        return $match;
    }

    private function brokenResponse(): MockResponse
    {
        $match = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $match['players'][0]['steamAccountId'] = null;

        return new MockResponse(json_encode(['data' => ['m0' => $match]], JSON_THROW_ON_ERROR));
    }
}
