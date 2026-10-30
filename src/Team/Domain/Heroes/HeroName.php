<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Team\Domain\Heroes;

use Histel951\Dota2Api\Team\Domain\Exception\InvalidHeroNameException;

final readonly class HeroName
{
    private string $value;

    /**
     * @throws InvalidHeroNameException
     */
    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidHeroNameException('Hero name cannot be empty');
        }

        if (strlen($normalized) < 2) {
            throw new InvalidHeroNameException('Hero name must be at least 2 characters');
        }

        if (strlen($normalized) > 50) {
            throw new InvalidHeroNameException('Hero name cannot exceed 50 characters');
        }

        $this->value = $normalized;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}