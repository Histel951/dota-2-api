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
 *
 * Режим $withPlaybackData: true — запрашивается playbackData (байбеки), STRATZ пересобирает его
 * не более чем для одного незакэшированного матча за запрос, поэтому матчи идут по одному в запросе;
 * false — playbackData не запрашивается, матчи идут по 5 в запросе, а ParsedStats::getBuybacks() = null.
 */
interface MatchSourceInterface
{
    /**
     * Один матч.
     *
     * @param bool $withPlaybackData false — без playbackData, ParsedStats::getBuybacks() = null
     *
     * @throws SourceRequestException транспорт, не-200, ошибка GraphQL, матч не найден ("Object not found")
     * @throws SourceDataException тело не JSON или данные не маппятся (previous — причина)
     */
    public function getMatch(MatchId $id, bool $withPlaybackData): MatchDetail;

    /**
     * Пакетная загрузка: id объединяются в GraphQL-запросы по нескольку матчей (с playbackData — по 1,
     * без — по 5), одновременно выполняется до $concurrency HTTP-запросов; результаты по готовности,
     * порядок не гарантирован. Ошибка запроса отдаётся значением на каждый id его пачки, ошибка поля
     * или маппинга — только у своего id; поток не прерывается, дубли id не теряются. Сам генератор
     * исключений бандла не бросает.
     *
     * @param list<MatchId> $ids
     * @param positive-int $concurrency
     * @param bool $withPlaybackData false — без playbackData: по 5 матчей в запросе, ParsedStats::getBuybacks() = null
     * @return Generator<MatchId, MatchDetail|SourceRequestException|SourceDataException, mixed, void>
     */
    public function getMatches(array $ids, int $concurrency, bool $withPlaybackData): Generator;
}
