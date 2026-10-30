<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Team\Domain;

use Histel951\Dota2Api\Team\Domain\Exception\InvalidTeamStatsException;

final readonly class TeamStats
{
    /**
     * @throws InvalidTeamStatsException
     */
    private function __construct(
        private int $wins,
        private int $losses,
        private float $rating,
    )
    {
        if ($wins < 0 || $losses < 0) {
            throw new InvalidTeamStatsException('Value must be positive');
        }
    }

    public function getRating(): float
    {
        return $this->rating;
    }

    public function getWins(): int
    {
        return $this->wins;
    }

    public function getLosses(): int
    {
        return $this->losses;
    }

    /**
     * @throws InvalidTeamStatsException
     */
    public static function fromRecord(int $wins, int $losses, float $rating): self
    {
        return new self($wins, $losses, $rating);
    }
}