<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Client\Config;

/**
 * Настройки HTTP-клиента одного источника.
 * apiKey: для OpenDota уходит в query (api_key), для STRATZ — Bearer-токеном.
 * rateLimitPerMinute: лимит запросов в минуту (ретраи тоже расходуют лимит), null — без лимитера.
 * Действует только для STRATZ, OpenDota параметр игнорирует.
 */
final readonly class ClientConfig
{
    public function __construct(
        private string $baseUrl,
        private ?string $apiKey = null,
        private ?int $timeout = null,
        private int $maxRetries = 3,
        private array $options = [],
        private ?int $rateLimitPerMinute = null,
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

    public function getTimeout(): ?int
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

    /**
     * Лимит запросов в минуту; null — без лимитера. Учитывается только STRATZ.
     */
    public function getRateLimitPerMinute(): ?int
    {
        return $this->rateLimitPerMinute;
    }
}
