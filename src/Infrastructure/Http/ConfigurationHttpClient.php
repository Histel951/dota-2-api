<?php

namespace Histel951\Dota2Api\Infrastructure\Http;

use Histel951\Dota2Api\Infrastructure\Http\Enums\ApiSource;

class ConfigurationHttpClient
{
    public function __construct(
        private readonly string    $baseUrl,
        private readonly ApiSource $apiSource,
        private readonly ?string   $apiKey = null,
        private readonly ?int      $timeout = null,
        private readonly int       $maxRetries = 3,
        private readonly array     $options = [],
    )
    {
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function getTimeout(): ?string
    {
        return $this->timeout;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getApiSource(): ApiSource
    {
        return $this->apiSource;
    }
}