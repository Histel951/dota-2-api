<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure;

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\ThrottlingHttpClient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class StratzHttpClientFactory
{
    public const string DEFAULT_BASE_URL = 'https://api.stratz.com/graphql';

    private const string RATE_LIMITER_ID = 'dota2_api.stratz';

    /**
     * Собственный клиент бандла поверх переданного: base_uri (полный URL эндпоинта), timeout,
     * User-Agent, Bearer-токен и ретраи. Коды 0/500/504 заданы стратегии явно,
     * иначе POST по умолчанию не повторяется.
     *
     * При заданном rateLimitPerMinute запросы ограничиваются скользящим окном в минуту.
     * Лимитер стоит ВНУТРИ ретраев: RetryableHttpClient повторяет запрос через обёрнутый клиент
     * (AsyncContext::replaceRequest), поэтому каждый ретрай тоже проходит лимитер и расходует лимит.
     * Состояние лимитера — в памяти клиента: лимит действует в пределах одного процесса.
     */
    public static function create(HttpClientInterface $client, ClientConfig $cfg): HttpClientInterface
    {
        $options = [
            'base_uri' => $cfg->getBaseUrl(),
            'timeout' => $cfg->getTimeout(),
            'headers' => ['User-Agent' => 'STRATZ_API'],
        ];

        if (null !== $cfg->getApiKey()) {
            $options['auth_bearer'] = $cfg->getApiKey();
        }

        $client = $client->withOptions(array_merge($options, $cfg->getOptions()));

        if (null !== $limit = $cfg->getRateLimitPerMinute()) {
            $limiter = new RateLimiterFactory(
                ['id' => self::RATE_LIMITER_ID, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 minute'],
                new InMemoryStorage(),
            );
            $client = new ThrottlingHttpClient($client, $limiter->create());
        }

        return new RetryableHttpClient(
            $client,
            new GenericRetryStrategy([0, 423, 425, 429, 500, 502, 503, 504, 507, 510]),
            $cfg->getMaxRetries(),
        );
    }
}
