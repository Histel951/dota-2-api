<?php

namespace Histel951\Dota2Api\Domain\Providers;

use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Entities\Match\MatchDetail;

interface MatchesProviderInterface
{
    /**
     * Match data
     *
     * @param MatchId $id
     * @return MatchDetail
     */
    public function getMatch(MatchId $id): MatchDetail;
}