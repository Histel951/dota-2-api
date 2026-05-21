<?php

namespace Histel951\Dota2Api\Domain\Services;

use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerPerformance;

// todo: в будущем убрать подсчёт ролей, сделать это в аналитическом сервисе
interface RoleResolverInterface
{
    /**
     * @param MatchPlayerPerformance[] $players
     * @return MatchPlayerPerformance[]
     */
    public function resolve(array $players): array;
}