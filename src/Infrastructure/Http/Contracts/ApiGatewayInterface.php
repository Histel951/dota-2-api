<?php

namespace Histel951\Dota2Api\Infrastructure\Http\Contracts;

use Histel951\Dota2Api\Infrastructure\Exceptions\ApiGatewayException;

interface ApiGatewayInterface
{
    /**
     * @throws ApiGatewayException
     */
    public function get(string $endpoint): array;
}