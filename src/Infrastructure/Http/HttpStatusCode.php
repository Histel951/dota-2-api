<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Http;

enum HttpStatusCode: int
{
    case OK = 200;
    case NOT_FOUND = 404;
}
