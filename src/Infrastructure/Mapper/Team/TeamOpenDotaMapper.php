<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Mapper\Team;

use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamName;
use Histel951\Dota2Api\Domain\Entities\Team\Exceptions\InvalidTeamStatsException;
use Histel951\Dota2Api\Domain\Entities\Team\Team;
use Histel951\Dota2Api\Domain\Entities\Team\ValueObjects\TeamStats;
use Histel951\Dota2Api\Domain\Entities\Team\ValueObjects\TeamTag;
use Histel951\Dota2Api\Infrastructure\Exceptions\MappingException;
use Histel951\Dota2Api\Infrastructure\Mapper\AbstractMapper;
use Throwable;

class TeamOpenDotaMapper extends AbstractMapper
{
    /**
     * Required fields for validation
     */
    private const array REQUIRED_FIELDS = [
        'team_id',
        'tag',
        'wins',
        'losses',
        'rating',
        'last_match_time'
    ];

    private const array OPTIONAL_FIELDS = ['name'];

    /**
     * @throws MappingException
     */
    public function toEntity(array $data): Team
    {
        $this->validate($data, self::REQUIRED_FIELDS);
        $optional = $this->extractOptionalFields($data, self::OPTIONAL_FIELDS);

        $stats = $this->createTeamStats($data);

        return $this->createTeam($data, $optional, $stats);
    }

    /**
     * @throws MappingException
     */
    private function createTeam(array $data, array $optional, TeamStats $stats): Team
    {
        try {
            return new Team(
                id: new TeamId($data['team_id']),
                name: new TeamName($optional['name']),
                tag: new TeamTag($data['tag']),
                stats: $stats,
                lastMatchTime: $this->createDateTime($data['last_match_time']),
            );
        } catch (Throwable $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws MappingException
     */
    private function createTeamStats(array $data): TeamStats
    {
        try {
            return TeamStats::fromApi(wins: $data['wins'], losses: $data['losses'], rating: $data['rating']);
        } catch (InvalidTeamStatsException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}