<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Team\Domain\History;

use Histel951\Dota2Api\Stratz\Team\Domain\Exception\InvalidScoreException;

/**
 * Счёт матча по убийствам
 */
final readonly class Score
{
    /**
     * @throws InvalidScoreException
     */
    public function __construct(
        private int $radiantKills,
        private int $direKills,
    )
    {
        if ($radiantKills < 0 || $direKills < 0) {
            throw new InvalidScoreException('Score cannot be negative');
        }
    }

    public function getRadiantKills(): int
    {
        return $this->radiantKills;
    }

    public function getDireKills(): int
    {
        return $this->direKills;
    }
}
