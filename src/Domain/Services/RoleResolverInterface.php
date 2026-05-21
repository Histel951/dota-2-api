<?php

namespace Histel951\Dota2Api\Domain\Services;

use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerPerformance;

interface RoleResolverInterface
{
    /**
     * @param MatchPlayerPerformance[] $players
     * @return MatchPlayerPerformance[]
     */
    public function resolve(array $players): array;
}