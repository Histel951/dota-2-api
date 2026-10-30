<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Http;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class ApiGateway implements ApiGatewayInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    )
    {
    }

    /**
     * @param ResponseInterface[] $responses
     */
    public function stream(array $responses): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses);
    }

    public function get(string $endpoint): array
    {
        return $this->decode($this->request(HttpMethod::GET, $endpoint));
    }

    public function request(HttpMethod $method, string $endpoint): ResponseInterface
    {
        try {
            return $this->httpClient->request($method->value, $endpoint);
        } catch (TransportExceptionInterface $e) {
            throw new SourceRequestException($e->getMessage(), 0, $e);
        }
    }

    public function decode(ResponseInterface $response): array
    {
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new SourceRequestException($e->getMessage(), 0, $e);
        }

        if ($statusCode === HttpStatusCode::NOT_FOUND->value) {
            throw new SourceRequestException('Object not found');
        }

        if ($statusCode !== HttpStatusCode::OK->value) {
            throw new SourceRequestException(
                sprintf('API returned unexpected status code %d', $statusCode)
            );
        }

        try {
            return $response->toArray();
        } catch (DecodingExceptionInterface $e) {
            throw new SourceDataException($e->getMessage(), 0, $e);
        } catch (ExceptionInterface $e) {
            throw new SourceRequestException($e->getMessage(), 0, $e);
        }
    }
}
