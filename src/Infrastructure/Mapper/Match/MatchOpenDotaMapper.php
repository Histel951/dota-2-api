<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Mapper\Match;

use Histel951\Dota2Api\Domain\Common\Enums\RoleEnum;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidDurationException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidGPMException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidHeroIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidKDAException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidMatchIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidPlayerIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidTeamIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidTeamNameException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidXPMException;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Duration;
use Histel951\Dota2Api\Domain\Common\ValueObjects\GPM;
use Histel951\Dota2Api\Domain\Common\ValueObjects\HeroId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\KDA;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Role;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\XPM;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\LaneEnum;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\SideEnum;
use Histel951\Dota2Api\Domain\Entities\Match\Exceptions\InvalidDraftOrderException;
use Histel951\Dota2Api\Domain\Entities\Match\MatchDetail;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Draft;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\DraftDecision;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\DraftOrder;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerLane;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerPerformance;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayers;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchResult;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TeamSide;
use Histel951\Dota2Api\Infrastructure\Exceptions\MappingException;
use Histel951\Dota2Api\Infrastructure\Mapper\AbstractMapper;

class MatchOpenDotaMapper extends AbstractMapper
{
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
     */
    function toEntity(array $data): MatchDetail
    {
        $this->validate($data, self::REQUIRED_FIELDS);
        $optional = $this->extractOptionalFields($data, self::OPTIONAL_FIELDS);

        $players = [];
        $radiantPlayers = [];
        $direPlayers = [];
        foreach ($data['players'] as $player) {
            $playerEntity = $this->createPlayer($player);
            $players[] = $playerEntity;

            if ($playerEntity->isRadiant()) {
                $radiantPlayers[] = $playerEntity;
            }

            if (false === $playerEntity->isRadiant()) {
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
            side: SideEnum::RADIANT,
        );

        $direTeam = $this->createTeamSide(
            teamId: $optional['dire_team_id'],
            teamName: $optional['dire_name'],
            won: !$data['radiant_win'],
            picks: $direPicks,
            bans: $direBans,
            players: $direPlayers,
            side: SideEnum::DIRE,
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
        );
    }

    /**
     * @throws InvalidKDAException
     * @throws InvalidHeroIdException
     * @throws InvalidXPMException
     * @throws InvalidGPMException
     * @throws InvalidPlayerIdException
     */
    private function createPlayer(array $playerData): MatchPlayerPerformance
    {
        return new MatchPlayerPerformance(
            playerId: new PlayerId($playerData['account_id']),
            heroId: new HeroId($playerData['hero_id']),
            kda: new Kda(
                kills: $playerData['kills'],
                deaths: $playerData['deaths'],
                assists: $playerData['assists']
            ),
            gpm: new Gpm($playerData['gold_per_min']),
            xpm: new Xpm($playerData['xp_per_min']),
            lane: new MatchPlayerLane(LaneEnum::from($playerData['lane'])),
            role: new Role(RoleEnum::UNKNOWN), // todo: ЗАГЛУШКА!! сделать RoleResolver который будет определять роль игрока в матче на основе общей статистики
            side: $playerData['isRadiant'] ? SideEnum::RADIANT : SideEnum::DIRE,
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
            side: SideEnum::from($data['team']),
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
     * @param SideEnum $side
     * @return TeamSide
     * @throws InvalidTeamIdException
     * @throws InvalidTeamNameException
     */
    private function createTeamSide(
        ?int $teamId,
        ?string $teamName,
        bool $won,
        array $picks,
        array $bans,
        array $players,
        SideEnum $side,
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
     * @deprecated
     * @param array $data
     * @return array
     */
    public function toCollection(array $data): array
    {
        return [];
    }
}