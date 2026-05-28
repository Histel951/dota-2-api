<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;

final readonly class MatchStartTime
{
    public function __construct(
        private DateTimeImmutable $value
    ) {}

    public static function fromTimestamp(int $timestamp): self
    {
        return new self(
            (new DateTimeImmutable(
                timezone: new DateTimeZone('UTC')
            ))->setTimestamp($timestamp)
        );
    }

    public function getValue(): DateTimeImmutable
    {
        return $this->value;
    }
}