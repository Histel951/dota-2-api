<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidPlayerProNameException;

final readonly class PlayerProName
{
    private string $value;

    /**
     * @throws InvalidPlayerProNameException
     */
    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidPlayerProNameException('Player pro name cannot be empty');
        }

        if (strlen($normalized) < 2) {
            throw new InvalidPlayerProNameException('Player pro name must be at least 2 characters');
        }

        if (strlen($normalized) > 50) {
            throw new InvalidPlayerProNameException('Player pro name cannot exceed 50 characters');
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