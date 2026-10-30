<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Infrastructure\Http\HttpMethod;
use Histel951\Dota2Api\Infrastructure\Http\HttpStatusCode;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final readonly class GraphQlGateway
{
    public function __construct(
        private HttpClientInterface $httpClient,
    )
    {
    }

    /**
     * @param ResponseInterface|iterable<ResponseInterface> $responses
     */
    public function stream(iterable|ResponseInterface $responses): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses);
    }

    /**
     * @param array<string, mixed> $variables
     * @throws SourceRequestException
     * @throws SourceDataException
     */
    public function query(string $query, array $variables = []): GraphQlResult
    {
        return $this->decode($this->request($query, $variables));
    }

    /**
     * Ленивый POST на base_uri клиента.
     *
     * @param array<string, mixed> $variables
     * @throws SourceRequestException
     */
    public function request(string $query, array $variables = []): ResponseInterface
    {
        try {
            return $this->httpClient->request(HttpMethod::POST->value, '', [
                'json' => ['query' => $query, 'variables' => (object) $variables],
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new SourceRequestException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws SourceRequestException статус не 200, транспорт, ответ без data с errors
     * @throws SourceDataException не JSON, нет ни data, ни errors, data не объект
     */
    /**
     * Тексты GraphQL-ошибок из тела не-200 ответа (STRATZ отдаёт ошибки запроса со статусом 400)
     */
    private function errorDetails(ResponseInterface $response): string
    {
        try {
            $body = json_decode($response->getContent(false), true);
        } catch (ExceptionInterface) {
            return '';
        }

        $messages = array_filter(array_map(
            static fn (mixed $error): ?string => is_array($error) && is_string($error['message'] ?? null) ? $error['message'] : null,
            is_array($body) && is_array($body['errors'] ?? null) ? $body['errors'] : [],
        ));

        return [] === $messages ? '' : ': ' . implode('; ', $messages);
    }

    public function decode(ResponseInterface $response): GraphQlResult
    {
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new SourceRequestException($e->getMessage(), 0, $e);
        }

        if ($statusCode !== HttpStatusCode::OK->value) {
            throw new SourceRequestException(
                sprintf('API returned unexpected status code %d', $statusCode) . $this->errorDetails($response),
            );
        }

        try {
            $body = $response->toArray();
        } catch (DecodingExceptionInterface $e) {
            throw new SourceDataException($e->getMessage(), 0, $e);
        } catch (ExceptionInterface $e) {
            throw new SourceRequestException($e->getMessage(), 0, $e);
        }

        $errors = isset($body['errors']) && is_array($body['errors'])
            ? array_values(array_filter($body['errors'], 'is_array'))
            : [];
        $data = $body['data'] ?? null;

        if (null === $data) {
            if ([] !== $errors) {
                throw new SourceRequestException(implode('; ', array_map(
                    static fn (array $error): string => is_string($error['message'] ?? null)
                        ? $error['message']
                        : 'Unknown GraphQL error',
                    $errors,
                )));
            }

            throw new SourceDataException('GraphQL response contains neither data nor errors');
        }

        if (!is_array($data)) {
            throw new SourceDataException('GraphQL response data is not an object');
        }

        return new GraphQlResult($data, $errors);
    }
}
