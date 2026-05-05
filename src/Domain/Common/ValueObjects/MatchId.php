<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidMatchIdException;

final readonly class MatchId
{
    /**
     * @throws InvalidMatchIdException
     */
    public function __construct(
        private int $value,
    )
    {
        if ($value < 0) {
            throw new InvalidMatchIdException('Match id must be greater than 0');
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function equals(self $matchId): bool
    {
        return $this->value === $matchId->getValue();
    }

    public function __toString(): string
    {
        return (string)$this->getValue();
    }
}