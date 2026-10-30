<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Match\Port;

use Generator;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Match\Domain\MatchDetail;
use Histel951\Dota2Api\SharedKernel\MatchId;

/**
 * Порт источника матчей. Роли игроков не резолвит: отдаёт то, что есть у источника
 * (position_est или UNKNOWN), резолв ролей — в Application.
 */
interface MatchSourceInterface
{
    /**
     * Матч с ролями от источника.
     *
     * @throws SourceRequestException транспорт, 404 ("Object not found"), иной не-200
     * @throws SourceDataException тело не JSON-массив или данные не маппятся (previous — причина)
     */
    public function getMatch(MatchId $id): MatchDetail;

    /**
     * Пакетная загрузка: до $concurrency одновременных запросов, результаты по готовности,
     * порядок не гарантирован. Ошибка одного матча отдаётся значением, поток не прерывается;
     * сам генератор исключений бандла не бросает.
     *
     * @param list<MatchId> $ids
     * @param positive-int $concurrency
     * @return Generator<MatchId, MatchDetail|SourceRequestException|SourceDataException, mixed, void>
     */
    public function getMatches(array $ids, int $concurrency): Generator;
}
