<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Client;

use Histel951\Dota2Api\OpenDota\OpenDotaClient;
use Histel951\Dota2Api\Stratz\StratzClient;

final readonly class Dota2ApiClient
{
    public function __construct(
        private OpenDotaClient $openDota,
        private StratzClient $stratz,
    )
    {
    }

    public function openDota(): OpenDotaClient
    {
        return $this->openDota;
    }

    public function stratz(): StratzClient
    {
        return $this->stratz;
    }
}
