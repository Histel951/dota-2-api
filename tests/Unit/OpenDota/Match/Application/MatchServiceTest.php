<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\OpenDota\Match\Application;

use Generator;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\OpenDota\Infrastructure\Match\MatchExtractor;
use Histel951\Dota2Api\OpenDota\Infrastructure\Match\MatchMapper;
use Histel951\Dota2Api\OpenDota\Match\Application\MatchFetchResult;
use Histel951\Dota2Api\OpenDota\Match\Application\MatchService;
use Histel951\Dota2Api\OpenDota\Match\Domain\MatchDetail;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\MatchPlayerPerformance;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\PlayerRole;
use Histel951\Dota2Api\OpenDota\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\SharedKernel\MatchId;
use PHPUnit\Framework\TestCase;

class MatchServiceTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';

    private const int TIGER = 98887913; // radiant, pos3
    private const int NOTHING_TO_SAY = 173978074; // radiant, pos2

    public function test_getMatchReturnsMatchFromSourceAsIs()
    {
        $match = $this->mappedMatch();
        $source = $this->createMock(MatchSourceInterface::class);
        $source->expects($this->once())->method('getMatch')->willReturn($match);

        $result = (new MatchService($source))->getMatch(new MatchId(8794744726));

        $this->assertNull($result->getError());
        $this->assertSame(8794744726, $result->getMatchId()->getValue());
        $this->assertSame($match, $result->getMatchDetail());
        $this->assertSame(PlayerRole::OFFLANE, $this->roles($result->getMatchDetail())[self::TIGER]);
    }

    public function test_getMatchPassesRequestedIdToSource()
    {
        $id = new MatchId(8794744726);
        $source = $this->createMock(MatchSourceInterface::class);
        $source->expects($this->once())->method('getMatch')->with($id)->willReturn($this->mappedMatch());

        (new MatchService($source))->getMatch($id);
    }

    public function test_getMatchReturnsSourceDataExceptionInResult()
    {
        $error = new SourceDataException('bad data');
        $source = $this->createStub(MatchSourceInterface::class);
        $source->method('getMatch')->willThrowException($error);

        $result = (new MatchService($source))->getMatch(new MatchId(5));

        $this->assertNull($result->getMatchDetail());
        $this->assertSame($error, $result->getError());
        $this->assertSame(5, $result->getMatchId()->getValue());
    }

    public function test_getMatchRethrowsSourceRequestException()
    {
        $source = $this->createStub(MatchSourceInterface::class);
        $source->method('getMatch')->willThrowException(new SourceRequestException('Object not found'));

        $this->expectException(SourceRequestException::class);
        $this->expectExceptionMessage('Object not found');

        (new MatchService($source))->getMatch(new MatchId(5));
    }

    public function test_getMatchWithoutPositionEstAndNoMiddleReturnsMatchWithUnknownRoles()
    {
        // раньше эвристика падала на такой раскладке с DomainException
        $source = $this->createStub(MatchSourceInterface::class);
        $source->method('getMatch')->willReturn($this->mappedMatch(self::withoutPositionAndNoMiddle(...)));

        $result = (new MatchService($source))->getMatch(new MatchId(8794744726));

        $this->assertNull($result->getError());
        $this->assertNotNull($result->getMatchDetail());
        $this->assertSame(
            array_fill(0, 10, PlayerRole::UNKNOWN),
            array_values($this->roles($result->getMatchDetail())),
        );
    }

    public function test_getMatchesReturnsResultPerMatchForMixedStream()
    {
        $dataError = new SourceDataException('bad data');
        $requestError = new SourceRequestException('Object not found');
        $source = $this->createStub(MatchSourceInterface::class);
        $source->method('getMatches')->willReturnCallback(
            fn (): Generator => $this->stream([
                [new MatchId(1), $this->mappedMatch()],
                [new MatchId(2), $requestError],
                [new MatchId(3), $dataError],
                [new MatchId(4), $this->mappedMatch(self::withoutPositionAndNoMiddle(...))],
                [new MatchId(5), $this->mappedMatch()],
            ])
        );

        $results = iterator_to_array((new MatchService($source))->getMatches([]), false);

        $this->assertContainsOnlyInstancesOf(MatchFetchResult::class, $results);
        $this->assertSame([1, 2, 3, 4, 5], array_map(static fn (MatchFetchResult $r) => $r->getMatchId()->getValue(), $results));

        $this->assertNull($results[0]->getError());
        $this->assertNotNull($results[0]->getMatchDetail());

        $this->assertNull($results[1]->getMatchDetail());
        $this->assertSame($requestError, $results[1]->getError());

        $this->assertNull($results[2]->getMatchDetail());
        $this->assertSame($dataError, $results[2]->getError());

        $this->assertNull($results[3]->getError());
        $this->assertSame(PlayerRole::UNKNOWN, $this->roles($results[3]->getMatchDetail())[self::NOTHING_TO_SAY]);

        $this->assertNull($results[4]->getError());
        $this->assertSame(PlayerRole::OFFLANE, $this->roles($results[4]->getMatchDetail())[self::TIGER]);
    }

    public function test_getMatchesKeepsSourceOrder()
    {
        $source = $this->createStub(MatchSourceInterface::class);
        $source->method('getMatches')->willReturnCallback(
            fn (): Generator => $this->stream([
                [new MatchId(30), new SourceRequestException('x')],
                [new MatchId(10), new SourceRequestException('y')],
                [new MatchId(20), new SourceRequestException('z')],
            ])
        );

        $results = iterator_to_array((new MatchService($source))->getMatches([]), false);

        $this->assertSame([30, 10, 20], array_map(static fn (MatchFetchResult $r) => $r->getMatchId()->getValue(), $results));
    }

    public function test_getMatchesPassesIdsAndDefaultConcurrencyToSource()
    {
        $ids = [new MatchId(1), new MatchId(2)];
        $source = $this->createMock(MatchSourceInterface::class);
        $source->expects($this->once())
            ->method('getMatches')
            ->with($ids, 5)
            ->willReturn($this->stream([]));

        $this->assertSame([], iterator_to_array((new MatchService($source))->getMatches($ids), false));
    }

    public function test_getMatchesPassesExplicitConcurrencyToSource()
    {
        $source = $this->createMock(MatchSourceInterface::class);
        $source->expects($this->once())
            ->method('getMatches')
            ->with([], 2)
            ->willReturn($this->stream([]));

        iterator_to_array((new MatchService($source))->getMatches([], 2), false);
    }

    public function test_getMatchesIsLazy()
    {
        $source = $this->createMock(MatchSourceInterface::class);
        $source->expects($this->never())->method('getMatches');

        (new MatchService($source))->getMatches([new MatchId(1)]);
    }

    /**
     * Мидер Radiant уходит на сейф, position_est нет ни у кого (нераспарсенный матч без мида)
     */
    private static function withoutPositionAndNoMiddle(array $data): array
    {
        foreach ($data['players'] as &$player) {
            unset($player['position_est']);
            if (self::NOTHING_TO_SAY === $player['account_id']) {
                $player['lane'] = 1;
                $player['lane_role'] = 1;
            }
        }

        return $data;
    }

    /**
     * @param callable(array): array|null $modify
     */
    private function mappedMatch(?callable $modify = null): MatchDetail
    {
        $data = json_decode(file_get_contents(self::FIXTURE), true);

        if (null !== $modify) {
            $data = $modify($data);
        }

        return (new MatchMapper())->toEntity((new MatchExtractor())->extract($data));
    }

    /**
     * @param list<array{MatchId, MatchDetail|SourceRequestException|SourceDataException}> $items
     */
    private function stream(array $items): Generator
    {
        foreach ($items as [$id, $outcome]) {
            yield $id => $outcome;
        }
    }

    /**
     * @return array<int, PlayerRole>
     */
    private function roles(MatchDetail $match): array
    {
        $roles = [];
        /** @var MatchPlayerPerformance $player */
        foreach ($match->getPlayers()->getValue() as $player) {
            $roles[$player->getIdentity()->getPlayerId()->toInt()] = $player->getRole()->getValue();
        }

        return $roles;
    }
}
