<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Domain;

use Histel951\Dota2Api\Stratz\Match\Domain\Exception\InvalidMatchSideException;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\MatchPlayer;
use Histel951\Dota2Api\SharedKernel\Draft\Draft;
use Histel951\Dota2Api\SharedKernel\Duration;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\SharedKernel\MatchStartTime;
use Histel951\Dota2Api\SharedKernel\Region\Region;
use Histel951\Dota2Api\SharedKernel\Side;

final readonly class MatchDetail
{
    /**
     * @param bool $isParsed матч обработан источником (доступны линии, позиции, драфт и ParsedStats)
     *
     * @throws InvalidMatchSideException стороны перепутаны местами
     */
    public function __construct(
        private MatchId $id,
        private Duration $duration,
        private MatchStartTime $startTime,
        private Region $region,
        private TeamSide $radiant,
        private TeamSide $dire,
        private Side $winner,
        private ?Draft $draft,
        private bool $isParsed,
    )
    {
        if (!$radiant->isRadiant()) {
            throw new InvalidMatchSideException('Radiant team must have the radiant side');
        }

        if ($dire->isRadiant()) {
            throw new InvalidMatchSideException('Dire team must have the dire side');
        }
    }

    public function getId(): MatchId
    {
        return $this->id;
    }

    public function getDuration(): Duration
    {
        return $this->duration;
    }

    public function getStartTime(): MatchStartTime
    {
        return $this->startTime;
    }

    public function getRegion(): Region
    {
        return $this->region;
    }

    public function getRadiant(): TeamSide
    {
        return $this->radiant;
    }

    public function getDire(): TeamSide
    {
        return $this->dire;
    }

    public function getWinner(): TeamSide
    {
        return $this->winner === Side::RADIANT ? $this->radiant : $this->dire;
    }

    /**
     * null у нераспарсенного матча
     */
    public function getDraft(): ?Draft
    {
        return $this->draft;
    }

    public function isParsed(): bool
    {
        return $this->isParsed;
    }

    /**
     * @return list<MatchPlayer> [...radiant, ...dire]
     */
    public function getPlayers(): array
    {
        return [...$this->radiant->getPlayers(), ...$this->dire->getPlayers()];
    }
}
