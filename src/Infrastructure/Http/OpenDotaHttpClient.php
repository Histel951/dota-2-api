<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Infrastructure\Http;

use Exception;
use Histel951\Dota2Api\Infrastructure\Http\Enums\HttpStatusCode;
use RuntimeException;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class OpenDotaHttpClient implements HttpClientInterface
{
    private array $options;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigurationHttpClient $cfg,
    ) {
        $this->options = array_merge([
            'base_uri' => $cfg->getBaseUrl(),
            'timeout' => $cfg->getTimeout(),
        ], $cfg->getOptions());
    }

    /**
     * {@inheritdoc}
     * @throws RuntimeException|ClientException|Exception
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($this->cfg->getApiKey() !== null) {
            $options['query'] = array_merge(
                $options['query'] ?? [],
                ['api_key' => $this->cfg->getApiKey()]
            );
        }

        $finalOptions = array_merge_recursive($this->options, $options);

        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->cfg->getMaxRetries()) {
            try {
                return $this->httpClient->request($method, $url, $finalOptions);
            } catch (ClientException $e) {
                $lastException = $e;
                $statusCode = $e->getCode();

                if (
                    $statusCode !== HttpStatusCode::TOO_MANY_REQUESTS->value
                    && $statusCode >= HttpStatusCode::NOT_FOUND->value
                    && $statusCode < HttpStatusCode::INTERNAL_SERVER_ERROR->value
                ) {
                    throw $e;
                }

                $attempts++;

                if ($attempts >= $this->cfg->getMaxRetries()) {
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