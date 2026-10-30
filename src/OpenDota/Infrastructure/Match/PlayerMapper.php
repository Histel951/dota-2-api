<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Infrastructure\Match;

use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Identity;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\MatchPlayerLane;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\MatchPlayerPerformance;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\PlayerRole;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Role;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\AncientCreeps;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\BuybackCount;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\CampsStacked;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\CourierKills;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\CreepsStats;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\DamageStats;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\Denies;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\GoldSpent;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\HeroDamage;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\HeroHealing;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\LaneCreeps;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\LastHits;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\MatchPlayerEconomy;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\NetWorth;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\NeutralCreeps;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\ObjectivesStats;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\ObserversDestroyed;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\ObserversPlaced;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\RoshanKills;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\RunePickups;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\SentryDestroyed;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\SentryPlaced;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\SmokeUses;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\Stuns;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\TeamfightParticipation;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\TormentorKills;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\TotalGold;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\TotalXP;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\TowerDamage;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\TowerKills;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\UtilityStats;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\WardingStats;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\Watchers;
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

/**
 * Собирает игрока матча (MatchPlayerPerformance) из данных игрока OpenDota
 */
final class PlayerMapper
{
    public function __construct(
        private readonly LaneNormalizer $laneNormalizer = new LaneNormalizer(),
    )
    {
    }

    /**
     * @param array $player Данные игрока из players[] ответа OpenDota
     * @param array $objectives objectives матча (пустой у нераспарсенных матчей)
     *
     * @throws InvalidKDAException
     * @throws InvalidHeroIdException
     * @throws InvalidXPMException
     * @throws InvalidGPMException
     * @throws InvalidPlayerIdException
     * @throws InvalidPlayerNameException
     * @throws InvalidCounterException
     */
    public function map(array $player, array $objectives): MatchPlayerPerformance
    {
        $isRadiant = (bool)$player['isRadiant'];

        return new MatchPlayerPerformance(
            identity: new Identity(
                playerId: new PlayerId($player['account_id']),
                heroId: new HeroId($player['hero_id']),
                side: $isRadiant ? Side::RADIANT : Side::DIRE,
                lane: new MatchPlayerLane(
                    $this->laneNormalizer->normalize($player['lane'] ?? null, $player['lane_role'] ?? null, $isRadiant),
                ),
                playerProName: new PlayerName($player['name'] ?? $player['personaname']),
                playerPersonaName: new PlayerName($player['personaname']),
            ),
            kda: new KDA(
                kills: $player['kills'],
                deaths: $player['deaths'],
                assists: $player['assists']
            ),
            role: new Role($this->resolveInitialRole($player)),
            objectives: new ObjectivesStats(
                roshanKills: new RoshanKills($player['roshan_kills']),
                towerKills: new TowerKills($player['tower_kills']),
                runePickups: new RunePickups($player['rune_pickups']),
                tormentorKills: new TormentorKills($this->calculateTormentorKills($objectives, $player['player_slot'] ?? 0)),
            ),
            creeps: new CreepsStats(
                lastHits: new LastHits($player['last_hits']),
                laneCreeps: new LaneCreeps($player['lane_kills']),
                denies: new Denies($player['denies']),
                neutralCreeps: new NeutralCreeps($player['neutral_kills']),
                ancientCreeps: new AncientCreeps($player['ancient_kills']),
                campsStacked: new CampsStacked($player['camps_stacked']),
            ),
            economy: new MatchPlayerEconomy(
                netWorth: new NetWorth($player['net_worth']),
                totalGold: new TotalGold($player['total_gold']),
                totalXP: new TotalXP($player['total_xp']),
                goldSpent: new GoldSpent($player['gold_spent']),
                XPM: new XPM($player['xp_per_min']),
                GPM: new GPM($player['gold_per_min']),
            ),
            warding: new WardingStats(
                observersPlaced: new ObserversPlaced($player['obs_placed']),
                sentryPlaced: new SentryPlaced($player['sen_placed']),
                observersDestroyed: new ObserversDestroyed($player['observer_kills']),
                sentryDestroyed: new SentryDestroyed($player['sentry_kills']),
                observerTakeovers: new Watchers($player['ability_uses']['ability_lamp_use'] ?? 0),
            ),
            damage: new DamageStats(
                heroDamage: new HeroDamage($player['hero_damage']),
                towerDamage: new TowerDamage($player['tower_damage']),
                heroHealing: new HeroHealing($player['hero_healing']),
            ),
            utility: new UtilityStats(
                buybackCount: new BuybackCount($player['buyback_count']),
                firstBloodClaimed: (bool)$player['firstblood_claimed'],
                courierKills: new CourierKills($player['courier_kills']),
                stuns: new Stuns($player['stuns']),
                teamfightParticipation: new TeamfightParticipation($player['teamfight_participation']),
                smokeUses: new SmokeUses($player['item_uses']['smoke_of_deceit'] ?? 0),
            ),
        );
    }

    /**
     * Начальная роль игрока из position_est OpenDota.
     *
     * Значения position_est 1-5 совпадают с PlayerRole (carry, mid, offlane, support, hard support).
     * Всё остальное (null, не int, 0, 6 и выше) — UNKNOWN.
     *
     * @param array $player Данные игрока от OpenDota
     */
    private function resolveInitialRole(array $player): PlayerRole
    {
        $position = $player['position_est'] ?? null;

        if (!is_int($position) || $position < 1 || $position > 5) {
            return PlayerRole::UNKNOWN;
        }

        return PlayerRole::tryFrom($position) ?? PlayerRole::UNKNOWN;
    }

    /**
     * Подсчитывает количество убийств торментора конкретным игроком
     *
     * @param array $objectives Массив objectives из данных матча
     * @param int $playerSlot Player slot игрока
     * @return int Количество убийств торментора
     */
    private function calculateTormentorKills(array $objectives, int $playerSlot): int
    {
        $count = 0;
        foreach ($objectives as $objective) {
            if (
                isset($objective['type']) &&
                $objective['type'] === 'CHAT_MESSAGE_MINIBOSS_KILL' &&
                isset($objective['player_slot']) &&
                $objective['player_slot'] === $playerSlot
            ) {
                $count++;
            }
        }
        return $count;
    }
}
