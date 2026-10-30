<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Infrastructure\Http;

enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
}