<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Http;

use Exception;
use Histel951\Dota2Api\Infrastructure\Exceptions\ApiGatewayException;
use Histel951\Dota2Api\Infrastructure\Http\Contracts\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\Http\Enums\HttpMethod;
use Histel951\Dota2Api\Infrastructure\Http\Enums\HttpStatusCode;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Throwable;

class ApiGateway implements ApiGatewayInterface
{
    public function __construct(
        private readonly OpenDotaHttpClient $httpClient,
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
     * @throws ApiGatewayException
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
     * @throws ApiGatewayException
     * @throws Exception
     */
    public function request(HttpMethod $method, string $endpoint): ResponseInterface
    {
        try {
            $response = $this->httpClient->request($method->value, $endpoint);
        } catch (TransportExceptionInterface $e) {
            throw new ApiGatewayException($e->getMessage());
        }

        $this->checkResponseStatus($response);

        return $response;
    }

    /**
     * @throws ApiGatewayException
     */
    public function parseResponse(ResponseInterface $response): array
    {
        try {
            return $response->toArray();
        } catch (Throwable $e) {
            throw new ApiGatewayException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws ApiGatewayException
     */
    private function checkResponseStatus(ResponseInterface $response): void
    {
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new ApiGatewayException($e->getMessage());
        }

        if ($statusCode === HttpStatusCode::NOT_FOUND->value) {
            throw new ApiGatewayException('Object not found');
        }

        if ($statusCode !== HttpStatusCode::OK->value) {
            throw new ApiGatewayException(
                sprintf('API returned unexpected status code %d', $statusCode)
            );
        }
    }
}