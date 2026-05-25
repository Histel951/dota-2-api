<?php

namespace Histel951\Dota2Api\Infrastructure\Http\Contracts;

use Exception;
use Histel951\Dota2Api\Infrastructure\Exceptions\ApiGatewayException;
use Histel951\Dota2Api\Infrastructure\Http\Enums\HttpMethod;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

interface ApiGatewayInterface
{
    /**
     * @param HttpMethod $method
     * @param string $endpoint
     * @return ResponseInterface
     * @throws ApiGatewayException
     * @throws Exception
     */
    public function request(HttpMethod $method, string $endpoint): ResponseInterface;

    /**
     * @param array $responses
     * @return ResponseStreamInterface
     */
    public function stream(array $responses): ResponseStreamInterface;

    /**
     * @throws ApiGatewayException
     */
    public function get(string $endpoint): array;
}