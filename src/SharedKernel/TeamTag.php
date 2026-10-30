<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\SharedKernel;

use Histel951\Dota2Api\SharedKernel\Exception\InvalidTeamTagException;

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