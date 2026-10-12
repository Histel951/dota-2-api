<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Infrastructure\Http;

use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class OpenDotaHttpClient implements HttpClientInterface
{
    private HttpClientInterface $httpClient;
    private array $options;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly ConfigurationHttpClient $cfg,
    ) {
        // ретраи 429/5xx и транспортных ошибок с экспоненциальной задержкой и учётом Retry-After
        $this->httpClient = new RetryableHttpClient($httpClient, maxRetries: $cfg->getMaxRetries());

        $this->options = array_merge([
            'base_uri' => $cfg->getBaseUrl(),
            'timeout' => $cfg->getTimeout(),
        ], $cfg->getOptions());
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($this->cfg->getApiKey() !== null) {
            $options['query'] = array_merge(
                $options['query'] ?? [],
                ['api_key' => $this->cfg->getApiKey()]
            );
        }

        return $this->httpClient->request($method, $url, array_merge_recursive($this->options, $options));
    }

    /**
     * {@inheritdoc}
     */
    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->options = array_merge_recursive($this->options, $options);

        return $clone;
    }
}
