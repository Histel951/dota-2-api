<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Infrastructure\Http;

enum HttpStatusCode: int
{
    /** 200 */
    case OK = 200;

    /** 400 */
    case NOT_FOUND = 404;
    case TOO_MANY_REQUESTS = 429;

    /** 500 */
    case INTERNAL_SERVER_ERROR = 500;
}