<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Client;

use Histel951\Dota2Api\Match\Application\MatchService;
use Histel951\Dota2Api\Team\Port\TeamSourceInterface;

final readonly class Dota2ApiClient
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
