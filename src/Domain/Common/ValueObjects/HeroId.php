<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidHeroIdException;

final readonly class HeroId
{
    private const int MIN_ID = 1;
    private const int MAX_ID = 127;

    private int $value;

    /**
     * @throws InvalidHeroIdException
     */
    public function __construct(int $value)
    {
        if ($value < self::MIN_ID) {
            throw new InvalidHeroIdException(
                sprintf('Hero ID must be at least %d, %d given', self::MIN_ID, $value)
            );
        }

        if ($value > self::MAX_ID) {
            throw new InvalidHeroIdException(
                sprintf('Hero ID cannot exceed %d, %d given', self::MAX_ID, $value)
            );
        }

        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}