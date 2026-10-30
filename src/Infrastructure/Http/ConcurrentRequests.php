<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Http;

use Closure;
use Generator;
use Histel951\Dota2Api\Exception\SourceRequestException;
use InvalidArgumentException;
use SplObjectStorage;
use SplQueue;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Окно параллельных ленивых HTTP-запросов: до $concurrency в полёте, новый стартует после каждого завершения,
 * результаты по готовности (порядок не гарантирован). Не знает формата API и домена.
 */
final class ConcurrentRequests
{
    /**
     * @template TBatch
     * @param list<TBatch> $batches одна партия — один HTTP-запрос; дубли допустимы
     * @param positive-int $concurrency
     * @param Closure(TBatch): ResponseInterface $send ленивый запрос; SourceRequestException (клиент отверг сразу)
     *        уходит значением и слот окна не занимает, прочие исключения не перехватываются
     * @param Closure(list<ResponseInterface>): ResponseStreamInterface $stream обычно $gateway->stream(...)
     * @return Generator<TBatch, ResponseInterface|SourceRequestException, mixed, void>
     *         ResponseInterface — тело получено, статус НЕ проверен; SourceRequestException — на всю партию:
     *         отвергнут send(), либо ошибка клиента в потоке (обрыв, таймаут, Client/ServerException
     *         RetryableHttpClient), previous — исходное исключение
     * @throws InvalidArgumentException $concurrency < 1 (при первой итерации)
     */
    public static function fetch(array $batches, int $concurrency, Closure $send, Closure $stream): Generator
    {
        if ($concurrency < 1) {
            throw new InvalidArgumentException("Concurrency must be at least 1, $concurrency given");
        }

        /** @var SplQueue<TBatch> $queue */
        $queue = new SplQueue();
        foreach ($batches as $batch) {
            $queue->enqueue($batch);
        }

        /** @var SplObjectStorage<ResponseInterface, TBatch> $inFlight */
        $inFlight = new SplObjectStorage();

        try {
            while (!$queue->isEmpty() || count($inFlight) > 0) {
                foreach (self::fillWindow($queue, $inFlight, $concurrency, $send) as [$batch, $rejection]) {
                    yield $batch => $rejection;
                }

                if (count($inFlight) === 0) {
                    continue;
                }

                $responses = [];
                foreach ($inFlight as $response) {
                    $responses[] = $response;
                }

                foreach ($stream($responses) as $response => $chunk) {
                    if (!isset($inFlight[$response])) {
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

                        $outcome = $response;
                    } catch (ExceptionInterface $e) {
                        $outcome = new SourceRequestException($e->getMessage(), 0, $e);
                    }

                    $batch = $inFlight[$response];
                    unset($inFlight[$response]);

                    yield $batch => $outcome;

                    foreach (self::fillWindow($queue, $inFlight, $concurrency, $send) as [$rejectedBatch, $rejection]) {
                        yield $rejectedBatch => $rejection;
                    }
                }
            }
        } finally {
            // ранний выход: незавершённые запросы отменяются, иначе их деструкторы бросят на не-2xx
            foreach ($inFlight as $response) {
                $response->cancel();
            }
        }
    }

    /**
     * Запускает запросы, пока окно не заполнено.
     *
     * @template TBatch
     * @param SplQueue<TBatch> $queue
     * @param SplObjectStorage<ResponseInterface, TBatch> $inFlight
     * @param Closure(TBatch): ResponseInterface $send
     * @return list<array{TBatch, SourceRequestException}> партии, отвергнутые клиентом сразу
     */
    private static function fillWindow(SplQueue $queue, SplObjectStorage $inFlight, int $concurrency, Closure $send): array
    {
        $rejected = [];

        while (count($inFlight) < $concurrency && !$queue->isEmpty()) {
            $batch = $queue->dequeue();

            try {
                $response = $send($batch);
            } catch (SourceRequestException $e) {
                $rejected[] = [$batch, $e];
                continue;
            }

            $inFlight[$response] = $batch;
        }

        return $rejected;
    }
}
