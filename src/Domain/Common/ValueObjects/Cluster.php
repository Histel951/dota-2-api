<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidClusterException;

final readonly class Cluster
{
    /**
     * @throws InvalidClusterException
     */
    public function __construct(
        private int $value,
    )
    {
        if ($value < 0) {
            throw new InvalidClusterException('Cluster must be greater than 0');
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function equals(self $cluster): bool
    {
        return $this->value === $cluster->getValue();
    }

    public function __toString(): string
    {
        return (string) $this->getValue();
    }
}