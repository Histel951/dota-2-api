<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Infrastructure\Http;

use Exception;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

interface ApiGatewayInterface
{
    /**
     * @param HttpMethod $method
     * @param string $endpoint
     * @return ResponseInterface
     * @throws SourceRequestException
     * @throws Exception
     */
    public function request(HttpMethod $method, string $endpoint): ResponseInterface;

    /**
     * @param array $responses
     * @return ResponseStreamInterface
     */
    public function stream(array $responses): ResponseStreamInterface;

    /**
     * @throws SourceRequestException
     */
    public function get(string $endpoint): array;
}