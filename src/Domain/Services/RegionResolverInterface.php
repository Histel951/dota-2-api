<?php

namespace Histel951\Dota2Api\Domain\Services;

use Histel951\Dota2Api\Domain\Common\Enums\RegionType;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Region;

interface RegionResolverInterface
{
    public function resolve(RegionType|int|null $region): Region;
}
