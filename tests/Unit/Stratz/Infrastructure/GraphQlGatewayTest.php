<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure;

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlGateway;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlResult;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzHttpClientFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GraphQlGatewayTest extends TestCase
{
    /** @var list<string> тела запросов */
    private array $bodies = [];

    public function test_queryWithoutVariablesSendsEmptyObject()
    {
        $gateway = $this->createGateway([new MockResponse('{"data":{"a":{}}}')]);

        $gateway->query('{ a }');

        $this->assertSame('{"query":"{ a }","variables":{}}', $this->bodies[0]);
    }

    public function test_queryWithVariablesSendsThem()
    {
        $gateway = $this->createGateway([new MockResponse('{"data":{"a":{}}}')]);

        $gateway->query('query($id: Long!) { a }', ['id' => 5]);

        $this->assertSame(
            ['query' => 'query($id: Long!) { a }', 'variables' => ['id' => 5]],
            json_decode($this->bodies[0], true),
        );
    }

    public function test_queryReturnsResultWithData()
    {
        $gateway = $this->createGateway([new MockResponse('{"data":{"match":{"id":1}}}')]);

        $this->assertSame(['id' => 1], $gateway->query('{ match }')->field('match'));
    }

    public function test_requestIsLazy()
    {
        $gateway = $this->createGateway([new MockResponse('', ['http_code' => 403])]);

        // request() не читает ответ: ошибка статуса возникает только в decode()
        $response = $gateway->request('{ a }');

        try {
            $gateway->decode($response);
            $this->fail('SourceRequestException expected');
        } catch (SourceRequestException) {
            $this->assertSame(403, $response->getStatusCode(false));
        }
    }

    public function test_decodeOfRequestEqualsQuery()
    {
        $gateway = $this->createGateway([
            new MockResponse('{"data":{"a":{"x":1}}}'),
            new MockResponse('{"data":{"a":{"x":1}}}'),
        ]);

        $viaQuery = $gateway->query('{ a }');
        $viaDecode = $gateway->decode($gateway->request('{ a }'));

        $this->assertEquals($viaQuery, $viaDecode);
    }

    public function test_forbiddenThrowsRequestException()
    {
        $gateway = $this->createGateway([new MockResponse('', ['http_code' => 403])]);

        $this->expectException(SourceRequestException::class);

        $gateway->query('{ a }');
    }

    public function test_serverErrorThrowsRequestException()
    {
        $gateway = $this->createGateway([new MockResponse('', ['http_code' => 500])], 0);

        $this->expectException(SourceRequestException::class);

        $gateway->query('{ a }');
    }

    public function test_transportErrorThrowsRequestException()
    {
        $gateway = $this->createGateway([new MockResponse('', ['error' => 'timeout'])], 0);

        $this->expectException(SourceRequestException::class);

        $gateway->query('{ a }');
    }

    public function test_notJsonThrowsDataExceptionWithPrevious()
    {
        $gateway = $this->createGateway([new MockResponse('<html>')]);

        try {
            $gateway->query('{ a }');
            $this->fail('SourceDataException expected');
        } catch (SourceDataException $e) {
            $this->assertNotNull($e->getPrevious());
        }
    }

    public function test_errorsWithoutDataThrowRequestExceptionWithJoinedMessages()
    {
        $gateway = $this->createGateway([
            new MockResponse('{"errors":[{"message":"first"},{"message":"second"}]}'),
        ]);

        $this->expectException(SourceRequestException::class);
        $this->expectExceptionMessage('first; second');

        $gateway->query('{ a }');
    }

    public function test_errorsWithNullDataThrowRequestException()
    {
        $gateway = $this->createGateway([new MockResponse('{"data":null,"errors":[{"message":"boom"}]}')]);

        $this->expectException(SourceRequestException::class);
        $this->expectExceptionMessage('boom');

        $gateway->query('{ a }');
    }

    public function test_neitherDataNorErrorsThrowsDataException()
    {
        $gateway = $this->createGateway([new MockResponse('{}')]);

        $this->expectException(SourceDataException::class);

        $gateway->query('{ a }');
    }

    public function test_scalarDataThrowsDataException()
    {
        $gateway = $this->createGateway([new MockResponse('{"data":"oops"}')]);

        $this->expectException(SourceDataException::class);

        $gateway->query('{ a }');
    }

    public function test_partialResponseIsNotDecodeError()
    {
        $gateway = $this->createGateway([
            new MockResponse('{"data":{"a":{"x":1},"b":null},"errors":[{"message":"b failed","path":["b"]}]}'),
        ]);

        $result = $gateway->query('{ a b }');

        $this->assertInstanceOf(GraphQlResult::class, $result);
        $this->assertSame(['x' => 1], $result->field('a'));
        try {
            $result->field('b');
            $this->fail('SourceRequestException expected');
        } catch (SourceRequestException $e) {
            $this->assertSame('b failed', $e->getMessage());
        }
    }

    /**
     * @param MockResponse[] $responses
     */
    private function createGateway(array $responses, int $maxRetries = 1): GraphQlGateway
    {
        $mock = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $body = $options['body'] ?? '';
            $this->bodies[] = is_string($body) ? $body : '';

            return array_shift($responses);
        });

        return new GraphQlGateway(StratzHttpClientFactory::create(
            $mock,
            new ClientConfig(baseUrl: 'https://api.stratz.com/graphql', maxRetries: $maxRetries),
        ));
    }
}
