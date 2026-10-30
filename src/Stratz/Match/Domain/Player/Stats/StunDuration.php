<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats;

use Histel951\Dota2Api\Stratz\Match\Domain\Exception\InvalidStunDurationException;

final readonly class StunDuration
{
    /**
     * @throws InvalidStunDurationException
     */
    public function __construct(
        private float $seconds,
    )
    {
        if ($seconds < 0) {
            throw new InvalidStunDurationException('Stun duration cannot be negative');
        }
    }

    public function getSeconds(): float
    {
        return $this->seconds;
    }
}
