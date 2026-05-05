<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidStatsException;

final readonly class WinrateStats
{
    private int $gamesPlayed;
    private int $gamesWon;
    private int $gamesLost;
    private float $winrate;

    /**
     * @throws InvalidStatsException
     */
    private function __construct(
        int $gamesPlayed,
        int $gamesWon,
    ) {
        if ($gamesPlayed < 0) {
            throw new InvalidStatsException('Games played cannot be negative');
        }

        if ($gamesWon < 0) {
            throw new InvalidStatsException('Games won cannot be negative');
        }

        if ($gamesWon > $gamesPlayed) {
            throw new InvalidStatsException('Games won cannot exceed games played');
        }

        $this->gamesPlayed = $gamesPlayed;
        $this->gamesWon = $gamesWon;
        $this->gamesLost = $gamesPlayed - $gamesWon;

        $this->winrate = $gamesPlayed > 0
            ? round(($gamesWon / $gamesPlayed) * 100, 2)
            : 0.0;
    }

    /**
     * @throws InvalidStatsException
     */
    public static function fromApi(int $gamesPlayed, int $wins): self
    {
        return new self($gamesPlayed, $wins);
    }

    public function getGamesPlayed(): int
    {
        return $this->gamesPlayed;
    }

    public function getGamesWon(): int
    {
        return $this->gamesWon;
    }

    public function getGamesLost(): int
    {
        return $this->gamesLost;
    }

    public function getWinrate(): float
    {
        return $this->winrate;
    }

    public function equals(self $other): bool
    {
        return $this->gamesPlayed === $other->gamesPlayed
            && $this->gamesWon === $other->gamesWon;
    }
}