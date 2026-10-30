<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure\Match;

use Histel951\Dota2Api\SharedKernel\Exception\InvalidGPMException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidHeroIdException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidKDAException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidPlayerIdException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidXPMException;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\PlayerMapper;
use Histel951\Dota2Api\Stratz\Match\Domain\Exception\InvalidStunDurationException;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Lane;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Position;
use Histel951\Dota2Api\Tests\Support\Stratz\StratzFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;

class PlayerMapperTest extends TestCase
{
    private PlayerMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PlayerMapper();
    }

    /**
     * Ожидания посчитаны по реальной фикстуре match_9026055036 (формулы: wards.type 0/1, wardDestruction.isWard
     * true/false, runes.action PICKUP, последний campStack, сумма count по локациям, smoke itemId 188,
     * stunDuration / 100, число buyBackEvents).
     *
     * @param array<string, mixed> $e
     */
    #[DataProvider('parsedPlayersProvider')]
    public function test_mapMapsParsedFixturePlayer(int $index, array $e)
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID)['players'][$index];

        $player = $this->mapper->map($raw, true);

        $identity = $player->getIdentity();
        $this->assertSame($e['acc'], $identity->getPlayerId()->toInt());
        $this->assertSame($e['hero'], $identity->getHeroId()->getValue());
        $this->assertSame($e['radiant'], $identity->isRadiant());
        $this->assertSame($e['lane'], $identity->getLane()->name);
        $this->assertSame($e['pos'], $identity->getPosition()?->value);
        $this->assertSame($raw['steamAccount']['name'], $identity->getPersonaName()?->getValue());
        $this->assertSame($raw['steamAccount']['proSteamAccount']['name'], $identity->getProName()?->getValue());

        $this->assertSame($e['k'], $player->getKda()->getKills());
        $this->assertSame($e['d'], $player->getKda()->getDeaths());
        $this->assertSame($e['a'], $player->getKda()->getAssists());
        $this->assertSame($e['gpm'], $player->getEconomy()->getGpm()->getValue());
        $this->assertSame($e['xpm'], $player->getEconomy()->getXpm()->getValue());
        $this->assertSame($e['nw'], $player->getEconomy()->getNetWorth()->getValue());
        $this->assertSame($e['gs'], $player->getEconomy()->getGoldSpent()->getValue());
        $this->assertSame($e['hd'], $player->getDamage()->getHeroDamage()->getValue());
        $this->assertSame($e['td'], $player->getDamage()->getTowerDamage()->getValue());
        $this->assertSame($e['hh'], $player->getDamage()->getHeroHealing()->getValue());
        $this->assertSame($e['lh'], $player->getFarm()->getLastHits()->getValue());
        $this->assertSame($e['dn'], $player->getFarm()->getDenies()->getValue());

        $stats = $player->getParsedStats();
        $this->assertNotNull($stats);
        $this->assertSame($e['lc'], $stats->getLaneCreeps()->getValue());
        $this->assertSame($e['nc'], $stats->getNeutralCreeps()->getValue());
        $this->assertSame($e['ac'], $stats->getAncientCreeps()->getValue());
        $this->assertSame($e['cs'], $stats->getCampsStacked()->getValue());
        $this->assertSame($e['wo'], $stats->getObserversPlaced()->getValue());
        $this->assertSame($e['ws'], $stats->getSentriesPlaced()->getValue());
        $this->assertSame($e['do'], $stats->getObserversDestroyed()->getValue());
        $this->assertSame($e['ds'], $stats->getSentriesDestroyed()->getValue());
        $this->assertSame($e['ru'], $stats->getRunePickups()->getValue());
        $this->assertSame($e['ck'], $stats->getCourierKills()->getValue());
        $this->assertSame($e['sm'], $stats->getSmokeUses()->getValue());
        $this->assertEqualsWithDelta($e['st'], $stats->getStunDuration()->getSeconds(), 0.0001);
        $this->assertSame($e['bb'], $stats->getBuybacks()?->getValue());
    }

    /**
     * @return array<string, array{int, array<string, mixed>}>
     */
    public static function parsedPlayersProvider(): array
    {
        return [
            'player0' => [0, ['acc' => 10366616, 'radiant' => true, 'hero' => 50, 'lane' => 'SAFE', 'pos' => 5, 'k' => 4, 'd' => 8, 'a' => 16, 'gpm' => 376, 'xpm' => 562, 'nw' => 13766, 'gs' => 12820, 'hd' => 10504, 'td' => 921, 'hh' => 22639, 'lh' => 114, 'dn' => 10, 'lc' => 58, 'nc' => 21, 'ac' => 0, 'cs' => 2, 'wo' => 15, 'ws' => 29, 'do' => 3, 'ds' => 3, 'ru' => 3, 'ck' => 0, 'sm' => 6, 'st' => 0, 'bb' => 1]],
            'player1' => [1, ['acc' => 111620041, 'radiant' => true, 'hero' => 17, 'lane' => 'MIDDLE', 'pos' => 2, 'k' => 9, 'd' => 6, 'a' => 9, 'gpm' => 663, 'xpm' => 961, 'nw' => 27305, 'gs' => 23035, 'hd' => 30231, 'td' => 1500, 'hh' => 13388, 'lh' => 495, 'dn' => 12, 'lc' => 261, 'nc' => 148, 'ac' => 13, 'cs' => 2, 'wo' => 2, 'ws' => 1, 'do' => 1, 'ds' => 4, 'ru' => 8, 'ck' => 0, 'sm' => 0, 'st' => 34.15, 'bb' => 1]],
            'player2' => [2, ['acc' => 138880576, 'radiant' => true, 'hero' => 96, 'lane' => 'OFF', 'pos' => 3, 'k' => 2, 'd' => 5, 'a' => 14, 'gpm' => 591, 'xpm' => 646, 'nw' => 22074, 'gs' => 23565, 'hd' => 13826, 'td' => 1886, 'hh' => 0, 'lh' => 404, 'dn' => 2, 'lc' => 217, 'nc' => 122, 'ac' => 24, 'cs' => 4, 'wo' => 0, 'ws' => 0, 'do' => 0, 'ds' => 0, 'ru' => 2, 'ck' => 0, 'sm' => 0, 'st' => 28.96, 'bb' => 2]],
            'player3' => [3, ['acc' => 105045291, 'radiant' => true, 'hero' => 123, 'lane' => 'OFF', 'pos' => 4, 'k' => 2, 'd' => 9, 'a' => 16, 'gpm' => 388, 'xpm' => 669, 'nw' => 14886, 'gs' => 13615, 'hd' => 19294, 'td' => 229, 'hh' => 0, 'lh' => 151, 'dn' => 1, 'lc' => 96, 'nc' => 30, 'ac' => 0, 'cs' => 3, 'wo' => 6, 'ws' => 12, 'do' => 4, 'ds' => 3, 'ru' => 4, 'ck' => 0, 'sm' => 1, 'st' => 101.83, 'bb' => 1]],
            'player4' => [4, ['acc' => 363758022, 'radiant' => true, 'hero' => 12, 'lane' => 'SAFE', 'pos' => 1, 'k' => 9, 'd' => 2, 'a' => 12, 'gpm' => 786, 'xpm' => 934, 'nw' => 36423, 'gs' => 31120, 'hd' => 41957, 'td' => 11801, 'hh' => 0, 'lh' => 615, 'dn' => 15, 'lc' => 313, 'nc' => 187, 'ac' => 36, 'cs' => 1, 'wo' => 0, 'ws' => 0, 'do' => 1, 'ds' => 2, 'ru' => 1, 'ck' => 0, 'sm' => 0, 'st' => 0, 'bb' => 0]],
            'player5' => [5, ['acc' => 898754153, 'radiant' => false, 'hero' => 53, 'lane' => 'SAFE', 'pos' => 1, 'k' => 13, 'd' => 3, 'a' => 13, 'gpm' => 1009, 'xpm' => 1249, 'nw' => 38483, 'gs' => 41175, 'hd' => 48163, 'td' => 16490, 'hh' => 0, 'lh' => 869, 'dn' => 17, 'lc' => 335, 'nc' => 388, 'ac' => 47, 'cs' => 6, 'wo' => 0, 'ws' => 0, 'do' => 0, 'ds' => 1, 'ru' => 8, 'ck' => 0, 'sm' => 0, 'st' => 0, 'bb' => 2]],
            'player6' => [6, ['acc' => 147767183, 'radiant' => false, 'hero' => 69, 'lane' => 'OFF', 'pos' => 3, 'k' => 7, 'd' => 8, 'a' => 15, 'gpm' => 578, 'xpm' => 747, 'nw' => 23931, 'gs' => 26335, 'hd' => 25299, 'td' => 0, 'hh' => 0, 'lh' => 292, 'dn' => 1, 'lc' => 112, 'nc' => 134, 'ac' => 25, 'cs' => 5, 'wo' => 1, 'ws' => 2, 'do' => 1, 'ds' => 4, 'ru' => 4, 'ck' => 0, 'sm' => 0, 'st' => 17.4, 'bb' => 0]],
            'player7' => [7, ['acc' => 113457795, 'radiant' => false, 'hero' => 106, 'lane' => 'MIDDLE', 'pos' => 2, 'k' => 7, 'd' => 5, 'a' => 12, 'gpm' => 564, 'xpm' => 761, 'nw' => 21310, 'gs' => 21825, 'hd' => 34238, 'td' => 675, 'hh' => 428, 'lh' => 340, 'dn' => 3, 'lc' => 248, 'nc' => 73, 'ac' => 0, 'cs' => 1, 'wo' => 2, 'ws' => 0, 'do' => 2, 'ds' => 2, 'ru' => 20, 'ck' => 0, 'sm' => 0, 'st' => 0, 'bb' => 1]],
            'player8' => [8, ['acc' => 157475523, 'radiant' => false, 'hero' => 121, 'lane' => 'OFF', 'pos' => 4, 'k' => 1, 'd' => 4, 'a' => 19, 'gpm' => 387, 'xpm' => 545, 'nw' => 15263, 'gs' => 16510, 'hd' => 16769, 'td' => 417, 'hh' => 493, 'lh' => 157, 'dn' => 4, 'lc' => 80, 'nc' => 59, 'ac' => 4, 'cs' => 0, 'wo' => 4, 'ws' => 6, 'do' => 2, 'ds' => 3, 'ru' => 7, 'ck' => 1, 'sm' => 5, 'st' => 12.14, 'bb' => 1]],
            'player9' => [9, ['acc' => 207829314, 'radiant' => false, 'hero' => 64, 'lane' => 'SAFE', 'pos' => 5, 'k' => 2, 'd' => 6, 'a' => 10, 'gpm' => 283, 'xpm' => 575, 'nw' => 9002, 'gs' => 11470, 'hd' => 8602, 'td' => 2431, 'hh' => 525, 'lh' => 67, 'dn' => 0, 'lc' => 8, 'nc' => 42, 'ac' => 1, 'cs' => 2, 'wo' => 14, 'ws' => 29, 'do' => 5, 'ds' => 8, 'ru' => 3, 'ck' => 0, 'sm' => 3, 'st' => 16.32, 'bb' => 1]],
        ];
    }

    public function test_mapUnparsedFixturePlayerHasNoParsedData()
    {
        $match = StratzFixtures::match(StratzFixtures::UNPARSED_MATCH_ID);
        $raw = $match['players'][0];

        $player = $this->mapper->map($raw, false);

        $identity = $player->getIdentity();
        $this->assertNull($player->getParsedStats());
        $this->assertNull($identity->getPosition());
        $this->assertSame(Lane::UNKNOWN, $identity->getLane());
        $this->assertSame(898754153, $identity->getPlayerId()->toInt());
        $this->assertSame(41, $identity->getHeroId()->getValue());
        $this->assertSame('NothingToGay', $identity->getPersonaName()?->getValue());
        $this->assertSame('Ame', $identity->getProName()?->getValue());
        $this->assertSame(2, $player->getKda()->getKills());
        $this->assertSame(5, $player->getKda()->getDeaths());
        $this->assertSame(9, $player->getKda()->getAssists());
        $this->assertSame(693, $player->getEconomy()->getGpm()->getValue());
        $this->assertSame(1017, $player->getEconomy()->getXpm()->getValue());
        $this->assertSame(29434, $player->getEconomy()->getNetWorth()->getValue());
        $this->assertSame(580, $player->getFarm()->getLastHits()->getValue());
        $this->assertSame(24, $player->getFarm()->getDenies()->getValue());
    }

    public function test_mapIgnoresStatsWhenMatchIsNotParsed()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID)['players'][0];

        $this->assertNull($this->mapper->map($raw, false)->getParsedStats());
    }

    public function test_mapDirePlayerSideFromIsRadiant()
    {
        $player = $this->mapper->map($this->createRaw(['isRadiant' => false]), false);

        $this->assertTrue($player->getIdentity()->isDire());
        $this->assertFalse($player->getIdentity()->isRadiant());
    }

    public function test_mapWithoutPlaybackDataGivesNullBuybacks()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID)['players'][0];
        $raw['playbackData'] = null;

        $stats = $this->mapper->map($raw, true)->getParsedStats();

        $this->assertNotNull($stats);
        $this->assertNull($stats->getBuybacks());
        $this->assertSame(15, $stats->getObserversPlaced()->getValue());
    }

    public function test_mapWithAbsentPlaybackDataKeyGivesNullBuybacks()
    {
        $raw = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID)['players'][0];
        unset($raw['playbackData']);

        $this->assertNull($this->mapper->map($raw, true)->getParsedStats()?->getBuybacks());
    }

    public function test_mapWithEmptyBuyBackEventsGivesZeroBuybacks()
    {
        $raw = $this->createRaw(['playbackData' => ['buyBackEvents' => []]]);

        $this->assertSame(0, $this->mapper->map($raw, true)->getParsedStats()?->getBuybacks()?->getValue());
    }

    public function test_mapParsedPlayerWithoutStatsGivesZeros()
    {
        $raw = $this->createRaw(['stats' => null, 'playbackData' => null]);

        $stats = $this->mapper->map($raw, true)->getParsedStats();

        $this->assertNotNull($stats);
        $this->assertSame(0, $stats->getLaneCreeps()->getValue());
        $this->assertSame(0, $stats->getNeutralCreeps()->getValue());
        $this->assertSame(0, $stats->getAncientCreeps()->getValue());
        $this->assertSame(0, $stats->getCampsStacked()->getValue());
        $this->assertSame(0, $stats->getObserversPlaced()->getValue());
        $this->assertSame(0, $stats->getSentriesPlaced()->getValue());
        $this->assertSame(0, $stats->getObserversDestroyed()->getValue());
        $this->assertSame(0, $stats->getSentriesDestroyed()->getValue());
        $this->assertSame(0, $stats->getRunePickups()->getValue());
        $this->assertSame(0, $stats->getCourierKills()->getValue());
        $this->assertSame(0, $stats->getSmokeUses()->getValue());
        $this->assertSame(0.0, $stats->getStunDuration()->getSeconds());
        $this->assertNull($stats->getBuybacks());
    }

    public function test_mapCountsOnlyMatchingEntries()
    {
        $raw = $this->createRaw([
            'stats' => [
                'campStack' => [1, 3, 7],
                'wards' => [['type' => 0], ['type' => 1], ['type' => 1], ['type' => 2]],
                'wardDestruction' => [['isWard' => true], ['isWard' => false], ['isWard' => false], ['isWard' => null]],
                'runes' => [['action' => 'PICKUP'], ['action' => 'ACTIVATE'], ['action' => 'PICKUP']],
                'courierKills' => [['time' => 100], ['time' => 200]],
                'itemUsed' => [
                    ['itemId' => 188, 'count' => 3],
                    ['itemId' => 1, 'count' => 9],
                    ['itemId' => 188, 'count' => 2],
                ],
                'farmDistributionReport' => [
                    'creepLocation' => [['count' => 4], ['count' => 6]],
                    'neutralLocation' => [['count' => 1]],
                    'ancientLocation' => [],
                ],
                'heroDamageReport' => ['dealtTotal' => ['stunDuration' => 1234]],
            ],
            'playbackData' => ['buyBackEvents' => [['time' => 1], ['time' => 2], ['time' => 3]]],
        ]);

        $stats = $this->mapper->map($raw, true)->getParsedStats();

        $this->assertNotNull($stats);
        $this->assertSame(7, $stats->getCampsStacked()->getValue());
        $this->assertSame(1, $stats->getObserversPlaced()->getValue());
        $this->assertSame(2, $stats->getSentriesPlaced()->getValue());
        $this->assertSame(1, $stats->getObserversDestroyed()->getValue());
        $this->assertSame(2, $stats->getSentriesDestroyed()->getValue());
        $this->assertSame(2, $stats->getRunePickups()->getValue());
        $this->assertSame(2, $stats->getCourierKills()->getValue());
        $this->assertSame(5, $stats->getSmokeUses()->getValue());
        $this->assertSame(10, $stats->getLaneCreeps()->getValue());
        $this->assertSame(1, $stats->getNeutralCreeps()->getValue());
        $this->assertSame(0, $stats->getAncientCreeps()->getValue());
        $this->assertEqualsWithDelta(12.34, $stats->getStunDuration()->getSeconds(), 0.0001);
        $this->assertSame(3, $stats->getBuybacks()?->getValue());
    }

    /**
     * Базовые показатели STRATZ отдаёт всегда (и у нераспарсенных матчей): пропуск — битый ответ, а не 0
     */
    #[DataProvider('baseFieldProvider')]
    public function test_mapWithoutBaseFieldFails(string $field)
    {
        $raw = $this->createRaw();
        unset($raw[$field]);

        $this->expectException(TypeError::class);

        $this->mapper->map($raw, false);
    }

    public static function baseFieldProvider(): array
    {
        return array_map(static fn(string $field): array => [$field], [
            'kills' => 'kills', 'deaths' => 'deaths', 'assists' => 'assists',
            'goldPerMinute' => 'goldPerMinute', 'experiencePerMinute' => 'experiencePerMinute',
            'networth' => 'networth', 'goldSpent' => 'goldSpent', 'heroDamage' => 'heroDamage',
            'towerDamage' => 'towerDamage', 'heroHealing' => 'heroHealing',
            'numLastHits' => 'numLastHits', 'numDenies' => 'numDenies',
        ]);
    }

    public function test_mapWithoutOptionalIdentityFieldsGivesDefaults()
    {
        $raw = $this->createRaw();
        unset($raw['isRadiant']);

        $player = $this->mapper->map($raw, false);

        $this->assertSame(Lane::UNKNOWN, $player->getIdentity()->getLane());
        $this->assertTrue($player->getIdentity()->isDire());
        $this->assertNull($player->getIdentity()->getProName());
        $this->assertNull($player->getIdentity()->getPersonaName());
    }

    #[DataProvider('positionProvider')]
    public function test_mapPosition(?string $raw, ?Position $expected)
    {
        $player = $this->mapper->map($this->createRaw(['position' => $raw]), false);

        $this->assertSame($expected, $player->getIdentity()->getPosition());
    }

    /**
     * @return array<string, array{?string, ?Position}>
     */
    public static function positionProvider(): array
    {
        return [
            'position1' => ['POSITION_1', Position::CARRY],
            'position2' => ['POSITION_2', Position::MIDDLE],
            'position3' => ['POSITION_3', Position::OFFLANE],
            'position4' => ['POSITION_4', Position::SOFT_SUPPORT],
            'position5' => ['POSITION_5', Position::HARD_SUPPORT],
            'null' => [null, null],
            'zero' => ['POSITION_0', null],
            'six' => ['POSITION_6', null],
            'unknown' => ['UNKNOWN', null],
            'suffix' => ['POSITION_12', null],
        ];
    }

    #[DataProvider('blankNameProvider')]
    public function test_mapBlankNamesGiveNull(?string $name)
    {
        $player = $this->mapper->map(
            $this->createRaw(['steamAccount' => ['name' => $name, 'proSteamAccount' => ['name' => $name]]]),
            false,
        );

        $this->assertNull($player->getIdentity()->getPersonaName());
        $this->assertNull($player->getIdentity()->getProName());
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function blankNameProvider(): array
    {
        return ['null' => [null], 'empty' => [''], 'spaces' => ['   ']];
    }

    public function test_mapPlayerWithoutProSteamAccount()
    {
        $player = $this->mapper->map(
            $this->createRaw(['steamAccount' => ['name' => 'Nick', 'proSteamAccount' => null]]),
            false,
        );

        $this->assertSame('Nick', $player->getIdentity()->getPersonaName()?->getValue());
        $this->assertNull($player->getIdentity()->getProName());
    }

    public function test_mapNegativeKillsThrows()
    {
        $this->expectException(InvalidKDAException::class);

        $this->mapper->map($this->createRaw(['kills' => -1]), false);
    }

    public function test_mapNegativeGpmThrows()
    {
        $this->expectException(InvalidGPMException::class);

        $this->mapper->map($this->createRaw(['goldPerMinute' => -1]), false);
    }

    public function test_mapNegativeXpmThrows()
    {
        $this->expectException(InvalidXPMException::class);

        $this->mapper->map($this->createRaw(['experiencePerMinute' => -1]), false);
    }

    public function test_mapInvalidSteamAccountIdThrows()
    {
        $this->expectException(InvalidPlayerIdException::class);

        $this->mapper->map($this->createRaw(['steamAccountId' => 0]), false);
    }

    public function test_mapInvalidHeroIdThrows()
    {
        $this->expectException(InvalidHeroIdException::class);

        $this->mapper->map($this->createRaw(['heroId' => 0]), false);
    }

    public function test_mapNegativeStunDurationThrows()
    {
        $raw = $this->createRaw(['stats' => ['heroDamageReport' => ['dealtTotal' => ['stunDuration' => -50]]]]);

        $this->expectException(InvalidStunDurationException::class);

        $this->mapper->map($raw, true);
    }

    public function test_mapDoesNotEmitWarningsOnSparseStats()
    {
        $raw = $this->createRaw(['stats' => ['wards' => [[]], 'runes' => [[]], 'itemUsed' => [[]]]]);

        $stats = $this->mapper->map($raw, true)->getParsedStats();

        $this->assertSame(0, $stats?->getObserversPlaced()->getValue());
        $this->assertSame(0, $stats?->getRunePickups()->getValue());
        $this->assertSame(0, $stats?->getSmokeUses()->getValue());
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createRaw(array $overrides = []): array
    {
        return array_merge(
            [
                'steamAccountId' => 100,
                'isRadiant' => true,
                'heroId' => 1,
                'kills' => 1,
                'deaths' => 2,
                'assists' => 3,
                'goldPerMinute' => 400,
                'experiencePerMinute' => 500,
                'networth' => 10000,
                'goldSpent' => 9000,
                'heroDamage' => 15000,
                'towerDamage' => 2000,
                'heroHealing' => 0,
                'numLastHits' => 150,
                'numDenies' => 10,
            ],
            $overrides,
        );
    }
}
