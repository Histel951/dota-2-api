<?php

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Enums\RegionType;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidRegionException;

final readonly class Region
{
    public function __construct(
        private int $id,
        private string $name,
        private string $code,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->id < 0) {
            throw InvalidRegionException::invalidRegionId($this->id);
        }

        if (empty($this->name)) {
            throw InvalidRegionException::emptyName();
        }

        if (empty($this->code)) {
            throw InvalidRegionException::emptyCode();
        }
    }

    public function fromId(int $id): self
    {
        $regionType = RegionType::tryFrom($id);

        if (null === $regionType) {
            throw InvalidRegionException::invalidRegionId($id);
        }

        return new self(
            id: $regionType->value,
            name: $regionType->label(),
            code: $regionType->code()
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public static function unknown(): self
    {
        return new self(
            id: RegionType::UNKNOWN->value,
            name: RegionType::UNKNOWN->label(),
            code: RegionType::UNKNOWN->code()
        );
    }
}
