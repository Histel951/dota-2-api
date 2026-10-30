<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\OpenDota\Mapper;

use Histel951\Dota2Api\Match\Domain\Player\PlayerRole;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidCounterException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidDurationException;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidGPMException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidHeroIdException;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidKDAException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidMatchIdException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidPlayerIdException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidPlayerNameException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidTeamIdException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidTeamNameException;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidXPMException;
use Histel951\Dota2Api\SharedKernel\Duration;
use Histel951\Dota2Api\Match\Domain\Player\Stats\GoldSpent;
use Histel951\Dota2Api\Match\Domain\Player\Stats\GPM;
use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\Match\Domain\Player\Stats\KDA;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Match\Domain\Player\Stats\NetWorth;
use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\PlayerName;
use Histel951\Dota2Api\Match\Domain\Region\Region;
use Histel951\Dota2Api\Match\Domain\Player\Role;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TotalGold;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TotalXP;
use Histel951\Dota2Api\Match\Domain\Player\Stats\XPM;
use Histel951\Dota2Api\Match\Domain\Player\Lane;
use Histel951\Dota2Api\Match\Domain\Side\Side;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidDraftOrderException;
use Histel951\Dota2Api\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Match\Domain\Player\Stats\AncientCreeps;
use Histel951\Dota2Api\Match\Domain\Player\Stats\BuybackCount;
use Histel951\Dota2Api\Match\Domain\Player\Stats\CampsStacked;
use Histel951\Dota2Api\Match\Domain\Player\Stats\CourierKills;
use Histel951\Dota2Api\Match\Domain\Player\Stats\CreepsStats;
use Histel951\Dota2Api\Match\Domain\Player\Stats\DamageStats;
use Histel951\Dota2Api\Match\Domain\Player\Stats\Denies;
use Histel951\Dota2Api\Match\Domain\Draft\Draft;
use Histel951\Dota2Api\Match\Domain\Draft\DraftDecision;
use Histel951\Dota2Api\Match\Domain\Draft\DraftOrder;
use Histel951\Dota2Api\Match\Domain\Player\Stats\HeroDamage;
use Histel951\Dota2Api\Match\Domain\Player\Stats\HeroHealing;
use Histel951\Dota2Api\Match\Domain\Player\Identity;
use Histel951\Dota2Api\Match\Domain\Player\Stats\LaneCreeps;
use Histel951\Dota2Api\Match\Domain\Player\Stats\LastHits;
use Histel951\Dota2Api\Match\Domain\Player\Stats\MatchPlayerEconomy;
use Histel951\Dota2Api\Match\Domain\Player\MatchPlayerLane;
use Histel951\Dota2Api\Match\Domain\Player\MatchPlayerPerformance;
use Histel951\Dota2Api\Match\Domain\Player\MatchPlayers;
use Histel951\Dota2Api\Match\Domain\MatchStartTime;
use Histel951\Dota2Api\Match\Domain\Player\Stats\NeutralCreeps;
use Histel951\Dota2Api\Match\Domain\Player\Stats\ObjectivesStats;
use Histel951\Dota2Api\Match\Domain\Player\Stats\ObserversDestroyed;
use Histel951\Dota2Api\Match\Domain\Player\Stats\ObserversPlaced;
use Histel951\Dota2Api\Match\Domain\Player\Stats\Watchers;
use Histel951\Dota2Api\Match\Domain\Player\Stats\RoshanKills;
use Histel951\Dota2Api\Match\Domain\Player\Stats\SmokeUses;
use Histel951\Dota2Api\Match\Domain\Player\Stats\RunePickups;
use Histel951\Dota2Api\Match\Domain\Player\Stats\SentryDestroyed;
use Histel951\Dota2Api\Match\Domain\Player\Stats\SentryPlaced;
use Histel951\Dota2Api\Match\Domain\Player\Stats\Stuns;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TeamfightParticipation;
use Histel951\Dota2Api\Match\Domain\Side\TeamSide;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TormentorKills;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TowerDamage;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TowerKills;
use Histel951\Dota2Api\Match\Domain\Player\Stats\UtilityStats;
use Histel951\Dota2Api\Match\Domain\Player\Stats\WardingStats;
use Histel951\Dota2Api\Match\Domain\Role\RoleResolverInterface;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Mapping\AbstractMapper;
use TypeError;

class MatchOpenDotaMapper extends AbstractMapper
{
    public function __construct(
        private readonly RoleResolverInterface $roleResolver,
    )
    {
    }

    private const array REQUIRED_FIELDS = [
        'match_id',
        'duration',
        'radiant_win',
        'players',
    ];

    private const array OPTIONAL_FIELDS = [
        'radiant_team_id',
        'radiant_name',
        'dire_team_id',
        'dire_name',
        'picks_bans',
        'region',
    ];

    /**
     * Любая ошибка данных (доменная валидация, неожиданный тип поля) отдаётся как SourceDataException
     *
     * @throws SourceDataException
     */
    public function toEntity(array $data): MatchDetail
    {
        try {
            return $this->createMatch($data);
        } catch (DomainException|TypeError $e) {
            throw new SourceDataException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws InvalidXPMException
     * @throws InvalidGPMException
     * @throws InvalidDurationException
     * @throws InvalidKDAException
     * @throws InvalidHeroIdException
     * @throws InvalidMatchIdException
     * @throws SourceDataException
     * @throws InvalidPlayerIdException
     * @throws InvalidTeamIdException
     * @throws InvalidTeamNameException
     * @throws InvalidDraftOrderException
     * @throws InvalidPlayerNameException
     * @throws InvalidCounterException
     * @throws DomainException
     */
    private function createMatch(array $data): MatchDetail
    {
        $this->validate($data, self::REQUIRED_FIELDS);
        $optional = $this->extractOptionalFields($data, self::OPTIONAL_FIELDS);

        $radiantPlayers = [];
        $direPlayers = [];
        foreach ($data['players'] as $player) {
            $playerEntity = $this->createPlayer($player, $data['objectives'] ?? []);

            if ($playerEntity->getIdentity()->isRadiant()) {
                $radiantPlayers[] = $playerEntity;
            } else {
                $direPlayers[] = $playerEntity;
            }
        }

        $picksBans = $optional['picks_bans'];
        $radiantPicks = [];
        $radiantBans = [];
        $direPicks = [];
        $direBans = [];
        $allPicks = [];
        $allBans = [];
        if (null !== $picksBans) {
            foreach ($picksBans as $draft) {
                $draftDecision = $this->createDraftDecision($draft);

                match ($draftDecision->isPick()) {
                    true => $allPicks[] = $draftDecision,
                    false => $allBans[] = $draftDecision,
                };

                if ($draftDecision->isRadiant()) {
                    match ($draftDecision->isPick()) {
                        true => $radiantPicks[] = $draftDecision,
                        false => $radiantBans[] = $draftDecision,
                    };
                } else {
                    match ($draftDecision->isPick()) {
                        true => $direPicks[] = $draftDecision,
                        false => $direBans[] = $draftDecision,
                    };
                }
            }
        }

        $radiantTeam = $this->createTeamSide(
            teamId: $optional['radiant_team_id'],
            teamName: $optional['radiant_name'],
            won: $data['radiant_win'],
            picks: $radiantPicks,
            bans: $radiantBans,
            players: $radiantPlayers,
            side: Side::RADIANT,
        );

        $direTeam = $this->createTeamSide(
            teamId: $optional['dire_team_id'],
            teamName: $optional['dire_name'],
            won: !$data['radiant_win'],
            picks: $direPicks,
            bans: $direBans,
            players: $direPlayers,
            side: Side::DIRE,
        );

        $detail = new MatchDetail(
            id: new MatchId($data['match_id']),
            duration: Duration::fromSeconds($data['duration']),
            radiant: $radiantTeam,
            dire: $direTeam,
            draft: new Draft(
                picks: $allPicks,
                bans: $allBans,
            ),
            startTime: MatchStartTime::fromTimestamp($data['start_time']),
            region: Region::fromId($optional['region']),
        );

        return $detail->withResolvedRoles($this->roleResolver);
    }

    /**
     * @throws InvalidKDAException
     * @throws InvalidHeroIdException
     * @throws InvalidXPMException
     * @throws InvalidGPMException
     * @throws InvalidPlayerIdException
     * @throws InvalidPlayerNameException
     * @throws InvalidCounterException
     */
    private function createPlayer(array $playerData, array $objectives): MatchPlayerPerformance
    {
        return new MatchPlayerPerformance(
            identity: new Identity(
                playerId: new PlayerId($playerData['account_id']),
                heroId: new HeroId($playerData['hero_id']),
                side: $playerData['isRadiant'] ? Side::RADIANT : Side::DIRE,
                lane: new MatchPlayerLane($this->resolveLane($playerData)),
                playerProName: new PlayerName($playerData['name'] ?? $playerData['personaname']),
                playerPersonaName: new PlayerName($playerData['personaname']),
            ),
            kda: new KDA(
                kills: $playerData['kills'],
                deaths: $playerData['deaths'],
                assists: $playerData['assists']
            ),
            role: new Role($this->resolveInitialRole($playerData)),
            objectives: new ObjectivesStats(
                roshanKills: new RoshanKills($playerData['roshan_kills']),
                towerKills: new TowerKills($playerData['tower_kills']),
                runePickups: new RunePickups($playerData['rune_pickups']),
                tormentorKills: new TormentorKills($this->calculateTormentorKills($objectives, $playerData['player_slot'] ?? 0)),
            ),
            creeps: new CreepsStats(
                lastHits: new LastHits($playerData['last_hits']),
                laneCreeps: new LaneCreeps($playerData['lane_kills']),
                denies: new Denies($playerData['denies']),
                neutralCreeps: new NeutralCreeps($playerData['neutral_kills']),
                ancientCreeps: new AncientCreeps($playerData['ancient_kills']),
                campsStacked: new CampsStacked($playerData['camps_stacked']),
            ),
            economy: new MatchPlayerEconomy(
                netWorth: new NetWorth($playerData['net_worth']),
                totalGold: new TotalGold($playerData['total_gold']),
                totalXP: new TotalXP($playerData['total_xp']),
                goldSpent: new GoldSpent($playerData['gold_spent']),
                XPM: new XPM($playerData['xp_per_min']),
                GPM: new GPM($playerData['gold_per_min']),
            ),
            warding: new WardingStats(
                observersPlaced: new ObserversPlaced($playerData['obs_placed']),
                sentryPlaced: new SentryPlaced($playerData['sen_placed']),
                observersDestroyed: new ObserversDestroyed($playerData['observer_kills']),
                sentryDestroyed: new SentryDestroyed($playerData['sentry_kills']),
                observerTakeovers: new Watchers($playerData['ability_uses']['ability_lamp_use'] ?? 0),
            ),
            damage: new DamageStats(
                heroDamage: new HeroDamage($playerData['hero_damage']),
                towerDamage: new TowerDamage($playerData['tower_damage']),
                heroHealing: new HeroHealing($playerData['hero_healing']),
            ),
            utility: new UtilityStats(
                buybackCount: new BuybackCount($playerData['buyback_count']),
                firstBloodClaimed: (bool)$playerData['firstblood_claimed'],
                courierKills: new CourierKills($playerData['courier_kills']),
                stuns: new Stuns($playerData['stuns']),
                teamfightParticipation: new TeamfightParticipation($playerData['teamfight_participation']),
                smokeUses: new SmokeUses($playerData['item_uses']['smoke_of_deceit'] ?? 0),
            ),
        );
    }

    /**
     * @throws InvalidHeroIdException
     * @throws InvalidDraftOrderException
     */
    private function createDraftDecision(array $data): DraftDecision
    {
        return new DraftDecision(
            heroId: new HeroId($data['hero_id']),
            draftOrder: new DraftOrder($data['order']),
            side: Side::from($data['team']),
            isPick: $data['is_pick'],
        );
    }

    /**
     * @param int|null $teamId
     * @param string|null $teamName
     * @param bool $won
     * @param DraftDecision[] $picks
     * @param DraftDecision[] $bans
     * @param MatchPlayerPerformance[] $players
     * @param Side $side
     * @return TeamSide
     * @throws InvalidTeamIdException
     * @throws InvalidTeamNameException
     */
    private function createTeamSide(
        ?int    $teamId,
        ?string $teamName,
        bool    $won,
        array   $picks,
        array   $bans,
        array   $players,
        Side    $side,
    ): TeamSide
    {
        return new TeamSide(
            teamId: new TeamId($teamId),
            teamName: new TeamName($teamName),
            won: $won,
            draft: new Draft(
                picks: $picks,
                bans: $bans,
            ),
            players: new MatchPlayers($players),
            side: $side,
        );
    }

    /**
     * Определяет линию игрока со стороны radiant (как её ожидает RoleResolverInterface)
     *
     * OpenDota отдаёт:
     * - lane: абсолютная позиция на карте — 1 bot, 2 mid, 3 top, 4 лес radiant, 5 лес dire
     * - lane_role: линия относительно команды игрока — 1 safe, 2 mid, 3 offlane, 4 лес
     *
     * Bot/mid/top совпадают с Lane::SAFE/MIDDLE/OFFLANE со стороны radiant и берутся как есть.
     * Для леса линия восстанавливается по lane_role с переводом в сторону radiant.
     *
     * @param array $playerData Данные игрока от OpenDota
     * @return Lane Линия игрока
     */
    private function resolveLane(array $playerData): Lane
    {
        $lane = Lane::tryFrom($playerData['lane'] ?? 0);

        if (null !== $lane) {
            return $lane;
        }

        $isRadiant = (bool)$playerData['isRadiant'];

        return match ($playerData['lane_role'] ?? null) {
            2 => Lane::MIDDLE,
            3 => $isRadiant ? Lane::OFFLANE : Lane::SAFE,
            default => $isRadiant ? Lane::SAFE : Lane::OFFLANE,
        };
    }

    /**
     * Начальная роль игрока из position_est OpenDota.
     *
     * Значения position_est 1-5 совпадают с PlayerRole (carry, mid, offlane, support, hard support).
     * Всё остальное (null, не int, 0, 6 и выше) — UNKNOWN.
     *
     * @param array $playerData Данные игрока от OpenDota
     */
    private function resolveInitialRole(array $playerData): PlayerRole
    {
        $position = $playerData['position_est'] ?? null;

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