<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Match\Application;

use Histel951\Dota2Api\Exception\Dota2ApiExceptionInterface;
use Histel951\Dota2Api\Match\Domain\MatchDetail;
use Histel951\Dota2Api\SharedKernel\MatchId;

/**
 * Результат загрузки матча: либо MatchDetail, либо ошибка — чтобы ошибка одного матча не прерывала пакетную загрузку
 */
final readonly class MatchFetchResult
{
    public function __construct(
        private MatchId $matchId,
        private ?MatchDetail $matchDetail,
        private ?Dota2ApiExceptionInterface $error,
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

    public function getError(): ?Dota2ApiExceptionInterface
    {
        return $this->error;
    }
}
