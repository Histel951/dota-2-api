<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Provider;

use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Domain\Entities\Match\Services\ProSceneRoleResolver;
use Histel951\Dota2Api\Infrastructure\Http\ApiGateway;
use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Client\Config\ApiSource;
use Histel951\Dota2Api\Infrastructure\OpenDota\Extractor\MatchExtractor;
use Histel951\Dota2Api\Infrastructure\OpenDota\OpenDotaHttpClientFactory;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\MatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Provider\OpenDotaMatchProvider;
use JsonException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenDotaMatchProviderTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';

    public function test_getMatchReturnsMatchDetail()
    {
        $provider = $this->createProvider([new MockResponse(file_get_contents(self::FIXTURE))]);

        $result = $provider->getMatch(new MatchId(8794744726));

        $this->assertNull($result->getError());
        $this->assertSame(8794744726, $result->getMatchDetail()->getId()->getValue());
    }

    public function test_getMatchReturnsErrorWhenResponseCannotBeMapped()
    {
        $provider = $this->createProvider([new MockResponse('not a json')]);

        $result = $provider->getMatch(new MatchId(8794744726));

        $this->assertNull($result->getMatchDetail());
        $this->assertInstanceOf(JsonException::class, $result->getError());
        $this->assertSame(8794744726, $result->getMatchId()->getValue());
    }

    public function test_getMatchesYieldsErrorsWithoutStoppingStream()
    {
        $provider = $this->createProvider([
            new MockResponse(file_get_contents(self::FIXTURE)),
            new MockResponse('not a json'),
        ]);

        $results = iterator_to_array(
            $provider->getMatches([new MatchId(8794744726), new MatchId(1)]),
            false
        );

        $this->assertCount(2, $results);

        $errors = array_filter($results, static fn ($result) => null !== $result->getError());
        $this->assertCount(1, $errors);
    }

    /**
     * @param MockResponse[] $responses
     */
    private function createProvider(array $responses): OpenDotaMatchProvider
    {
        $httpClient = OpenDotaHttpClientFactory::create(
            new MockHttpClient($responses),
            new ClientConfig(baseUrl: 'https://api.opendota.com/api/', apiSource: ApiSource::OPENDOTA),
        );

        return new OpenDotaMatchProvider(
            gateway: new ApiGateway($httpClient),
            mapper: new MatchOpenDotaMapper(new ProSceneRoleResolver()),
            extractor: new MatchExtractor(),
        );
    }
}
