<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Team;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Mapping\AbstractMapper;
use Histel951\Dota2Api\SharedKernel\Cluster;
use Histel951\Dota2Api\SharedKernel\Duration;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\SharedKernel\LeagueId;
use Histel951\Dota2Api\SharedKernel\LeagueName;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\SharedKernel\Side;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
use Histel951\Dota2Api\Stratz\Team\Domain\History\Score;
use Histel951\Dota2Api\Stratz\Team\Domain\History\TeamMatch;
use TypeError;
use ValueError;

/**
 * Контекст: в строку матча источник добавляет `teamId` — команду, чью историю читаем
 */
final class TeamMatchMapper extends AbstractMapper
{
    private const array REQUIRED_FIELDS = [
        'id',
        'didRadiantWin',
        'durationSeconds',
        'startDateTime',
        'clusterId',
        'leagueId',
        'teamId',
    ];

    private const array OPTIONAL_FIELDS = [
        'league',
        'radiantTeamId',
        'direTeamId',
        'radiantTeam',
        'direTeam',
        'radiantKills',
        'direKills',
    ];

    /**
     * @throws SourceDataException
     */
    public function toEntity(array $data): TeamMatch
    {
        try {
            return $this->createTeamMatch($data);
        } catch (DomainException|TypeError|ValueError $e) {
            throw new SourceDataException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws SourceDataException
     * @throws DomainException
     */
    private function createTeamMatch(array $data): TeamMatch
    {
        $this->validate($data, self::REQUIRED_FIELDS);
        $optional = $this->extractOptionalFields($data, self::OPTIONAL_FIELDS);

        $side = $this->resolveSide($data['teamId'], $optional, $data['id']);
        $isRadiant = $side === Side::RADIANT;

        $opposingTeamId = $isRadiant ? $optional['direTeamId'] : $optional['radiantTeamId'];
        $opposingTeam = $isRadiant ? $optional['direTeam'] : $optional['radiantTeam'];

        return new TeamMatch(
            matchId: new MatchId($data['id']),
            side: $side,
            won: $isRadiant === $data['didRadiantWin'],
            score: $this->createScore($optional['radiantKills'], $optional['direKills']),
            startTime: $this->createDateTime($data['startDateTime']),
            duration: Duration::fromSeconds($data['durationSeconds']),
            cluster: new Cluster($data['clusterId']),
            leagueId: new LeagueId($data['leagueId']),
            leagueName: $this->createLeagueName($optional['league']['displayName'] ?? null),
            opposingTeamId: new TeamId($opposingTeamId),
            opposingTeamName: new TeamName($opposingTeam['name'] ?? null),
        );
    }

    /**
     * @throws SourceDataException
     */
    private function resolveSide(int $teamId, array $optional, int $matchId): Side
    {
        return match ($teamId) {
            $optional['radiantTeamId'] => Side::RADIANT,
            $optional['direTeamId'] => Side::DIRE,
            default => throw new SourceDataException(
                sprintf('Team %d is not a side of match %d', $teamId, $matchId)
            ),
        };
    }

    /**
     * Счёта нет, пока STRATZ не отдал убийства по минутам (нераспарсенный матч)
     *
     * @param list<int>|null $radiantKills
     * @param list<int>|null $direKills
     *
     * @throws DomainException
     */
    private function createScore(?array $radiantKills, ?array $direKills): ?Score
    {
        if ($radiantKills === null || $direKills === null) {
            return null;
        }

        return new Score(array_sum($radiantKills), array_sum($direKills));
    }

    /**
     * @throws DomainException
     */
    private function createLeagueName(?string $displayName): ?LeagueName
    {
        return $displayName === null || trim($displayName) === '' ? null : new LeagueName($displayName);
    }
}
