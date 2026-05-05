<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Infrastructure\Http;

use RuntimeException;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class OpenDotaHttpClient implements HttpClientInterface
{
    private const string BASE_URL = 'https://api.opendota.com/api';
    private const int DEFAULT_TIMEOUT = 30;
    private const int MAX_RETRIES = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $timeout = null,
        private array $options = []
    ) {
        $this->options = array_merge([
            'base_uri' => $baseUrl ?? self::BASE_URL,
            'timeout' => $timeout ?? self::DEFAULT_TIMEOUT,
        ], $options);
    }

    /**
     * {@inheritdoc}
     * @throws RuntimeException|ClientException
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($this->apiKey !== null) {
            $options['query'] = array_merge(
                $options['query'] ?? [],
                ['api_key' => $this->apiKey]
            );
        }

        $finalOptions = array_merge_recursive($this->options, $options);

        $attempts = 0;
        $lastException = null;

        while ($attempts < self::MAX_RETRIES) {
            try {
                return $this->httpClient->request($method, $url, $finalOptions);
            } catch (ClientException $e) {
                $lastException = $e;
                $statusCode = $e->getCode();

                if ($statusCode !== 429 && $statusCode >= 400 && $statusCode < 500) {
                    throw $e;
                }

                $attempts++;

                if ($attempts >= self::MAX_RETRIES) {
                    throw $e;
                }

                sleep(2 ** $attempts);
            }
        }

        throw $lastException ?? new RuntimeException('Request failed');
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