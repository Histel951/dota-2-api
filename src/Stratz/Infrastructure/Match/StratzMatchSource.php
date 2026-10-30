<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Match;

use Generator;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlGateway;
use Histel951\Dota2Api\Stratz\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Stratz\Match\Port\MatchSourceInterface;
use SplObjectStorage;
use SplQueue;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
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
        /** @var SplQueue<non-empty-list<MatchId>> $queue */
        $queue = new SplQueue();
        foreach (array_chunk($ids, MatchQuery::BATCH_SIZE) as $chunk) {
            $queue->enqueue($chunk);
        }

        /** @var SplObjectStorage<ResponseInterface, non-empty-list<MatchId>> $chunks */
        $chunks = new SplObjectStorage();
        /** @var array<int, ResponseInterface> $pending */
        $pending = [];

        while (!$queue->isEmpty() || $pending !== []) {
            foreach ($this->fillWindow($queue, $chunks, $pending, $concurrency) as $failedId => $failure) {
                yield $failedId => $failure;
            }

            if ($pending === []) {
                continue;
            }

            foreach ($this->gateway->stream($pending) as $response => $chunk) {
                if (!isset($chunks[$response])) {
                    continue;
                }

                try {
                    if ($chunk->isFirst()) {
                        // заголовки уже получены, вызов не блокирует; без явной проверки статуса
                        // RetryableHttpClient бросает ClientException/ServerException из самого потока
                        $response->getStatusCode();
                    }

                    if (!$chunk->isLast()) {
                        continue;
                    }
                } catch (TransportExceptionInterface $e) {
                    $ids = $chunks[$response];
                    unset($pending[spl_object_id($response)], $chunks[$response]);

                    foreach ($ids as $id) {
                        yield $id => new SourceRequestException($e->getMessage(), 0, $e);
                    }
                    continue;
                }

                $ids = $chunks[$response];
                unset($pending[spl_object_id($response)], $chunks[$response]);

                foreach ($this->resolveChunk($response, $ids) as $id => $outcome) {
                    yield $id => $outcome;
                }

                foreach ($this->fillWindow($queue, $chunks, $pending, $concurrency) as $failedId => $failure) {
                    yield $failedId => $failure;
                }
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

    /**
     * Запускает запросы, пока окно не заполнено. Отвергнутые клиентом сразу отдаёт значением на каждый id пачки.
     *
     * @param SplQueue<non-empty-list<MatchId>> $queue
     * @param SplObjectStorage<ResponseInterface, non-empty-list<MatchId>> $chunks
     * @param array<int, ResponseInterface> $pending
     * @return Generator<MatchId, SourceRequestException, mixed, void>
     */
    private function fillWindow(
        SplQueue $queue,
        SplObjectStorage $chunks,
        array &$pending,
        int $concurrency,
    ): Generator
    {
        while (count($pending) < $concurrency && !$queue->isEmpty()) {
            $ids = $queue->dequeue();
            ['query' => $query, 'variables' => $variables] = MatchQuery::build($ids);

            try {
                $response = $this->gateway->request($query, $variables);
            } catch (SourceRequestException $e) {
                foreach ($ids as $id) {
                    yield $id => $e;
                }
                continue;
            }

            $pending[spl_object_id($response)] = $response;
            $chunks[$response] = $ids;
        }
    }
}
