<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidKDAException;

final readonly class KDA
{
    /**
     * @throws InvalidKDAException
     */
    public function __construct(
        private int $kills = 0,
        private int $deaths = 0,
        private int $assists = 0,
    )
    {
        if ($kills < 0 || $deaths < 0 || $assists < 0) {
            throw new InvalidKDAException('KDA must be a positive integer or 0');
        }
    }

    public function getKills(): int
    {
        return $this->kills;
    }

    public function getDeaths(): int
    {
        return $this->deaths;
    }

    public function getAssists(): int
    {
        return $this->assists;
    }

    public function getCoefficient(): float
    {
        return round($this->kills + $this->assists / $this->deaths, 2);
    }
}