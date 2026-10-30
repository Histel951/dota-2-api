<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure;

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class StratzHttpClientFactory
{
    public const string DEFAULT_BASE_URL = 'https://api.stratz.com/graphql';

    /**
     * Собственный клиент бандла поверх переданного: base_uri (полный URL эндпоинта), timeout,
     * User-Agent, Bearer-токен и ретраи. Коды 0/500/504 заданы стратегии явно,
     * иначе POST по умолчанию не повторяется.
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

        return new RetryableHttpClient(
            $client->withOptions(array_merge($options, $cfg->getOptions())),
            new GenericRetryStrategy([0, 423, 425, 429, 500, 502, 503, 504, 507, 510]),
            $cfg->getMaxRetries(),
        );
    }
}
