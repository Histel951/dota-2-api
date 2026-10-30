<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\OpenDota\Infrastructure\Match;

use Histel951\Dota2Api\OpenDota\Infrastructure\Match\MatchExtractor;
use Histel951\Dota2Api\OpenDota\Infrastructure\Match\MatchMapper;
use Histel951\Dota2Api\OpenDota\Match\Domain\MatchDetail;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\PlayerRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Роль игрока OpenDota — его position_est (1-5), иначе UNKNOWN; соседи по стороне не затрагиваются
 */
class MatchMapperPositionEstTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';

    private const int AME = 898754153; // radiant, pos1
    private const int XNOVA = 94296097; // radiant, pos5
    private const int NOTHING_TO_SAY = 173978074; // radiant, pos2, единственный на миду
    private const int FY = 101695162; // radiant, pos4
    private const int TIGER = 98887913; // radiant, pos3
    private const int YSR = 170896543; // dire, pos1
    private const int ZZQ = 249835593; // dire, pos5
    private const int ECHOZZ = 315272623; // dire, pos2
    private const int PLANET = 150961567; // dire, pos4
    private const int NIU = 145957968; // dire, pos3

    /** роли по position_est фикстуры (совпадают с MatchMapperTest::playersProvider) */
    private const array POSITION_RADIANT = [
        self::AME => PlayerRole::CARRY,
        self::XNOVA => PlayerRole::HARD_SUPPORT,
        self::NOTHING_TO_SAY => PlayerRole::MIDDLE,
        self::FY => PlayerRole::SUPPORT,
        self::TIGER => PlayerRole::OFFLANE,
    ];

    private const array POSITION_DIRE = [
        self::YSR => PlayerRole::CARRY,
        self::ZZQ => PlayerRole::HARD_SUPPORT,
        self::ECHOZZ => PlayerRole::MIDDLE,
        self::PLANET => PlayerRole::SUPPORT,
        self::NIU => PlayerRole::OFFLANE,
    ];

    private MatchMapper $mapper;

    public function setUp(): void
    {
        $this->mapper = new MatchMapper();
    }

    public function test_fixtureRolesFollowPositionEst()
    {
        $match = $this->mapFixture();

        $this->assertEquals(self::POSITION_RADIANT + self::POSITION_DIRE, $this->roles($match));
    }

    public function test_swappedPositionEstIsTakenAsIs()
    {
        $match = $this->mapFixture(static function (array $data): array {
            $data = self::setPosition($data, self::TIGER, 4);

            return self::setPosition($data, self::FY, 3);
        });

        $roles = $this->roles($match);

        $this->assertSame(PlayerRole::SUPPORT, $roles[self::TIGER]);
        $this->assertSame(PlayerRole::OFFLANE, $roles[self::FY]);
        $this->assertSame(PlayerRole::CARRY, $roles[self::AME]);
        $this->assertSame(PlayerRole::HARD_SUPPORT, $roles[self::XNOVA]);
        $this->assertSame(PlayerRole::MIDDLE, $roles[self::NOTHING_TO_SAY]);
        $this->assertEquals(self::POSITION_DIRE, $this->side($roles, self::POSITION_DIRE));
    }

    #[DataProvider('missingPositionProvider')]
    public function test_missingPositionGivesUnknownOnlyToThatPlayer(bool $removeKey)
    {
        $match = $this->mapFixture(
            static fn (array $data): array => self::setPosition($data, self::TIGER, $removeKey ? false : null),
        );

        $this->assertEquals(
            [self::TIGER => PlayerRole::UNKNOWN] + self::POSITION_RADIANT + self::POSITION_DIRE,
            $this->roles($match),
        );
    }

    public static function missingPositionProvider(): array
    {
        return ['null value' => [false], 'key removed' => [true]];
    }

    #[DataProvider('invalidPositionProvider')]
    public function test_invalidPositionGivesUnknownOnlyToThatPlayer(mixed $value)
    {
        $match = $this->mapFixture(static fn (array $data): array => self::setPosition($data, self::FY, $value));

        $this->assertEquals(
            [self::FY => PlayerRole::UNKNOWN] + self::POSITION_RADIANT + self::POSITION_DIRE,
            $this->roles($match),
        );
    }

    public static function invalidPositionProvider(): array
    {
        return [
            'zero' => [0],
            'six' => [6],
            'negative' => [-1],
            'numeric string' => ['4'],
            'float' => [4.5],
            'bool' => [true],
        ];
    }

    public function test_duplicatedPositionIsTakenAsIsWithoutTouchingOthers()
    {
        // проверки набора 1-5 на стороне нет: каждая роль берётся из position_est своего игрока
        $match = $this->mapFixture(static fn (array $data): array => self::setPosition($data, self::XNOVA, 1));

        $this->assertEquals(
            [self::XNOVA => PlayerRole::CARRY] + self::POSITION_RADIANT + self::POSITION_DIRE,
            $this->roles($match),
        );
    }

    public function test_invalidPositionOnRadiantDoesNotAffectDire()
    {
        $match = $this->mapFixture(function (array $data): array {
            $data = self::setPosition($data, self::AME, 0);
            $data = self::setPosition($data, self::YSR, 3);

            return self::setPosition($data, self::NIU, 1);
        });

        $roles = $this->roles($match);

        $this->assertEquals(
            [self::AME => PlayerRole::UNKNOWN] + self::POSITION_RADIANT,
            $this->side($roles, self::POSITION_RADIANT),
        );
        $this->assertSame(PlayerRole::OFFLANE, $roles[self::YSR]);
        $this->assertSame(PlayerRole::CARRY, $roles[self::NIU]);
    }

    public function test_unparsedMatchMapsWithUnknownRoles()
    {
        $match = $this->mapFixture(static fn (array $data): array => self::withoutPositions($data));

        $this->assertCount(10, $match->getPlayers()->getValue());
        $this->assertSame(array_fill(0, 10, PlayerRole::UNKNOWN), array_values($this->roles($match)));
    }

    public function test_noMiddleLayoutWithValidPositionEstMapsByPositionEst()
    {
        $match = $this->mapFixture(static fn (array $data): array => self::removeRadiantMiddle($data));

        $this->assertEquals(self::POSITION_RADIANT + self::POSITION_DIRE, $this->roles($match));
    }

    public function test_noMiddleLayoutWithoutPositionEstMapsWithUnknownRoles()
    {
        // раньше эвристика падала на такой раскладке с DomainException
        $match = $this->mapFixture(
            static fn (array $data): array => self::withoutPositions(self::removeRadiantMiddle($data)),
        );

        $this->assertCount(10, $match->getPlayers()->getValue());
        $this->assertSame(array_fill(0, 10, PlayerRole::UNKNOWN), array_values($this->roles($match)));
    }

    /**
     * @param int|false|mixed $position false — удалить ключ
     */
    private static function setPosition(array $data, int $accountId, mixed $position): array
    {
        foreach ($data['players'] as &$player) {
            if ($player['account_id'] === $accountId) {
                if (false === $position) {
                    unset($player['position_est']);
                } else {
                    $player['position_est'] = $position;
                }
            }
        }

        return $data;
    }

    /** Нераспарсенный матч: position_est нет ни у кого */
    private static function withoutPositions(array $data): array
    {
        foreach ($data['players'] as &$player) {
            unset($player['position_est']);
        }

        return $data;
    }

    /** Мидер Radiant уходит на сейф: на миду 0 игроков */
    private static function removeRadiantMiddle(array $data): array
    {
        foreach ($data['players'] as &$player) {
            if ($player['account_id'] === self::NOTHING_TO_SAY) {
                $player['lane'] = 1;
                $player['lane_role'] = 1;
            }
        }

        return $data;
    }

    /**
     * @return array<int, PlayerRole> account id => роль
     */
    private function roles(MatchDetail $match): array
    {
        $roles = [];
        foreach ($match->getPlayers()->getValue() as $player) {
            $roles[$player->getIdentity()->getPlayerId()->toInt()] = $player->getRole()->getValue();
        }

        return $roles;
    }

    /**
     * @param array<int, PlayerRole> $roles
     * @param array<int, PlayerRole> $sample игроки нужной стороны
     * @return array<int, PlayerRole>
     */
    private function side(array $roles, array $sample): array
    {
        return array_intersect_key($roles, $sample);
    }

    /**
     * @param callable(array): array|null $modify
     */
    private function mapFixture(?callable $modify = null): MatchDetail
    {
        $data = json_decode(file_get_contents(self::FIXTURE), true);

        if (null !== $modify) {
            $data = $modify($data);
        }

        return $this->mapper->toEntity((new MatchExtractor())->extract($data));
    }
}
