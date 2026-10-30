<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Team;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Mapping\AbstractMapper;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
use Histel951\Dota2Api\SharedKernel\TeamTag;
use Histel951\Dota2Api\SharedKernel\WinrateStats;
use Histel951\Dota2Api\Stratz\Team\Domain\Team;
use TypeError;
use ValueError;

final class TeamMapper extends AbstractMapper
{
    private const array REQUIRED_FIELDS = [
        'id',
        'winCount',
        'lossCount',
        'lastMatchDateTime',
    ];

    private const array OPTIONAL_FIELDS = ['name', 'tag'];

    /**
     * @throws SourceDataException
     */
    public function toEntity(array $data): Team
    {
        try {
            return $this->createTeam($data);
        } catch (DomainException|TypeError|ValueError $e) {
            throw new SourceDataException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws SourceDataException
     * @throws DomainException
     */
    private function createTeam(array $data): Team
    {
        $this->validate($data, self::REQUIRED_FIELDS);
        $optional = $this->extractOptionalFields($data, self::OPTIONAL_FIELDS);

        return new Team(
            id: new TeamId($data['id']),
            name: $this->isBlank($optional['name']) ? null : new TeamName($optional['name']),
            tag: $this->isBlank($optional['tag']) ? null : new TeamTag($optional['tag']),
            record: WinrateStats::fromGames($data['winCount'] + $data['lossCount'], $data['winCount']),
            lastMatchTime: $this->createDateTime($data['lastMatchDateTime']),
        );
    }

    /**
     * Пустое или отсутствующее значение — «имени/тега нет», а не ошибка данных
     */
    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}
