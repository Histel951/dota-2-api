<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Infrastructure;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Infrastructure\Http\HttpMethod;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

interface ApiGatewayInterface
{
    /**
     * Ленивый запрос: не ждёт заголовков и не проверяет статус (см. decode()).
     *
     * @throws SourceRequestException клиент отверг запрос сразу
     */
    public function request(HttpMethod $method, string $endpoint): ResponseInterface;

    /**
     * @param ResponseInterface[] $responses
     */
    public function stream(array $responses): ResponseStreamInterface;

    /**
     * Проверяет статус и разбирает JSON-тело.
     *
     * @throws SourceRequestException транспорт, 404 ("Object not found"), иной не-200
     * @throws SourceDataException тело не JSON-массив (previous — \JsonException)
     */
    public function decode(ResponseInterface $response): array;

    /**
     * Эквивалент decode(request(GET, $endpoint)).
     *
     * @throws SourceRequestException
     * @throws SourceDataException
     */
    public function get(string $endpoint): array;
}
