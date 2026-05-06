<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Mapper\Team;

use Histel951\Dota2Api\Domain\Common\ValueObjects\Cluster;
use Histel951\Dota2Api\Domain\Common\ValueObjects\LeagueId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\LeagueName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchOutcome;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamName;
use Histel951\Dota2Api\Domain\Entities\TeamMatch\TeamMatch;
use Histel951\Dota2Api\Infrastructure\Exceptions\MappingException;
use Histel951\Dota2Api\Infrastructure\Mapper\AbstractMapper;
use Throwable;

class TeamMatchOpenDotaMapper extends AbstractMapper
{
    /**
     * Required fields for validation
     */
    private const array REQUIRED_FIELDS = [
        'match_id',
        'radiant_win',
        'radiant_score',
        'dire_score',
        'radiant',
        'duration',
        'start_time',
        'leagueid',
        'league_name',
        'cluster',
        'opposing_team_id'
    ];

    private const array OPTIONAL_FIELDS = ['opposing_team_name'];

    /**
     * @throws MappingException
     */
    public function toEntity(array $data): TeamMatch
    {
        $this->validate($data, self::REQUIRED_FIELDS);
        $optional = $this->extractOptionalFields($data, self::OPTIONAL_FIELDS);

        $outcome = $this->createOutcome($data);

        return $this->createTeamMatch($data, $optional, $outcome);
    }

    /**
     * @throws MappingException
     */
    private function createTeamMatch(array $data, array $optional, MatchOutcome $outcome): TeamMatch
    {
        try {
            return new TeamMatch(
                matchId: new MatchId($data['match_id']),
                matchOutcome: $outcome,
                startTime: $this->createDateTime($data['start_time']),
                matchCluster: new Cluster($data['cluster']),
                leagueId: new LeagueId($data['leagueid']),
                leagueName: new LeagueName($data['league_name']),
                opposingTeamId: new TeamId($data['opposing_team_id']),
                opposingTeamName: new TeamName($optional['opposing_team_name'])
            );
        } catch (Throwable $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function createOutcome(array $data): MatchOutcome
    {
        return MatchOutcome::fromApi(
            isRadiant: $data['radiant'],
            radiantWon: $data['radiant_win'],
            radiantScore: $data['radiant_score'],
            direScore: $data['dire_score'],
            duration: $data['duration']
        );
    }
}