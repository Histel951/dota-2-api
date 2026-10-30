<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Infrastructure\Match;

use Generator;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Infrastructure\Http\ConcurrentRequests;
use Histel951\Dota2Api\Infrastructure\Http\HttpMethod;
use Histel951\Dota2Api\OpenDota\Infrastructure\ApiGatewayInterface;
use Histel951\Dota2Api\OpenDota\Match\Domain\MatchDetail;
use Histel951\Dota2Api\OpenDota\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\SharedKernel\MatchId;
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
        $responses = ConcurrentRequests::fetch(
            $ids,
            $concurrency,
            fn (MatchId $id): ResponseInterface => $this->gateway->request(HttpMethod::GET, "matches/$id"),
            $this->gateway->stream(...),
        );

        foreach ($responses as $id => $response) {
            if ($response instanceof SourceRequestException) {
                yield $id => $response;
                continue;
            }

            try {
                $outcome = $this->mapper->toEntity($this->extractor->extract($this->gateway->decode($response)));
            } catch (SourceRequestException|SourceDataException $e) {
                $outcome = $e;
            }

            yield $id => $outcome;
        }
    }
}
