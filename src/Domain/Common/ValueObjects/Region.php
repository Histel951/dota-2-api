<?php

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

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
        return new self(0, 'Unknown', 'unknown');
    }
}
