<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Match\Application;

use Generator;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Match\Domain\Role\RoleResolverInterface;
use Histel951\Dota2Api\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\SharedKernel\MatchId;

/**
 * Сценарии загрузки матчей: берёт матч у источника и резолвит роли игроков.
 */
final readonly class MatchService
{
    public function __construct(
        private MatchSourceInterface $source,
        private RoleResolverInterface $roleResolver,
    )
    {
    }

    /**
     * Ошибки данных и ролей возвращаются в результате, ошибки HTTP бросаются.
     *
     * @throws SourceRequestException
     */
    public function getMatch(MatchId $id): MatchFetchResult
    {
        try {
            $match = $this->source->getMatch($id);
        } catch (SourceDataException $e) {
            return new MatchFetchResult($id, null, $e);
        }

        return $this->resolve($id, $match);
    }

    /**
     * Пакетная загрузка; ошибки HTTP, данных и ролей одного матча попадают в getError().
     *
     * @param list<MatchId> $ids
     * @param positive-int $concurrency
     * @return Generator<int, MatchFetchResult, mixed, void>
     */
    public function getMatches(array $ids, int $concurrency = 5): Generator
    {
        foreach ($this->source->getMatches($ids, $concurrency) as $id => $outcome) {
            yield $outcome instanceof MatchDetail
                ? $this->resolve($id, $outcome)
                : new MatchFetchResult($id, null, $outcome);
        }
    }

    private function resolve(MatchId $id, MatchDetail $match): MatchFetchResult
    {
        try {
            return new MatchFetchResult($id, $match->withResolvedRoles($this->roleResolver), null);
        } catch (DomainException $e) {
            return new MatchFetchResult($id, null, $e);
        }
    }
}
