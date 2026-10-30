<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Match;

use Histel951\Dota2Api\SharedKernel\Exception\InvalidCounterException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidGPMException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidHeroIdException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidKDAException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidPlayerIdException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidPlayerNameException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidXPMException;
use Histel951\Dota2Api\SharedKernel\GPM;
use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\SharedKernel\KDA;
use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\PlayerName;
use Histel951\Dota2Api\SharedKernel\Side;
use Histel951\Dota2Api\SharedKernel\XPM;
use Histel951\Dota2Api\Stratz\Match\Domain\Exception\InvalidStunDurationException;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Identity;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\MatchPlayer;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Position;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\AncientCreeps;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Buybacks;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\CampsStacked;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\CourierKills;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Damage;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Denies;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Economy;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Farm;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\GoldSpent;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\HeroDamage;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\HeroHealing;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\LaneCreeps;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\LastHits;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\NetWorth;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\NeutralCreeps;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\ObserversDestroyed;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\ObserversPlaced;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\ParsedStats;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\RunePickups;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\SentriesDestroyed;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\SentriesPlaced;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\SmokeUses;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\StunDuration;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\TowerDamage;

/**
 * Собирает игрока матча из элемента players STRATZ
 */
final class PlayerMapper
{
    private const int SMOKE_ITEM_ID = 188;
    private const int OBSERVER_WARD = 0;
    private const int SENTRY_WARD = 1;
    private const string RUNE_PICKUP = 'PICKUP';
    /** STRATZ отдаёт длительность стана в сотых долях секунды */
    private const int STUN_UNITS_PER_SECOND = 100;

    public function __construct(
        private readonly LaneMapper $lanes = new LaneMapper(),
    )
    {
    }

    /**
     * @param array<string, mixed> $player элемент players матча
     * @param bool $isParsed матч распарсен: иначе ParsedStats не заполняется
     *
     * @throws InvalidPlayerIdException
     * @throws InvalidHeroIdException
     * @throws InvalidKDAException
     * @throws InvalidGPMException
     * @throws InvalidXPMException
     * @throws InvalidCounterException
     * @throws InvalidPlayerNameException
     * @throws InvalidStunDurationException
     */
    public function map(array $player, bool $isParsed): MatchPlayer
    {
        return new MatchPlayer(
            identity: new Identity(
                playerId: new PlayerId($player['steamAccountId'] ?? null),
                heroId: new HeroId($player['heroId'] ?? null),
                side: ($player['isRadiant'] ?? null) ? Side::RADIANT : Side::DIRE,
                lane: $this->lanes->toLane($player['lane'] ?? null),
                position: $this->position($player['position'] ?? null),
                proName: $this->name($player['steamAccount']['proSteamAccount']['name'] ?? null),
                personaName: $this->name($player['steamAccount']['name'] ?? null),
            ),
            kda: new KDA(
                kills: $player['kills'] ?? 0,
                deaths: $player['deaths'] ?? 0,
                assists: $player['assists'] ?? 0,
            ),
            economy: new Economy(
                gpm: new GPM($player['goldPerMinute'] ?? 0),
                xpm: new XPM($player['experiencePerMinute'] ?? 0),
                netWorth: new NetWorth($player['networth'] ?? 0),
                goldSpent: new GoldSpent($player['goldSpent'] ?? 0),
            ),
            damage: new Damage(
                heroDamage: new HeroDamage($player['heroDamage'] ?? 0),
                towerDamage: new TowerDamage($player['towerDamage'] ?? 0),
                heroHealing: new HeroHealing($player['heroHealing'] ?? 0),
            ),
            farm: new Farm(
                lastHits: new LastHits($player['numLastHits'] ?? 0),
                denies: new Denies($player['numDenies'] ?? 0),
            ),
            parsedStats: $isParsed ? $this->parsedStats($player) : null,
        );
    }

    /**
     * @param array<string, mixed> $player
     *
     * @throws InvalidCounterException
     * @throws InvalidStunDurationException
     */
    private function parsedStats(array $player): ParsedStats
    {
        $stats = $player['stats'] ?? [];
        $farm = $stats['farmDistributionReport'] ?? [];
        $wards = $stats['wards'] ?? [];
        $destroyed = $stats['wardDestruction'] ?? [];
        $smokes = array_filter(
            $stats['itemUsed'] ?? [],
            static fn(array $item): bool => ($item['itemId'] ?? null) === self::SMOKE_ITEM_ID,
        );

        return new ParsedStats(
            laneCreeps: new LaneCreeps($this->sumCounts($farm['creepLocation'] ?? [])),
            neutralCreeps: new NeutralCreeps($this->sumCounts($farm['neutralLocation'] ?? [])),
            ancientCreeps: new AncientCreeps($this->sumCounts($farm['ancientLocation'] ?? [])),
            campsStacked: new CampsStacked($this->lastValue($stats['campStack'] ?? [])),
            observersPlaced: new ObserversPlaced(
                $this->countWhere($wards, static fn(array $w): bool => ($w['type'] ?? null) === self::OBSERVER_WARD),
            ),
            sentriesPlaced: new SentriesPlaced(
                $this->countWhere($wards, static fn(array $w): bool => ($w['type'] ?? null) === self::SENTRY_WARD),
            ),
            observersDestroyed: new ObserversDestroyed(
                $this->countWhere($destroyed, static fn(array $w): bool => ($w['isWard'] ?? null) === true),
            ),
            sentriesDestroyed: new SentriesDestroyed(
                $this->countWhere($destroyed, static fn(array $w): bool => ($w['isWard'] ?? null) === false),
            ),
            runePickups: new RunePickups(
                $this->countWhere(
                    $stats['runes'] ?? [],
                    static fn(array $r): bool => ($r['action'] ?? null) === self::RUNE_PICKUP,
                ),
            ),
            courierKills: new CourierKills(count($stats['courierKills'] ?? [])),
            smokeUses: new SmokeUses($this->sumCounts($smokes)),
            stunDuration: new StunDuration(
                (float) ($stats['heroDamageReport']['dealtTotal']['stunDuration'] ?? 0) / self::STUN_UNITS_PER_SECOND,
            ),
            buybacks: is_array($player['playbackData'] ?? null) ? new Buybacks(count($player['playbackData']['buyBackEvents'] ?? [])) : null,
        );
    }

    /**
     * 'POSITION_1'..'POSITION_5' → Position, иначе null
     */
    private function position(?string $position): ?Position
    {
        if (null === $position || 1 !== preg_match('/^POSITION_([1-5])$/', $position, $matches)) {
            return null;
        }

        return Position::from((int) $matches[1]);
    }

    /**
     * @throws InvalidPlayerNameException
     */
    private function name(?string $name): ?PlayerName
    {
        if (null === $name || '' === trim($name)) {
            return null;
        }

        return new PlayerName($name);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function sumCounts(array $items): int
    {
        return (int) array_sum(array_column($items, 'count'));
    }

    /**
     * @param array<int, mixed> $items
     * @param callable(mixed): bool $predicate
     */
    private function countWhere(array $items, callable $predicate): int
    {
        return count(array_filter($items, $predicate));
    }

    /**
     * Последний элемент списка, пустой список → 0
     *
     * @param array<int, int> $values
     */
    private function lastValue(array $values): int
    {
        return [] === $values ? 0 : (int) $values[array_key_last($values)];
    }
}
