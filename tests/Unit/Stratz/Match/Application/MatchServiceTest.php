<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Match\Application;

use Generator;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\MatchMapper;
use Histel951\Dota2Api\Stratz\Match\Application\MatchService;
use Histel951\Dota2Api\Stratz\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Stratz\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\Tests\Support\Stratz\StratzFixtures;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MatchServiceTest extends TestCase
{
    private MatchSourceInterface&MockObject $source;
    private MatchService $service;

    protected function setUp(): void
    {
        $this->source = $this->createMock(MatchSourceInterface::class);
        $this->service = new MatchService($this->source);
    }

    public function test_getMatchReturnsDetail()
    {
        $id = new MatchId(9026055036);
        $match = $this->createMatch();
        $this->source->expects($this->once())->method('getMatch')->with($id, true)->willReturn($match);

        $result = $this->service->getMatch($id);

        $this->assertSame($id, $result->getMatchId());
        $this->assertSame($match, $result->getMatchDetail());
        $this->assertNull($result->getError());
    }

    public function test_getMatchPutsDataExceptionInResult()
    {
        $id = new MatchId(5);
        $error = new SourceDataException('bad data');
        $this->source->method('getMatch')->willThrowException($error);

        $result = $this->service->getMatch($id);

        $this->assertSame($id, $result->getMatchId());
        $this->assertNull($result->getMatchDetail());
        $this->assertSame($error, $result->getError());
    }

    public function test_getMatchRethrowsRequestException()
    {
        $this->source->method('getMatch')->willThrowException(new SourceRequestException('Object not found'));

        $this->expectException(SourceRequestException::class);
        $this->expectExceptionMessage('Object not found');

        $this->service->getMatch(new MatchId(5));
    }

    public function test_getMatchesMapsMixedStream()
    {
        $ok = new MatchId(1);
        $missing = new MatchId(2);
        $broken = new MatchId(3);
        $match = $this->createMatch();
        $requestError = new SourceRequestException('Object not found');
        $dataError = new SourceDataException('bad data');

        $this->source
            ->method('getMatches')
            ->willReturnCallback(static function () use ($ok, $missing, $broken, $match, $requestError, $dataError): Generator {
                yield $ok => $match;
                yield $missing => $requestError;
                yield $broken => $dataError;
            });

        $results = iterator_to_array($this->service->getMatches([$ok, $missing, $broken]), false);

        $this->assertCount(3, $results);
        $this->assertSame($ok, $results[0]->getMatchId());
        $this->assertSame($match, $results[0]->getMatchDetail());
        $this->assertNull($results[0]->getError());
        $this->assertSame($missing, $results[1]->getMatchId());
        $this->assertNull($results[1]->getMatchDetail());
        $this->assertSame($requestError, $results[1]->getError());
        $this->assertSame($broken, $results[2]->getMatchId());
        $this->assertNull($results[2]->getMatchDetail());
        $this->assertSame($dataError, $results[2]->getError());
    }

    public function test_getMatchesUsesDefaultConcurrencyOfFive()
    {
        $ids = [new MatchId(1)];
        $this->source
            ->expects($this->once())
            ->method('getMatches')
            ->with($ids, 5, true)
            ->willReturnCallback(static function (): Generator {
                yield from [];
            });

        $this->assertSame([], iterator_to_array($this->service->getMatches($ids), false));
    }

    public function test_getMatchesPassesExplicitConcurrency()
    {
        $ids = [new MatchId(1)];
        $this->source
            ->expects($this->once())
            ->method('getMatches')
            ->with($ids, 2, true)
            ->willReturnCallback(static function (): Generator {
                yield from [];
            });

        $this->assertSame([], iterator_to_array($this->service->getMatches($ids, 2), false));
    }

    public function test_getMatchPassesWithoutPlaybackDataFlag()
    {
        $id = new MatchId(9026055036);
        $match = $this->createMatch();
        $this->source->expects($this->once())->method('getMatch')->with($id, false)->willReturn($match);

        $this->assertSame($match, $this->service->getMatch($id, false)->getMatchDetail());
    }

    public function test_getMatchesPassesWithoutPlaybackDataFlag()
    {
        $ids = [new MatchId(1)];
        $this->source
            ->expects($this->once())
            ->method('getMatches')
            ->with($ids, 3, false)
            ->willReturnCallback(static function (): Generator {
                yield from [];
            });

        $this->assertSame([], iterator_to_array($this->service->getMatches($ids, 3, false), false));
    }

    public function test_getMatchesKeepsDuplicateIds()
    {
        $id = new MatchId(1);
        $match = $this->createMatch();
        $this->source
            ->method('getMatches')
            ->willReturnCallback(static function () use ($id, $match): Generator {
                yield $id => $match;
                yield $id => $match;
            });

        $results = iterator_to_array($this->service->getMatches([$id, $id]), false);

        $this->assertCount(2, $results);
    }

    private function createMatch(): MatchDetail
    {
        return (new MatchMapper())->toEntity(StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID));
    }
}
