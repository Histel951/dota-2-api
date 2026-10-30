<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Client;

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Client\Dota2ApiClient;
use Histel951\Dota2Api\Client\Dota2ApiClientBuilder;
use Histel951\Dota2Api\DependencyInjection\Dota2ApiExtension;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\Tests\Support\Stratz\StratzFixtures;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\OpenDota\Infrastructure\Team\OpenDotaTeamSource;
use Histel951\Dota2Api\OpenDota\Match\Application\MatchService;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\PlayerRole;
use Histel951\Dota2Api\SharedKernel\MatchId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class Dota2ApiClientBuilderTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../Fixtures/opendota/match_8794744726.json';

    private const int TIGER = 98887913;
    private const int FY = 101695162;

    /** @var string[] */
    private array $requested = [];

    public function test_buildCreatesClientWithMatchServiceAndTeamSource()
    {
        $client = $this->builder([new MockResponse('{}')])->build();

        $this->assertInstanceOf(MatchService::class, $client->openDota()->matches());
        $this->assertInstanceOf(OpenDotaTeamSource::class, $client->openDota()->teams());
    }

    public function test_defaultMatchRolesComeFromPositionEst()
    {
        $data = json_decode(file_get_contents(self::FIXTURE), true);
        // в фикстуре TIGER = OFFLANE, fy = SUPPORT; position_est переставлен
        foreach ($data['players'] as &$player) {
            if (self::TIGER === $player['account_id']) {
                $player['position_est'] = 4;
            } elseif (self::FY === $player['account_id']) {
                $player['position_est'] = 3;
            }
        }
        unset($player);

        $client = $this->builder([new MockResponse(json_encode($data))])->build();

        $result = $client->openDota()->matches()->getMatch(new MatchId(8794744726));

        $this->assertNull($result->getError());
        $roles = [];
        foreach ($result->getMatchDetail()->getPlayers()->getValue() as $p) {
            $roles[$p->getIdentity()->getPlayerId()->toInt()] = $p->getRole()->getValue();
        }
        $this->assertSame(PlayerRole::SUPPORT, $roles[self::TIGER]);
        $this->assertSame(PlayerRole::OFFLANE, $roles[self::FY]);
        $this->assertSame(['https://api.opendota.com/api/matches/8794744726'], $this->requested);
    }

    public function test_defaultMatchRolesAreUnknownWithoutPositionEst()
    {
        $data = json_decode(file_get_contents(self::FIXTURE), true);
        foreach ($data['players'] as &$player) {
            unset($player['position_est']);
        }
        unset($player);

        $client = $this->builder([new MockResponse(json_encode($data))])->build();

        $result = $client->openDota()->matches()->getMatch(new MatchId(8794744726));

        $this->assertNull($result->getError());
        $roles = [];
        foreach ($result->getMatchDetail()->getPlayers()->getValue() as $p) {
            $roles[$p->getIdentity()->getPlayerId()->toInt()] = $p->getRole()->getValue();
        }
        $this->assertSame(array_fill(0, 10, PlayerRole::UNKNOWN), array_values($roles));
    }

    public function test_getMatchesGoesThroughBuiltClient()
    {
        $client = $this->builder([
            new MockResponse(file_get_contents(self::FIXTURE)),
            new MockResponse('{}', ['http_code' => 404]),
        ])->build();

        $results = iterator_to_array($client->openDota()->matches()->getMatches([new MatchId(1), new MatchId(2)]), false);

        $this->assertCount(2, $results);
        $errors = array_filter($results, static fn ($r) => null !== $r->getError());
        $this->assertCount(1, $errors);
        $this->assertInstanceOf(SourceRequestException::class, array_values($errors)[0]->getError());
    }

    public function test_getMatchThrowsRequestExceptionOnNotFound()
    {
        $client = $this->builder([new MockResponse('{}', ['http_code' => 404])])->build();

        $this->expectException(SourceRequestException::class);

        $client->openDota()->matches()->getMatch(new MatchId(1));
    }

    public function test_stratzWorksWithoutConfigUsingDefaultBaseUrlAndNoAuthorization()
    {
        $captured = $this->captureStratz(new MockResponse(StratzFixtures::matchJson(StratzFixtures::PARSED_MATCH_ID)));

        $result = (new Dota2ApiClientBuilder($captured->http))->build()
            ->stratz()->matches()->getMatch(new MatchId(StratzFixtures::PARSED_MATCH_ID));

        $this->assertNull($result->getError());
        $this->assertSame('https://api.stratz.com/graphql', $captured->url);
        $this->assertContains('User-Agent: STRATZ_API', $captured->headers);
        foreach ($captured->headers as $header) {
            $this->assertStringNotContainsStringIgnoringCase('authorization', $header);
        }
    }

    public function test_stratzConfigTokenIsSentAsBearer()
    {
        $captured = $this->captureStratz(new MockResponse(StratzFixtures::matchJson(StratzFixtures::PARSED_MATCH_ID)));
        $config = new ClientConfig(baseUrl: 'https://example.test/gql', apiKey: 'tok', maxRetries: 0);

        (new Dota2ApiClientBuilder($captured->http, null, $config))->build()
            ->stratz()->matches()->getMatch(new MatchId(StratzFixtures::PARSED_MATCH_ID));

        $this->assertSame('https://example.test/gql', $captured->url);
        $this->assertContains('Authorization: Bearer tok', $captured->headers);
    }

    public function test_stratzGetMatchSendsGraphQlPostOnFixture()
    {
        $captured = $this->captureStratz(new MockResponse(StratzFixtures::matchJson(StratzFixtures::PARSED_MATCH_ID)));
        $config = new ClientConfig(baseUrl: 'https://api.stratz.com/graphql', apiKey: 'tok', maxRetries: 0);

        $result = (new Dota2ApiClientBuilder($captured->http, null, $config))->build()
            ->stratz()->matches()->getMatch(new MatchId(StratzFixtures::PARSED_MATCH_ID));

        $this->assertNull($result->getError());
        $this->assertSame(StratzFixtures::PARSED_MATCH_ID, $result->getMatchDetail()->getId()->getValue());
        $this->assertSame('POST', $captured->method);
        $this->assertSame('https://api.stratz.com/graphql', $captured->url);
        $this->assertContains('User-Agent: STRATZ_API', $captured->headers);
        $body = json_decode($captured->body, true);
        $this->assertIsString($body['query']);
        $this->assertStringContainsString('m0', $body['query']);
        $this->assertSame(['m0' => StratzFixtures::PARSED_MATCH_ID], $body['variables']);
    }

    public function test_stratzGetTeamMapsFixture()
    {
        $json = file_get_contents(__DIR__ . '/../../Fixtures/stratz/team_8261500.json');
        $captured = $this->captureStratz(new MockResponse($json));

        $team = (new Dota2ApiClientBuilder($captured->http))->build()->stratz()->teams()->getTeam(new TeamId(8261500));

        $this->assertSame(8261500, $team->getId()->toInt());
        $this->assertSame(8261500, json_decode($captured->body, true)['variables']['teamId']);
    }

    public function test_stratzAndOpenDotaDoNotShareCredentials()
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $auth = array_values(array_filter($options['headers'], static fn (string $h): bool => str_starts_with(strtolower($h), 'authorization')));
            $requests[] = [$url, $auth];

            return new MockResponse(
                str_contains($url, 'stratz')
                    ? StratzFixtures::matchJson(StratzFixtures::PARSED_MATCH_ID)
                    : file_get_contents(self::FIXTURE)
            );
        });
        $client = (new Dota2ApiClientBuilder(
            $http,
            new ClientConfig(baseUrl: 'https://api.opendota.com/api/', maxRetries: 0),
            new ClientConfig(baseUrl: 'https://api.stratz.com/graphql', apiKey: 'tok', maxRetries: 0),
        ))->build();

        $client->openDota()->matches()->getMatch(new MatchId(8794744726));
        $client->stratz()->matches()->getMatch(new MatchId(StratzFixtures::PARSED_MATCH_ID));

        $this->assertSame(['https://api.opendota.com/api/matches/8794744726', []], $requests[0]);
        $this->assertSame(['https://api.stratz.com/graphql', ['Authorization: Bearer tok']], $requests[1]);
    }

    public function test_builderGraphMatchesContainerGraph()
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class)->setPublic(true);
        $container->setAlias(HttpClientInterface::class, 'http_client')->setPublic(true);
        (new Dota2ApiExtension())->load([[]], $container);
        $container->compile();
        $fromContainer = $container->get(Dota2ApiClient::class);

        $fromBuilder = (new Dota2ApiClientBuilder(new MockHttpClient()))->build();

        $this->assertSame($fromContainer->stratz()::class, $fromBuilder->stratz()::class);
        $this->assertSame($fromContainer->stratz()->matches()::class, $fromBuilder->stratz()->matches()::class);
        $this->assertSame($fromContainer->stratz()->teams()::class, $fromBuilder->stratz()->teams()::class);
        $this->assertSame($fromContainer->openDota()->matches()::class, $fromBuilder->openDota()->matches()::class);
        $this->assertSame($fromContainer->openDota()->teams()::class, $fromBuilder->openDota()->teams()::class);
    }

    /**
     * Запоминает единственный запрос к STRATZ.
     */
    private function captureStratz(MockResponse $response): object
    {
        $captured = new class {
            public MockHttpClient $http;
            public string $method = '';
            public string $url = '';
            public string $body = '';
            /** @var list<string> */
            public array $headers = [];
        };
        $captured->http = new MockHttpClient(static function (string $method, string $url, array $options) use ($captured, $response): MockResponse {
            $captured->method = $method;
            $captured->url = $url;
            $captured->body = (string) $options['body'];
            $captured->headers = $options['headers'];

            return $response;
        });

        return $captured;
    }

    /**
     * @param MockResponse[] $responses
     */
    private function builder(array $responses): Dota2ApiClientBuilder
    {
        $http = new MockHttpClient(function (string $method, string $url) use (&$responses): MockResponse {
            $this->requested[] = $url;

            return array_shift($responses) ?? new MockResponse('{}', ['http_code' => 500]);
        });

        return new Dota2ApiClientBuilder(
            $http,
            new ClientConfig(baseUrl: 'https://api.opendota.com/api/', maxRetries: 0),
        );
    }
}
