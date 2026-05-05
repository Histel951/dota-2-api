<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Mapper\Team;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidStatsException;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerProName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\WinrateStats;
use Histel951\Dota2Api\Domain\Entities\TeamPlayer\TeamPlayer;
use Histel951\Dota2Api\Infrastructure\Exceptions\MappingException;
use Histel951\Dota2Api\Infrastructure\Mapper\AbstractMapper;
use Throwable;

final class TeamPlayerOpenDotaMapper extends AbstractMapper
{
    /**
     * Required fields for validation
     */
    private const array REQUIRED_FIELDS = [
        'account_id',
        'games_played',
        'wins',
        'is_current_team_member'
    ];

    private const array OPTIONAL_FIELDS = ['name'];

    /**
     * @throws MappingException
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
     * @throws MappingException
     */
    private function createTeamPlayer(array $data, array $optional, WinrateStats $stats): TeamPlayer
    {
        try {
            return new TeamPlayer(
                playerId: new PlayerId($data['account_id']),
                playerProName: new PlayerProName($optional['name']),
                playerStats: $stats,
                isCurrentTeamMember: $data['is_current_team_member']
            );
        } catch (Throwable $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}