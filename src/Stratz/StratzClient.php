<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz;

use Histel951\Dota2Api\Stratz\Match\Application\MatchService;
use Histel951\Dota2Api\Stratz\Team\Port\TeamSourceInterface;

/**
 * Точка входа в данные STRATZ: матчи и команды.
 */
final readonly class StratzClient
{
    public function __construct(
        private MatchService $matches,
        private TeamSourceInterface $teams,
    )
    {
    }

    public function matches(): MatchService
    {
        return $this->matches;
    }

    public function teams(): TeamSourceInterface
    {
        return $this->teams;
    }
}
