<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure;

use Histel951\Dota2Api\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\Team\Port\TeamSourceInterface;

/**
 * Создаёт адаптеры источника. MatchService собирает composition root.
 */
interface SourceFactoryInterface
{
    public function createMatchSource(): MatchSourceInterface;

    public function createTeamSource(): TeamSourceInterface;
}
