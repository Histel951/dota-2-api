<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Mapper\Team;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidStatsException;
use Histel951\Dota2Api\Domain\Common\ValueObjects\HeroId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\HeroName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\WinrateStats;
use Histel951\Dota2Api\Domain\Entities\TeamHero\TeamHero;
use Histel951\Dota2Api\Infrastructure\Exceptions\MappingException;
use Histel951\Dota2Api\Infrastructure\Mapper\AbstractMapper;
use Throwable;

class TeamHeroOpenDotaMapper extends AbstractMapper
{
    private const array REQUIRED_FIELDS = [
        'hero_id',
        'localized_name',
        'games_played',
        'wins'
    ];

    /**
     * @throws MappingException
     * @throws InvalidStatsException
     */
    public function toEntity(array $data): TeamHero
    {
        $this->validate($data, self::REQUIRED_FIELDS);

        $stats = WinrateStats::fromApi($data['games_played'], $data['wins']);

        return $this->createTeamHero($data, $stats);
    }

    /**
     * @throws MappingException
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
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}