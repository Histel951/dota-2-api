<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\SharedKernel;

use Histel951\Dota2Api\SharedKernel\Exception\InvalidLeagueNameException;

final readonly class LeagueName
{
    private string $value;

    /**
     * @throws InvalidLeagueNameException
     */
    public function __construct(
        string $value,
    )
    {
        $normalized = trim($value);
        if ($normalized === '') {
            throw new InvalidLeagueNameException('League name cannot be empty');
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