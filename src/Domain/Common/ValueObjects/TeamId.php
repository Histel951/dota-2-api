<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidTeamIdException;

final readonly class TeamId
{
    private ?string $value;

    /**
     * @throws InvalidTeamIdException
     */
    public function __construct(
        string|int $value = null
    )
    {
        if (null === $value) {
            $this->value = null;
            return;
        }

        $normalized = (string) $value;

        if (trim($normalized) === '') {
            throw new InvalidTeamIdException('Team ID cannot be empty');
        }

        if (!ctype_digit($normalized)) {
            throw new InvalidTeamIdException(
                sprintf('Team ID must be numeric, "%s" given', $normalized)
            );
        }

        $this->value = $normalized;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function toInt(): int
    {
        return (int) $this->value;
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