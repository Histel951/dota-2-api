<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Infrastructure\Team;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Mapping\AbstractMapper;
use Histel951\Dota2Api\OpenDota\Team\Domain\Exception\InvalidTeamStatsException;
use Histel951\Dota2Api\OpenDota\Team\Domain\Team;
use Histel951\Dota2Api\OpenDota\Team\Domain\TeamStats;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
use Histel951\Dota2Api\SharedKernel\TeamTag;
use Throwable;

class TeamMapper extends AbstractMapper
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
     * @throws SourceDataException
     */
    public function toEntity(array $data): Team
    {
        $this->validate($data, self::REQUIRED_FIELDS);
        $optional = $this->extractOptionalFields($data, self::OPTIONAL_FIELDS);

        $stats = $this->createTeamStats($data);

        return $this->createTeam($data, $optional, $stats);
    }

    /**
     * @throws SourceDataException
     */
    private function createTeam(array $data, array $optional, TeamStats $stats): Team
    {
        try {
            return new Team(
                id: new TeamId($data['team_id']),
                name: $this->createName($optional['name']),
                tag: $this->createTag($data['tag']),
                stats: $stats,
                lastMatchTime: $this->createDateTime($data['last_match_time']),
            );
        } catch (Throwable $e) {
            throw new SourceDataException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Пустое или отсутствующее имя команды — «имени нет», как и для tag
     */
    private function createName(?string $name): ?TeamName
    {
        return $name === null || trim($name) === '' ? null : new TeamName($name);
    }

    /**
     * Пустой tag у части команд /teams (около 12% списка) означает «тега нет», а не ошибку данных
     */
    private function createTag(string $tag): ?TeamTag
    {
        return trim($tag) === '' ? null : new TeamTag($tag);
    }

    /**
     * @throws SourceDataException
     */
    private function createTeamStats(array $data): TeamStats
    {
        try {
            return TeamStats::fromRecord(wins: $data['wins'], losses: $data['losses'], rating: $data['rating']);
        } catch (InvalidTeamStatsException $e) {
            throw new SourceDataException($e->getMessage(), $e->getCode(), $e);
        }
    }
}