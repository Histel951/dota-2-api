<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Providers\Results;

use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Match\Domain\MatchDetail;
use Throwable;

/**
 * Результат загрузки матча: либо MatchDetail, либо ошибка — чтобы ошибка одного матча не прерывала пакетную загрузку
 */
final readonly class MatchFetchResult
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