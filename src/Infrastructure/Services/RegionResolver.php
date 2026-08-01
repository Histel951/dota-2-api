<?php

namespace Histel951\Dota2Api\Infrastructure\Services;

use Histel951\Dota2Api\Domain\Common\ValueObjects\Region;
use Histel951\Dota2Api\Domain\Services\RegionResolverInterface;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\RegionType;

final readonly class RegionResolver implements RegionResolverInterface
{
    /**
     * @var array<int, Region>
     */
    private array $regions;

    public function __construct()
    {
        $regions = [];

        foreach (RegionType::cases() as $region) {
            $regions[$region->value] = new Region(
                id: $region->value,
                name: $region->label(),
                code: $region->code(),
            );
        }

        $this->regions = $regions;
    }

    public function resolve(RegionType|int|null $region): Region
    {
        if ($region === null) {
            return Region::unknown();
        }

        $regionId = $region instanceof RegionType
            ? $region->value
            : $region;

        return $this->regions[$regionId] ?? Region::unknown();
    }
}