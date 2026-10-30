<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Team;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Mapping\AbstractMapper;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\SharedKernel\HeroName;
use Histel951\Dota2Api\SharedKernel\WinrateStats;
use Histel951\Dota2Api\Stratz\Team\Domain\Heroes\TeamHero;
use TypeError;
use ValueError;

/**
 * Контекст: в строку героя источник добавляет `displayName` из constants.heroes
 */
final class TeamHeroMapper extends AbstractMapper
{
    private const array REQUIRED_FIELDS = [
        'heroId',
        'matchCount',
        'winCount',
        'displayName',
    ];

    /**
     * @throws SourceDataException
     */
    public function toEntity(array $data): TeamHero
    {
        try {
            return $this->createTeamHero($data);
        } catch (DomainException|TypeError|ValueError $e) {
            throw new SourceDataException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws SourceDataException
     * @throws DomainException
     */
    private function createTeamHero(array $data): TeamHero
    {
        $this->validate($data, self::REQUIRED_FIELDS);

        return new TeamHero(
            heroId: new HeroId($data['heroId']),
            name: new HeroName($data['displayName']),
            stats: WinrateStats::fromGames($data['matchCount'], $data['winCount']),
        );
    }
}
