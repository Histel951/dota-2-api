<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Team;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Mapping\AbstractMapper;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\PlayerName;
use Histel951\Dota2Api\SharedKernel\WinrateStats;
use Histel951\Dota2Api\Stratz\Team\Domain\Roster\TeamPlayer;
use TypeError;
use ValueError;

/**
 * Контекст: в строку игрока источник добавляет `teamId` — команду, чей состав читаем
 */
final class TeamPlayerMapper extends AbstractMapper
{
    private const array REQUIRED_FIELDS = [
        'steamAccountId',
        'matchCount',
        'winCount',
        'teamId',
    ];

    private const array OPTIONAL_FIELDS = ['steamAccount'];

    /**
     * @throws SourceDataException
     */
    public function toEntity(array $data): TeamPlayer
    {
        try {
            return $this->createTeamPlayer($data);
        } catch (DomainException|TypeError|ValueError $e) {
            throw new SourceDataException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws SourceDataException
     * @throws DomainException
     */
    private function createTeamPlayer(array $data): TeamPlayer
    {
        $this->validate($data, self::REQUIRED_FIELDS);
        $optional = $this->extractOptionalFields($data, self::OPTIONAL_FIELDS);

        $proSteamAccount = $optional['steamAccount']['proSteamAccount'] ?? null;
        $name = $proSteamAccount['name'] ?? $optional['steamAccount']['name'] ?? null;

        return new TeamPlayer(
            playerId: new PlayerId($data['steamAccountId']),
            name: $name === null || trim($name) === '' ? null : new PlayerName($name),
            stats: WinrateStats::fromGames($data['matchCount'], $data['winCount']),
            isCurrentMember: ($proSteamAccount['teamId'] ?? null) === $data['teamId'],
        );
    }
}
