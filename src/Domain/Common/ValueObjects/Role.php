<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Enums\RoleEnum;

final readonly class Role
{
    public function __construct(
        private RoleEnum $value
    )
    {
    }

    public function getValue(): RoleEnum
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