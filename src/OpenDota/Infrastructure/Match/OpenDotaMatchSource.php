<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Infrastructure\Match;

use Generator;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Infrastructure\Http\HttpMethod;
use Histel951\Dota2Api\OpenDota\Infrastructure\ApiGatewayInterface;
use Histel951\Dota2Api\OpenDota\Match\Domain\MatchDetail;
use Histel951\Dota2Api\OpenDota\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\SharedKernel\MatchId;
use SplObjectStorage;
use SplQueue;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Источник матчей OpenDota: GET matches/{id}.
 */
final class OpenDotaMatchSource implements MatchSourceInterface
{
    public function __construct(
        private readonly ApiGatewayInterface $gateway,
        private readonly MatchExtractor $extractor,
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
        return $this->mapper->toEntity($this->extractor->extract($this->gateway->get("matches/$id")));
    }

    /**
     * @param list<MatchId> $ids
     * @param positive-int $concurrency
     * @return Generator<MatchId, MatchDetail|SourceRequestException|SourceDataException, mixed, void>
     */
    public function getMatches(array $ids, int $concurrency): Generator
    {
        $queue = new SplQueue();
        foreach ($ids as $id) {
            $queue->enqueue($id);
        }

        /** @var SplObjectStorage<ResponseInterface, MatchId> $matchIds */
        $matchIds = new SplObjectStorage();
        /** @var array<int, ResponseInterface> $pending */
        $pending = [];

        while (!$queue->isEmpty() || $pending !== []) {
            foreach ($this->fillWindow($queue, $matchIds, $pending, $concurrency) as $failedId => $failure) {
                yield $failedId => $failure;
            }

            if ($pending === []) {
                continue;
            }

            foreach ($this->gateway->stream($pending) as $response => $chunk) {
                if (!isset($matchIds[$response])) {
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
                    $outcome = new SourceRequestException($e->getMessage(), 0, $e);
                    $id = $matchIds[$response];
                    unset($pending[spl_object_id($response)], $matchIds[$response]);

                    yield $id => $outcome;
                    continue;
                }

                $id = $matchIds[$response];
                unset($pending[spl_object_id($response)], $matchIds[$response]);

                try {
                    $outcome = $this->mapper->toEntity(
                        $this->extractor->extract($this->gateway->decode($response))
                    );
                } catch (SourceRequestException|SourceDataException $e) {
                    $outcome = $e;
                }

                yield $id => $outcome;

                foreach ($this->fillWindow($queue, $matchIds, $pending, $concurrency) as $failedId => $failure) {
                    yield $failedId => $failure;
                }
            }
        }
    }

    /**
     * Запускает запросы, пока окно не заполнено. Запросы, отвергнутые клиентом сразу, отдаёт значением.
     *
     * @param SplQueue<MatchId> $queue
     * @param SplObjectStorage<ResponseInterface, MatchId> $matchIds
     * @param array<int, ResponseInterface> $pending
     * @return Generator<MatchId, SourceRequestException, mixed, void>
     */
    private function fillWindow(
        SplQueue $queue,
        SplObjectStorage $matchIds,
        array &$pending,
        int $concurrency,
    ): Generator
    {
        while (count($pending) < $concurrency && !$queue->isEmpty()) {
            $id = $queue->dequeue();

            try {
                $response = $this->gateway->request(HttpMethod::GET, "matches/$id");
            } catch (SourceRequestException $e) {
                yield $id => $e;
                continue;
            }

            $pending[spl_object_id($response)] = $response;
            $matchIds[$response] = $id;
        }
    }
}
