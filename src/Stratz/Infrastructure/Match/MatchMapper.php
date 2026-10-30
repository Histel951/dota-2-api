<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Match;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Mapping\AbstractMapper;
use Histel951\Dota2Api\SharedKernel\Duration;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\SharedKernel\MatchStartTime;
use Histel951\Dota2Api\SharedKernel\Region\Region;
use Histel951\Dota2Api\SharedKernel\Side;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
use Histel951\Dota2Api\Stratz\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\MatchPlayer;
use Histel951\Dota2Api\Stratz\Match\Domain\TeamSide;
use TypeError;
use ValueError;

final class MatchMapper extends AbstractMapper
{
    private const array REQUIRED_FIELDS = [
        'id',
        'didRadiantWin',
        'durationSeconds',
        'startDateTime',
        'players',
    ];

    private const array OPTIONAL_FIELDS = [
        'parsedDateTime',
        'regionId',
        'radiantTeamId',
        'direTeamId',
        'radiantTeam',
        'direTeam',
        'pickBans',
    ];

    public function __construct(
        private readonly PlayerMapper $players = new PlayerMapper(),
        private readonly DraftMapper $draft = new DraftMapper(),
    )
    {
    }

    /**
     * Любая ошибка данных (доменная валидация, неожиданный тип поля) отдаётся как SourceDataException
     *
     * @throws SourceDataException
     */
    public function toEntity(array $data): MatchDetail
    {
        try {
            return $this->createMatch($data);
        } catch (DomainException|TypeError|ValueError $e) {
            throw new SourceDataException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws SourceDataException
     * @throws DomainException
     */
    private function createMatch(array $data): MatchDetail
    {
        $this->validate($data, self::REQUIRED_FIELDS);
        $optional = $this->extractOptionalFields($data, self::OPTIONAL_FIELDS);
        $isParsed = null !== $optional['parsedDateTime'];

        if (!is_array($data['players'])) {
            throw new SourceDataException('Field "players" in API response is not a list');
        }

        $radiantPlayers = [];
        $direPlayers = [];
        foreach ($data['players'] as $player) {
            $matchPlayer = $this->players->map($player, $isParsed);

            if ($matchPlayer->getIdentity()->isRadiant()) {
                $radiantPlayers[] = $matchPlayer;
            } else {
                $direPlayers[] = $matchPlayer;
            }
        }

        return new MatchDetail(
            id: new MatchId($data['id']),
            duration: Duration::fromSeconds($data['durationSeconds']),
            startTime: MatchStartTime::fromTimestamp($data['startDateTime']),
            region: Region::fromId($optional['regionId']),
            radiant: $this->createTeamSide(
                Side::RADIANT,
                $optional['radiantTeamId'],
                $optional['radiantTeam']['name'] ?? null,
                $radiantPlayers,
            ),
            dire: $this->createTeamSide(
                Side::DIRE,
                $optional['direTeamId'],
                $optional['direTeam']['name'] ?? null,
                $direPlayers,
            ),
            winner: $data['didRadiantWin'] ? Side::RADIANT : Side::DIRE,
            draft: $this->draft->map($optional['pickBans']),
            isParsed: $isParsed,
        );
    }

    /**
     * @param list<MatchPlayer> $players
     *
     * @throws DomainException
     */
    private function createTeamSide(Side $side, ?int $teamId, ?string $teamName, array $players): TeamSide
    {
        return new TeamSide(
            side: $side,
            teamId: new TeamId($teamId),
            teamName: new TeamName($teamName),
            players: $players,
        );
    }
}
