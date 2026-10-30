<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Client;

use Histel951\Dota2Api\OpenDota\OpenDotaClient;

final readonly class Dota2ApiClient
{
    public function __construct(
        private OpenDotaClient $openDota,
    )
    {
    }

    public function openDota(): OpenDotaClient
    {
        return $this->openDota;
    }
}
