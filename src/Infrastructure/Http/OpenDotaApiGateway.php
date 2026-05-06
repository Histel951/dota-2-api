<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Http;

use Histel951\Dota2Api\Infrastructure\Exceptions\ApiGatewayException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

class OpenDotaApiGateway
{
    public function __construct(
        private readonly OpenDotaHttpClient $httpClient,
    )
    {
    }

    /**
     * @throws ApiGatewayException
     */
    public function get(string $endpoint): array
    {
        $response = $this->sendRequest(Request::METHOD_GET, $endpoint);
        return $this->parseResponse($response);
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @return ResponseInterface
     * @throws ApiGatewayException
     */
    private function sendRequest(string $method, string $endpoint): ResponseInterface
    {
        try {
            $response = $this->httpClient->request($method, $endpoint);
        } catch (TransportExceptionInterface $e) {
            throw new ApiGatewayException($e->getMessage());
        }

        $this->checkResponseStatus($response);

        return $response;
    }

    /**
     * @throws ApiGatewayException
     */
    private function parseResponse(ResponseInterface $response): array
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

        if ($statusCode === Response::HTTP_NOT_FOUND) {
            throw new ApiGatewayException('Object not found');
        }

        if ($statusCode !== Response::HTTP_OK) {
            throw new ApiGatewayException(
                sprintf('API returned unexpected status code %d', $statusCode)
            );
        }
    }
}