<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\OpenDota\Team;

use Histel951\Dota2Api\Team\Domain\Exception\InvalidStatsException;
use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\Team\Domain\Heroes\HeroName;
use Histel951\Dota2Api\Team\Domain\WinrateStats;
use Histel951\Dota2Api\Team\Domain\Heroes\TeamHero;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Mapping\AbstractMapper;
use Throwable;

class TeamHeroMapper extends AbstractMapper
{
    private const array REQUIRED_FIELDS = [
        'hero_id',
        'localized_name',
        'games_played',
        'wins'
    ];

    /**
     * @throws SourceDataException
     * @throws InvalidStatsException
     */
    public function toEntity(array $data): TeamHero
    {
        $this->validate($data, self::REQUIRED_FIELDS);

        $stats = WinrateStats::fromGames($data['games_played'], $data['wins']);

        return $this->createTeamHero($data, $stats);
    }

    /**
     * @throws SourceDataException
     */
    private function createTeamHero(array $data, WinrateStats $stats): TeamHero
    {
        try {
            return new TeamHero(
                id: new HeroId($data['hero_id']),
                name: new HeroName($data['localized_name']),
                stats: $stats
            );
        } catch (Throwable $e) {
            throw new SourceDataException($e->getMessage(), $e->getCode(), $e);
        }
    }
}