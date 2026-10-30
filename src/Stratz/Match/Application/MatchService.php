<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Application;

use Generator;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Stratz\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Stratz\Match\Port\MatchSourceInterface;

/**
 * Сценарии загрузки матчей STRATZ. Роли берутся из источника, резолвер не нужен.
 */
final readonly class MatchService
{
    public function __construct(
        private MatchSourceInterface $source,
    )
    {
    }

    /**
     * Ошибки данных возвращаются в результате, ошибки HTTP бросаются.
     *
     * @param bool $withPlaybackData false — без playbackData (байбеки), ParsedStats::getBuybacks() = null
     *
     * @throws SourceRequestException
     */
    public function getMatch(MatchId $id, bool $withPlaybackData = false): MatchFetchResult
    {
        try {
            return new MatchFetchResult($id, $this->source->getMatch($id, $withPlaybackData), null);
        } catch (SourceDataException $e) {
            return new MatchFetchResult($id, null, $e);
        }
    }

    /**
     * Пакетная загрузка; ошибки HTTP и данных одного матча попадают в getError().
     * Без playbackData матчи идут по 5 в запросе вместо 1, ParsedStats::getBuybacks() = null.
     *
     * @param list<MatchId> $ids
     * @param positive-int $concurrency
     * @param bool $withPlaybackData false — без playbackData (байбеки)
     * @return Generator<int, MatchFetchResult, mixed, void>
     */
    public function getMatches(array $ids, int $concurrency = 5, bool $withPlaybackData = false): Generator
    {
        foreach ($this->source->getMatches($ids, $concurrency, $withPlaybackData) as $id => $outcome) {
            yield $outcome instanceof MatchDetail
                ? new MatchFetchResult($id, $outcome, null)
                : new MatchFetchResult($id, null, $outcome);
        }
    }
}
