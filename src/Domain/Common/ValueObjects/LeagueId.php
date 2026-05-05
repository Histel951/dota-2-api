<?php

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidLeagueIdException;

final readonly class LeagueId
{
    /**
     * @throws InvalidLeagueIdException
     */
    public function __construct(
        private int $value,
    )
    {
        if ($value < 0) {
            throw new InvalidLeagueIdException('League ID must be positive integer');
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function equals(self $leagueId): bool
    {
        return $this->value === $leagueId->getValue();
    }

    public function __toString(): string
    {
        return (string) $this->getValue();
    }
}