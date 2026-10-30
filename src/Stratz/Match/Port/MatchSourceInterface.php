<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Port;

use Generator;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Stratz\Match\Domain\MatchDetail;

/**
 * Порт источника матчей STRATZ (GraphQL, корневое поле match). Роли игроков берутся
 * из позиций источника (position), отдельный резолвер ролей не нужен.
 */
interface MatchSourceInterface
{
    /**
     * Один матч.
     *
     * @throws SourceRequestException транспорт, не-200, ошибка GraphQL, матч не найден ("Object not found")
     * @throws SourceDataException тело не JSON или данные не маппятся (previous — причина)
     */
    public function getMatch(MatchId $id): MatchDetail;

    /**
     * Пакетная загрузка: id объединяются в GraphQL-запросы по нескольку матчей, одновременно
     * выполняется до $concurrency HTTP-запросов; результаты по готовности, порядок не гарантирован.
     * Ошибка запроса отдаётся значением на каждый id его пачки, ошибка поля или маппинга — только
     * у своего id; поток не прерывается, дубли id не теряются. Сам генератор исключений бандла не бросает.
     *
     * @param list<MatchId> $ids
     * @param positive-int $concurrency
     * @return Generator<MatchId, MatchDetail|SourceRequestException|SourceDataException, mixed, void>
     */
    public function getMatches(array $ids, int $concurrency): Generator;
}
