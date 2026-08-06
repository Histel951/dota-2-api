<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Provider;

use Generator;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidMatchIdException;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Infrastructure\Exceptions\ApiGatewayException;
use Histel951\Dota2Api\Infrastructure\Http\Contracts\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\Http\Enums\HttpMethod;
use Histel951\Dota2Api\Infrastructure\Mapper\Match\MatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Provider\Contracts\ExtractorInterface;
use Histel951\Dota2Api\Infrastructure\Provider\Results\MatchResult;
use SplObjectStorage;
use SplQueue;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

class OpenDotaMatchProvider implements MatchesProviderInterface
{
    public function __construct(
        private readonly ApiGatewayInterface $gateway,
        private readonly MatchOpenDotaMapper $mapper,
        private readonly ExtractorInterface $extractor,
    )
    {
    }

    /**
     * @param MatchId $id
     * @return MatchResult
     * @throws ApiGatewayException
     */
    public function getMatch(MatchId $id): MatchResult
    {
        $response = $this->gateway->request(
            HttpMethod::GET,
            "matches/$id"
        );

        try {
            $json = $response->getContent();
            $extracted = $this->extractor->extract($json);
            $entity = $this->mapper->toEntity($extracted);

            return new MatchResult($id, $entity, null);
        } catch (Throwable $e) {
            dd($e->getMessage());
            return new MatchResult($id, null, $e);
        }
    }

    /**
     * @param MatchId[] $ids
     * @param int $concurrency
     * @return Generator
     * @throws ApiGatewayException
     * @throws InvalidMatchIdException
     * @throws TransportExceptionInterface
     */
    public function getMatches(array $ids, int $concurrency = 5): Generator
    {
        $pending = [];
        $responseMap = new SplObjectStorage();

        $queue = new SplQueue();
        foreach ($ids as $id) {
            $queue->enqueue($id->getValue());
        }

        while (count($pending) < $concurrency && !$queue->isEmpty()) {

            $id = $queue->dequeue();

            $response = $this->gateway->request(
                HttpMethod::GET,
                "matches/$id"
            );

            $pending[$id] = $response;
            $responseMap[$response] = $id;
        }

        while (!empty($pending)) {

            foreach ($this->gateway->stream($pending) as $response => $chunk) {

                if (!$chunk->isLast()) {
                    continue;
                }

                $id = $responseMap[$response];

                try {
                    $json = $response->getContent();
                    $extracted = $this->extractor->extract($json);
                    $entity = $this->mapper->toEntity($extracted);

                    yield new MatchResult(new MatchId($id), $entity, null);
                } catch (Throwable $e) {

                    yield new MatchResult(new MatchId($id), null, $e);
                } finally {
                    unset($pending[$id]);
                    unset($responseMap[$response]);
                }

                if (!$queue->isEmpty()) {

                    $nextId = $queue->dequeue();

                    $nextResponse = $this->gateway->request(
                        HttpMethod::GET,
                        "matches/$nextId"
                    );

                    $pending[$nextId] = $nextResponse;
                    $responseMap[$nextResponse] = $nextId;
                }
            }
        }
    }
}