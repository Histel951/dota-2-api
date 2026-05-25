<?php

namespace Histel951\Dota2Api\Domain\Providers;

use Generator;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Infrastructure\Provider\Results\MatchResult;

interface MatchesProviderInterface
{
    /**
     * Match data
     *
     * @param MatchId $id
     * @return MatchResult
     */
    public function getMatch(MatchId $id): MatchResult;

    /**
     * Get matches as stream
     *
     * @param array $ids
     * @param int $concurrency
     * @return Generator<MatchResult>
     */
    public function getMatches(array $ids, int $concurrency = 5): Generator;
}