<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Infrastructure\OpenDota\Http;

use Histel951\Dota2Api\Infrastructure\Http\ConfigurationHttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenDotaHttpClientFactory
{
    /**
     * Собственный клиент бандла поверх переданного: base_uri, timeout, api_key в query
     * и ретраи 429/5xx/транспортных ошибок с экспоненциальной задержкой и учётом Retry-After
     */
    public static function create(HttpClientInterface $client, ConfigurationHttpClient $cfg): HttpClientInterface
    {
        $options = [
            'base_uri' => $cfg->getBaseUrl(),
            'timeout' => $cfg->getTimeout(),
        ];

        if (null !== $cfg->getApiKey()) {
            $options['query'] = ['api_key' => $cfg->getApiKey()];
        }

        return new RetryableHttpClient(
            $client->withOptions(array_merge($options, $cfg->getOptions())),
            maxRetries: $cfg->getMaxRetries(),
        );
    }
}
