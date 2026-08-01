<?php

namespace Histel951\Dota2Api\Domain\Common\Exceptions;

use InvalidArgumentException;

class InvalidRegionException extends InvalidArgumentException
{
    public static function invalidRegionId(int $regionId): self
    {
        return new self(sprintf('Invalid region ID: %d', $regionId));
    }

    public static function emptyName(): self
    {
        return new self('Region name cannot be empty');
    }

    public static function emptyCode(): self
    {
        return new self('Region code cannot be empty');
    }
}
