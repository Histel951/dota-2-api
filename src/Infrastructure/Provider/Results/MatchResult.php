<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Provider\Results;

use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Entities\Match\MatchDetail;
use Throwable;

final readonly class MatchResult
{
    public function __construct(
        private MatchId      $matchId,
        private ?MatchDetail $matchDetail,
        private ?Throwable   $error,
    )
    {
    }

    public function getMatchId(): MatchId
    {
        return $this->matchId;
    }

    public function getMatchDetail(): ?MatchDetail
    {
        return $this->matchDetail;
    }

    public function getError(): ?Throwable
    {
        return $this->error;
    }
}