<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Client\Config;

enum ApiSource: string
{
    case OPENDOTA = 'opendota';
    case STRATZ = 'stratz';
}