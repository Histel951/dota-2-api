<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Client;

use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Domain\Providers\TeamProviderInterface;

final class Dota2ApiClient
{
    public function __construct(
        private readonly MatchesProviderInterface $matchesProvider,
        private readonly TeamProviderInterface $teamProvider,
    )
    {
    }

    public function matches(): MatchesProviderInterface
    {
        return $this->matchesProvider;
    }

    public function teams(): TeamProviderInterface
    {
        return $this->teamProvider;
    }
}