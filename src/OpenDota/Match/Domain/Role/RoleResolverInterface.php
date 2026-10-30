<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\OpenDota\Match\Domain\Role;

use Histel951\Dota2Api\OpenDota\Match\Domain\Player\MatchPlayerPerformance;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;

interface RoleResolverInterface
{
    /**
     * @param MatchPlayerPerformance[] $players
     * @param bool $isRadiant нужен, поскольку линия отдаётся с апи только со стороны radiant, поэтому safe lane для dire будет считаться как offlane
     * @return MatchPlayerPerformance[]
     * @throws DomainException
     */
    public function resolve(array $players, bool $isRadiant = true): array;
}