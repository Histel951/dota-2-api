<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Domain\Providers;

use Generator;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Domain\Providers\Results\MatchFetchResult;

interface MatchesProviderInterface
{
    /**
     * Match data
     *
     * @param MatchId $id
     * @return MatchFetchResult
     */
    public function getMatch(MatchId $id): MatchFetchResult;

    /**
     * Get matches as stream
     *
     * @param array $ids
     * @param int $concurrency
     * @return Generator<MatchFetchResult>
     */
    public function getMatches(array $ids, int $concurrency = 5): Generator;
}