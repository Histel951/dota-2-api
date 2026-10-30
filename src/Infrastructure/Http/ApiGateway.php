<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Http;

use Exception;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Throwable;

class ApiGateway implements ApiGatewayInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    )
    {
    }

    /**
     * @param ResponseInterface[] $responses
     * @return ResponseStreamInterface
     */
    public function stream(array $responses): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses);
    }

    /**
     * @throws SourceRequestException
     */
    public function get(string $endpoint): array
    {
        $response = $this->request(HttpMethod::GET, $endpoint);
        return $this->parseResponse($response);
    }

    /**
     * @param HttpMethod $method
     * @param string $endpoint
     * @return ResponseInterface
     * @throws SourceRequestException
     * @throws Exception
     */
    public function request(HttpMethod $method, string $endpoint): ResponseInterface
    {
        try {
            $response = $this->httpClient->request($method->value, $endpoint);
        } catch (TransportExceptionInterface $e) {
            throw new SourceRequestException($e->getMessage());
        }

        $this->checkResponseStatus($response);

        return $response;
    }

    /**
     * @throws SourceRequestException
     */
    private function parseResponse(ResponseInterface $response): array
    {
        try {
            return $response->toArray();
        } catch (Throwable $e) {
            throw new SourceRequestException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws SourceRequestException
     */
    private function checkResponseStatus(ResponseInterface $response): void
    {
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new SourceRequestException($e->getMessage());
        }

        if ($statusCode === HttpStatusCode::NOT_FOUND->value) {
            throw new SourceRequestException('Object not found');
        }

        if ($statusCode !== HttpStatusCode::OK->value) {
            throw new SourceRequestException(
                sprintf('API returned unexpected status code %d', $statusCode)
            );
        }
    }
}