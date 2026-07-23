<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidTeamNameException;

final readonly class TeamName
{
    private ?string $value;

    /**
     * @throws InvalidTeamNameException
     */
    public function __construct(
        ?string $value
    )
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidTeamNameException('Team name cannot be empty');
        }

        if (preg_match('/\s{2,}/', $normalized)) {
            throw new InvalidTeamNameException('Team name cannot contain multiple spaces in a row');
        }

        $this->value = $normalized;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function equals(self $teamName): bool
    {
        return $this->value === $teamName->getValue();
    }

    public function __toString(): string
    {
        if (null === $this->value) {
            return '';
        }

        return $this->value;
    }
}