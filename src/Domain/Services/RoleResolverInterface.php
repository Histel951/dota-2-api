<?php

namespace Histel951\Dota2Api\Domain\Services;

use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerPerformance;

interface RoleResolverInterface
{
    /**
     * @param MatchPlayerPerformance[] $players
     * @param bool $isRadiant нужен, поскольку линия отдаётся с апи только со стороны radiant, поэтому safe lane для dire будет считаться как offlane
     * @return MatchPlayerPerformance[]
     */
    public function resolve(array $players, bool $isRadiant = true): array;
}