<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Client;

use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Team\Port\TeamSourceInterface;

final class Dota2ApiClient
{
    public function __construct(
        private readonly MatchesProviderInterface $matchesProvider,
        private readonly TeamSourceInterface $teamProvider,
    )
    {
    }

    public function matches(): MatchesProviderInterface
    {
        return $this->matchesProvider;
    }

    public function teams(): TeamSourceInterface
    {
        return $this->teamProvider;
    }
}