<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure\Match;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\SharedKernel\Side;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\MatchMapper;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Lane;
use Histel951\Dota2Api\Tests\Support\Stratz\StratzFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MatchMapperTest extends TestCase
{
    private MatchMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new MatchMapper();
    }

    public function test_toEntityMapsParsedMatch()
    {
        $match = $this->mapper->toEntity(StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID));

        $this->assertSame(9026055036, $match->getId()->getValue());
        $this->assertSame(2835, $match->getDuration()->getSeconds());
        $this->assertSame(1790954533, $match->getStartTime()->getValue()->getTimestamp());
        $this->assertSame(3, $match->getRegion()->getId());
        $this->assertTrue($match->isParsed());
        $this->assertTrue($match->getWinner()->isRadiant());
        $this->assertSame(Side::RADIANT, $match->getWinner()->getSide());

        $this->assertSame('15', $match->getRadiant()->getTeamId()->getValue());
        $this->assertSame('LGD Gaming', $match->getRadiant()->getTeamName()->getValue());
        $this->assertSame('8261500', $match->getDire()->getTeamId()->getValue());
        $this->assertSame('Xtreme Gaming', $match->getDire()->getTeamName()->getValue());
    }

    public function test_toEntitySplitsPlayersBySideKeepingOrder()
    {
        $match = $this->mapper->toEntity(StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID));

        $this->assertSame(
            [10366616, 111620041, 138880576, 105045291, 363758022],
            array_map(fn($p): int => $p->getIdentity()->getPlayerId()->toInt(), $match->getRadiant()->getPlayers()),
        );
        $this->assertSame(
            [898754153, 147767183, 113457795, 157475523, 207829314],
            array_map(fn($p): int => $p->getIdentity()->getPlayerId()->toInt(), $match->getDire()->getPlayers()),
        );
        $this->assertCount(10, $match->getPlayers());
    }

    public function test_toEntityMapsParsedPlayersPositionsAndLanes()
    {
        $match = $this->mapper->toEntity(StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID));

        $actual = array_map(
            fn($p): string => $p->getIdentity()->getLane()->name . ':' . $p->getIdentity()->getPosition()?->value,
            $match->getPlayers(),
        );

        $this->assertSame(
            [
                'SAFE:5', 'MIDDLE:2', 'OFF:3', 'OFF:4', 'SAFE:1',
                'SAFE:1', 'OFF:3', 'MIDDLE:2', 'OFF:4', 'SAFE:5',
            ],
            $actual,
        );
        foreach ($match->getPlayers() as $player) {
            $this->assertNotNull($player->getParsedStats());
        }
    }

    public function test_toEntityMapsDraftOfParsedMatch()
    {
        $draft = $this->mapper->toEntity(StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID))->getDraft();

        $this->assertNotNull($draft);
        $this->assertCount(10, $draft->getPicks());
        $this->assertCount(14, $draft->getBans());
    }

    public function test_toEntityMapsUnparsedMatch()
    {
        $match = $this->mapper->toEntity(StratzFixtures::match(StratzFixtures::UNPARSED_MATCH_ID));

        $this->assertSame(8794744726, $match->getId()->getValue());
        $this->assertSame(2776, $match->getDuration()->getSeconds());
        $this->assertSame(13, $match->getRegion()->getId());
        $this->assertFalse($match->isParsed());
        $this->assertNull($match->getDraft());
        $this->assertFalse($match->getWinner()->isRadiant());
        $this->assertSame('8261500', $match->getRadiant()->getTeamId()->getValue());
        $this->assertSame('Xtreme Gaming', $match->getRadiant()->getTeamName()->getValue());
        $this->assertSame('5017210', $match->getDire()->getTeamId()->getValue());
        $this->assertSame('EHOME.immortal', $match->getDire()->getTeamName()->getValue());
    }

    public function test_toEntityUnparsedPlayersHaveNoParsedData()
    {
        $match = $this->mapper->toEntity(StratzFixtures::match(StratzFixtures::UNPARSED_MATCH_ID));

        $this->assertCount(10, $match->getPlayers());
        foreach ($match->getPlayers() as $player) {
            $this->assertNull($player->getParsedStats());
            $this->assertNull($player->getIdentity()->getPosition());
            $this->assertSame(Lane::UNKNOWN, $player->getIdentity()->getLane());
        }
    }

    public function test_toEntityWithoutTeams()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        unset($raw['radiantTeamId'], $raw['direTeamId'], $raw['radiantTeam'], $raw['direTeam']);

        $match = $this->mapper->toEntity($raw);

        $this->assertNull($match->getRadiant()->getTeamId()->getValue());
        $this->assertNull($match->getRadiant()->getTeamName()->getValue());
        $this->assertNull($match->getDire()->getTeamId()->getValue());
        $this->assertNull($match->getDire()->getTeamName()->getValue());
    }

    public function test_toEntityWithNullTeams()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw['radiantTeamId'] = null;
        $raw['radiantTeam'] = null;

        $match = $this->mapper->toEntity($raw);

        $this->assertNull($match->getRadiant()->getTeamId()->getValue());
        $this->assertNull($match->getRadiant()->getTeamName()->getValue());
    }

    public function test_toEntityDireWinWhenRadiantDidNotWin()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw['didRadiantWin'] = false;

        $this->assertSame(Side::DIRE, $this->mapper->toEntity($raw)->getWinner()->getSide());
    }

    public function test_toEntityWithoutRegionGivesUnknownRegion()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw['regionId'] = null;

        $this->assertSame(0, $this->mapper->toEntity($raw)->getRegion()->getId());
    }

    public function test_toEntityWithoutParsedDateTimeIsNotParsed()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        unset($raw['parsedDateTime']);

        $match = $this->mapper->toEntity($raw);

        $this->assertFalse($match->isParsed());
        foreach ($match->getPlayers() as $player) {
            $this->assertNull($player->getParsedStats());
        }
    }

    public function test_toEntityParsedMatchWithoutPlaybackDataKeepsBuybacksNull()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        foreach ($raw['players'] as &$player) {
            $player['playbackData'] = null;
        }
        unset($player);

        $match = $this->mapper->toEntity($raw);

        $this->assertTrue($match->isParsed());
        foreach ($match->getPlayers() as $p) {
            $this->assertNull($p->getParsedStats()?->getBuybacks());
            $this->assertNotNull($p->getParsedStats());
        }
    }

    public function test_toEntityWithoutPlayersGivesEmptySides()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw['players'] = [];

        $this->assertSame([], $this->mapper->toEntity($raw)->getPlayers());
    }

    #[DataProvider('requiredFieldsProvider')]
    public function test_toEntityMissingRequiredFieldThrows(string $field)
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        unset($raw[$field]);

        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity($raw);
    }

    #[DataProvider('requiredFieldsProvider')]
    public function test_toEntityNullRequiredFieldThrows(string $field)
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw[$field] = null;

        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity($raw);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredFieldsProvider(): array
    {
        return [
            'id' => ['id'],
            'didRadiantWin' => ['didRadiantWin'],
            'durationSeconds' => ['durationSeconds'],
            'startDateTime' => ['startDateTime'],
            'players' => ['players'],
        ];
    }

    public function test_toEntityAnonymousPlayerThrowsSourceDataException()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw['players'][3]['steamAccountId'] = null;

        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity($raw);
    }

    public function test_toEntityInvalidPlayerValueThrowsSourceDataException()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw['players'][0]['kills'] = -5;

        try {
            $this->mapper->toEntity($raw);
            $this->fail('SourceDataException expected');
        } catch (SourceDataException $e) {
            $this->assertNotNull($e->getPrevious());
        }
    }

    public function test_toEntityInvalidDraftThrowsSourceDataException()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw['pickBans'][0]['order'] = 99;

        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity($raw);
    }

    public function test_toEntityWrongTypeOfIdThrowsSourceDataException()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw['id'] = 'not-a-number';

        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity($raw);
    }

    public function test_toEntityWrongTypeOfDurationThrowsSourceDataException()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw['durationSeconds'] = [];

        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity($raw);
    }

    public function test_toEntityPlayerThatIsNotAnObjectThrowsSourceDataException()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw['players'][0] = 'broken';

        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity($raw);
    }

    public function test_toEntityPlayersThatIsNotAListThrowsSourceDataException()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID);
        $raw['players'] = 'broken';

        $this->expectException(SourceDataException::class);
        $this->expectExceptionMessage('Field "players" in API response is not a list');

        $this->mapper->toEntity($raw);
    }
}
