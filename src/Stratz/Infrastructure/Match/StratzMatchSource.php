<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Match;

use Generator;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Infrastructure\Http\ConcurrentRequests;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlGateway;
use Histel951\Dota2Api\Stratz\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Stratz\Match\Port\MatchSourceInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Источник матчей STRATZ: GraphQL-запрос с несколькими алиасами match(id) на HTTP-запрос.
 */
final class StratzMatchSource implements MatchSourceInterface
{
    public function __construct(
        private readonly GraphQlGateway $gateway,
        private readonly MatchMapper $mapper,
    )
    {
    }

    /**
     * @throws SourceRequestException
     * @throws SourceDataException
     */
    public function getMatch(MatchId $id): MatchDetail
    {
        ['query' => $query, 'variables' => $variables] = MatchQuery::build([$id]);

        return $this->mapper->toEntity($this->gateway->query($query, $variables)->field(MatchQuery::alias(0)));
    }

    /**
     * @param list<MatchId> $ids
     * @param positive-int $concurrency
     * @return Generator<MatchId, MatchDetail|SourceRequestException|SourceDataException, mixed, void>
     */
    public function getMatches(array $ids, int $concurrency): Generator
    {
        $responses = ConcurrentRequests::fetch(
            array_chunk($ids, MatchQuery::BATCH_SIZE),
            $concurrency,
            function (array $batch): ResponseInterface {
                ['query' => $query, 'variables' => $variables] = MatchQuery::build($batch);

                return $this->gateway->request($query, $variables);
            },
            $this->gateway->stream(...),
        );

        /** @var non-empty-list<MatchId> $batch */
        foreach ($responses as $batch => $response) {
            if ($response instanceof SourceRequestException) {
                // один экземпляр на всю пачку
                foreach ($batch as $id) {
                    yield $id => $response;
                }
                continue;
            }

            foreach ($this->resolveChunk($response, $batch) as $id => $outcome) {
                yield $id => $outcome;
            }
        }
    }

    /**
     * Разбирает ответ пачки: сбой запроса или decode — значением на каждый id, поле и маппинг — у своего id.
     *
     * @param non-empty-list<MatchId> $ids
     * @return Generator<MatchId, MatchDetail|SourceRequestException|SourceDataException, mixed, void>
     */
    private function resolveChunk(ResponseInterface $response, array $ids): Generator
    {
        try {
            $result = $this->gateway->decode($response);
        } catch (SourceRequestException|SourceDataException $e) {
            foreach ($ids as $id) {
                yield $id => $e;
            }

            return;
        }

        foreach ($ids as $index => $id) {
            try {
                yield $id => $this->mapper->toEntity($result->field(MatchQuery::alias($index)));
            } catch (SourceRequestException|SourceDataException $e) {
                yield $id => $e;
            }
        }
    }
}
