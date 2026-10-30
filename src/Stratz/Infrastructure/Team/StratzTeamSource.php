<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Team;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlGateway;
use Histel951\Dota2Api\Stratz\Team\Domain\Heroes\TeamHero;
use Histel951\Dota2Api\Stratz\Team\Domain\History\TeamMatch;
use Histel951\Dota2Api\Stratz\Team\Domain\Roster\TeamPlayer;
use Histel951\Dota2Api\Stratz\Team\Domain\Team;
use Histel951\Dota2Api\Stratz\Team\Port\TeamSourceInterface;

/**
 * Источник команд STRATZ: GraphQL-запросы по teamId.
 */
final class StratzTeamSource implements TeamSourceInterface
{
    public function __construct(
        private readonly GraphQlGateway $gateway,
        private readonly TeamMapper $teamMapper,
        private readonly TeamMatchMapper $matchesMapper,
        private readonly TeamPlayerMapper $playerMapper,
        private readonly TeamHeroMapper $heroMapper,
    )
    {
    }

    /**
     * @throws SourceRequestException
     * @throws SourceDataException
     */
    public function getTeam(TeamId $id): Team
    {
        $result = $this->gateway->query(TeamQuery::TEAM, ['teamId' => $id->toInt()]);

        return $this->teamMapper->toEntity($result->field('team'));
    }

    /**
     * @return list<TeamMatch>
     * @throws SourceRequestException
     * @throws SourceDataException
     */
    public function getMatchesByTeam(TeamId $teamId): array
    {
        $team = $this->gateway->query(TeamQuery::MATCHES, ['teamId' => $teamId->toInt()])->field('team');
        $rows = array_map(
            fn (mixed $row): array => ['teamId' => $teamId->toInt()] + $this->row($row),
            $this->listOf($team, 'matches'),
        );

        return $this->matchesMapper->toCollection($rows);
    }

    /**
     * @return list<TeamPlayer>
     * @throws SourceRequestException
     * @throws SourceDataException
     */
    public function getPlayersByTeam(TeamId $teamId): array
    {
        $team = $this->gateway->query(TeamQuery::PLAYERS, ['teamId' => $teamId->toInt()])->field('team');
        $rows = array_map(
            fn (mixed $row): array => ['teamId' => $teamId->toInt()] + $this->row($row),
            $this->listOf($team, 'players'),
        );

        return $this->playerMapper->toCollection($rows);
    }

    /**
     * @return list<TeamHero>
     * @throws SourceRequestException
     * @throws SourceDataException
     */
    public function getHeroesByTeam(TeamId $teamId): array
    {
        $result = $this->gateway->query(TeamQuery::HEROES, ['teamId' => $teamId->toInt()]);
        $team = $result->field('team');

        $names = [];
        foreach ($this->listOf($result->field('constants'), 'heroes') as $hero) {
            $hero = $this->row($hero);
            if (isset($hero['id']) && is_int($hero['id'])) {
                $names[$hero['id']] = $hero['displayName'] ?? null;
            }
        }

        $rows = array_map(
            function (mixed $row) use ($names): array {
                $row = $this->row($row);
                $heroId = $row['heroId'] ?? null;

                return $row + ['displayName' => is_int($heroId) ? ($names[$heroId] ?? null) : null];
            },
            $this->listOf($team, 'heroes'),
        );

        return $this->heroMapper->toCollection($rows);
    }

    /**
     * @param array<mixed> $parent
     * @return list<mixed>
     * @throws SourceDataException
     */
    private function listOf(array $parent, string $key): array
    {
        $value = $parent[$key] ?? null;

        if (null === $value) {
            return [];
        }

        if (!is_array($value)) {
            throw new SourceDataException(sprintf('Field "%s" is not a list in GraphQL response', $key));
        }

        return array_values($value);
    }

    /**
     * @return array<mixed>
     * @throws SourceDataException
     */
    private function row(mixed $row): array
    {
        if (!is_array($row)) {
            throw new SourceDataException('List item in GraphQL response is not an object');
        }

        return $row;
    }
}
