<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Match\Domain\Player;

final readonly class Role
{
    public function __construct(
        private PlayerRole $value
    )
    {
    }

    public function getValue(): PlayerRole
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string)$this->value->value;
    }

    public function toInt(): int
    {
        return $this->value->value;
    }
}