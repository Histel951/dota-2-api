<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Team\ValueObjects;

use Histel951\Dota2Api\Domain\Entities\Team\Exceptions\InvalidTeamTagException;

final readonly class TeamTag
{
    private string $value;

    /**
     * @throws InvalidTeamTagException
     */
    public function __construct(
        string $value
    )
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidTeamTagException('Team tag cannot be empty');
        }

        if ($normalized !== $value) {
            throw new InvalidTeamTagException('Team name cannot have leading or trailing spaces');
        }

        $this->value = $normalized;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $teamTag): bool
    {
        return $this->value === $teamTag->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}