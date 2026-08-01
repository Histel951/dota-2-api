<?php

namespace Histel951\Dota2Api\Domain\Services;

use Histel951\Dota2Api\Domain\Common\ValueObjects\Region;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\RegionType;

interface RegionResolverInterface
{
    public function resolve(RegionType|int|null $region): Region;
}
