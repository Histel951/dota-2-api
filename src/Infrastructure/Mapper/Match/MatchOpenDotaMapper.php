<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Mapper\Match;

use Histel951\Dota2Api\Domain\Common\Enums\PlayerRole;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidCounterException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidDurationException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidGPMException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidHeroIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidKDAException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidMatchIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidPlayerIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidPlayerNameException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidTeamIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidTeamNameException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidXPMException;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Duration;
use Histel951\Dota2Api\Domain\Common\ValueObjects\GoldSpent;
use Histel951\Dota2Api\Domain\Common\ValueObjects\GPM;
use Histel951\Dota2Api\Domain\Common\ValueObjects\HeroId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\KDA;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\NetWorth;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Role;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TotalGold;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TotalXP;
use Histel951\Dota2Api\Domain\Common\ValueObjects\XPM;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Lane;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Side;
use Histel951\Dota2Api\Domain\Entities\Match\Exceptions\InvalidDraftOrderException;
use Histel951\Dota2Api\Domain\Entities\Match\MatchDetail;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\AncientCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\BuybackCount;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\CampsStacked;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\CourierKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\CreepsStats;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\DamageStats;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Denies;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Draft;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\DraftDecision;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\DraftOrder;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\HeroDamage;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\HeroHealing;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Identity;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\LaneCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\LastHits;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerEconomy;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerLane;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerPerformance;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayers;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchResult;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchStartTime;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\NeutralCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObjectivesStats;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObserversDestroyed;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObserversPlaced;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObserverTakeovers;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\RoshanKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\SmokeUses;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\RunePickups;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\SentryDestroyed;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\SentryPlaced;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Stuns;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TeamfightParticipation;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TeamSide;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TormentorKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TowerDamage;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TowerKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\UtilityStats;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\WardingStats;
use Histel951\Dota2Api\Domain\Services\RegionResolverInterface;
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;
use Histel951\Dota2Api\Infrastructure\Exceptions\MappingException;
use Histel951\Dota2Api\Infrastructure\Mapper\AbstractMapper;

class MatchOpenDotaMapper extends AbstractMapper
{
    public function __construct(
        private readonly RoleResolverInterface $roleResolver,
        private readonly RegionResolverInterface $regionResolver,
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
     * @throws InvalidXPMException
     * @throws InvalidGPMException
     * @throws InvalidDurationException
     * @throws InvalidKDAException
     * @throws InvalidHeroIdException
     * @throws InvalidMatchIdException
     * @throws MappingException
     * @throws InvalidPlayerIdException
     * @throws InvalidTeamIdException
     * @throws InvalidTeamNameException
     * @throws InvalidDraftOrderException
     * @throws InvalidPlayerNameException
     * @throws InvalidCounterException
     */
    function toEntity(array $data): MatchDetail
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

        $radiantPlayers = $this->roleResolver->resolve($radiantPlayers);
        $direPlayers = $this->roleResolver->resolve($direPlayers, false);

        $players = [
            ...$radiantPlayers,
            ...$direPlayers,
        ];

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

        $winner = null;
        $loser = null;
        foreach ([$radiantTeam, $direTeam] as $team) {
            if ($team->isWon()) {
                $winner = $team;
            } else {
                $loser = $team;
            }
        }

        $matchResult = new MatchResult(
            winner: $winner,
            loser: $loser,
        );

        $region = $this->regionResolver->resolve($optional['region']);

        return new MatchDetail(
            id: new MatchId($data['match_id']),
            duration: Duration::fromSeconds($data['duration']),
            result: $matchResult,
            radiant: $radiantTeam,
            dire: $direTeam,
            draft: new Draft(
                picks: $allPicks,
                bans: $allBans,
            ),
            players: new MatchPlayers($players),
            startTime: MatchStartTime::fromTimestamp($data['start_time']),
            region: $region,
        );
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
                lane: new MatchPlayerLane(Lane::from($playerData['lane'])),
                playerProName: new PlayerName($playerData['name']),
                playerPersonaName: new PlayerName($playerData['personaname']),
            ),
            kda: new KDA(
                kills: $playerData['kills'],
                deaths: $playerData['deaths'],
                assists: $playerData['assists']
            ),
            role: new Role(PlayerRole::UNKNOWN),
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
                observerTakeovers: new ObserverTakeovers($playerData['ability_uses']['ability_capture'] ?? 0),
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
                smokeUses: new SmokeUses($playerData['item_usage']['smoke_of_deceit'] ?? 0),
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

    /**
     * @deprecated
     * @param array $data
     * @return array
     */
    public function toCollection(array $data): array
    {
        return [];
    }
}