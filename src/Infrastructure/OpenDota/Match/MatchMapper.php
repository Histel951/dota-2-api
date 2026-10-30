<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\OpenDota\Match;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Mapping\AbstractMapper;
use Histel951\Dota2Api\Match\Domain\Draft\Draft;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidCounterException;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidDraftOrderException;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidGPMException;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidKDAException;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidXPMException;
use Histel951\Dota2Api\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Match\Domain\MatchStartTime;
use Histel951\Dota2Api\Match\Domain\Player\MatchPlayers;
use Histel951\Dota2Api\Match\Domain\Region\Region;
use Histel951\Dota2Api\Match\Domain\Side\Side;
use Histel951\Dota2Api\Match\Domain\Side\TeamSide;
use Histel951\Dota2Api\SharedKernel\Duration;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidDurationException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidHeroIdException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidMatchIdException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidPlayerIdException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidPlayerNameException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidTeamIdException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidTeamNameException;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
use TypeError;
use ValueError;

final class MatchMapper extends AbstractMapper
{
    public function __construct(
        private readonly PlayerMapper $players = new PlayerMapper(),
        private readonly DraftMapper $draft = new DraftMapper(),
    )
    {
    }
    private const array REQUIRED_FIELDS = [
        'match_id',
        'duration',
        'radiant_win',
        'players',
    ];

    private const array OPTIONAL_FIELDS = [
        'radiant_team_id',
        'radiant_name',
        'dire_team_id',
        'dire_name',
        'picks_bans',
        'region',
    ];

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
     * @throws InvalidXPMException
     * @throws InvalidGPMException
     * @throws InvalidDurationException
     * @throws InvalidKDAException
     * @throws InvalidHeroIdException
     * @throws InvalidMatchIdException
     * @throws SourceDataException
     * @throws InvalidPlayerIdException
     * @throws InvalidTeamIdException
     * @throws InvalidTeamNameException
     * @throws InvalidDraftOrderException
     * @throws InvalidPlayerNameException
     * @throws InvalidCounterException
     * @throws DomainException
     */
    private function createMatch(array $data): MatchDetail
    {
        $this->validate($data, self::REQUIRED_FIELDS);
        $optional = $this->extractOptionalFields($data, self::OPTIONAL_FIELDS);

        $radiantPlayers = [];
        $direPlayers = [];
        foreach ($data['players'] as $player) {
            $playerEntity = $this->players->map($player, $data['objectives'] ?? []);

            if ($playerEntity->getIdentity()->isRadiant()) {
                $radiantPlayers[] = $playerEntity;
            } else {
                $direPlayers[] = $playerEntity;
            }
        }

        $picksBans = $optional['picks_bans'];

        $radiantTeam = $this->createTeamSide(
            teamId: $optional['radiant_team_id'],
            teamName: $optional['radiant_name'],
            won: $data['radiant_win'],
            draft: $this->draft->map($this->filterBySide($picksBans, Side::RADIANT)),
            players: $radiantPlayers,
            side: Side::RADIANT,
        );

        $direTeam = $this->createTeamSide(
            teamId: $optional['dire_team_id'],
            teamName: $optional['dire_name'],
            won: !$data['radiant_win'],
            draft: $this->draft->map($this->filterBySide($picksBans, Side::DIRE)),
            players: $direPlayers,
            side: Side::DIRE,
        );

        return new MatchDetail(
            id: new MatchId($data['match_id']),
            duration: Duration::fromSeconds($data['duration']),
            radiant: $radiantTeam,
            dire: $direTeam,
            draft: $this->draft->map($picksBans),
            startTime: MatchStartTime::fromTimestamp($data['start_time']),
            region: Region::fromId($optional['region']),
        );
    }

    /**
     * Оставляет решения драфта одной стороны (null остаётся null — драфта нет)
     */
    private function filterBySide(?array $picksBans, Side $side): ?array
    {
        if (null === $picksBans) {
            return null;
        }

        return array_values(array_filter(
            $picksBans,
            static fn(array $item): bool => Side::from($item['team']) === $side,
        ));
    }

    /**
     * @param MatchPlayerPerformance[] $players
     *
     * @throws InvalidTeamIdException
     * @throws InvalidTeamNameException
     */
    private function createTeamSide(
        ?int    $teamId,
        ?string $teamName,
        bool    $won,
        Draft   $draft,
        array   $players,
        Side    $side,
    ): TeamSide
    {
        return new TeamSide(
            teamId: new TeamId($teamId),
            teamName: new TeamName($teamName),
            won: $won,
            draft: $draft,
            players: new MatchPlayers($players),
            side: $side,
        );
    }
}
