<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\OpenDota\Mapper;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidStatsException;
use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\PlayerName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\WinrateStats;
use Histel951\Dota2Api\Domain\Entities\TeamPlayer\TeamPlayer;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Mapping\AbstractMapper;
use Throwable;

class TeamPlayerOpenDotaMapper extends AbstractMapper
{
    /**
     * Required fields for validation
     */
    private const array REQUIRED_FIELDS = [
        'account_id',
        'games_played',
        'wins'
    ];

    /**
     * name и is_current_team_member у бывших игроков команды приходят null
     */
    private const array OPTIONAL_FIELDS = ['name', 'is_current_team_member'];

    /**
     * @throws SourceDataException
     * @throws InvalidStatsException
     */
    function toEntity(array $data): TeamPlayer
    {
        $this->validate($data, self::REQUIRED_FIELDS);
        $optional = $this->extractOptionalFields($data, self::OPTIONAL_FIELDS);

        $stats = WinrateStats::fromApi($data['games_played'], $data['wins']);

        return $this->createTeamPlayer($data, $optional, $stats);
    }

    /**
     * @throws SourceDataException
     */
    private function createTeamPlayer(array $data, array $optional, WinrateStats $stats): TeamPlayer
    {
        try {
            return new TeamPlayer(
                playerId: new PlayerId($data['account_id']),
                playerProName: $optional['name'] === null ? null : new PlayerName($optional['name']),
                playerStats: $stats,
                isCurrentTeamMember: $optional['is_current_team_member'] ?? false
            );
        } catch (Throwable $e) {
            throw new SourceDataException($e->getMessage(), $e->getCode(), $e);
        }
    }
}