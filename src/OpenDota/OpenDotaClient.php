<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota;

use Histel951\Dota2Api\OpenDota\Match\Application\MatchService;
use Histel951\Dota2Api\OpenDota\Team\Port\TeamSourceInterface;

/**
 * Точка входа в данные OpenDota: матчи и команды.
 */
final readonly class OpenDotaClient
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
