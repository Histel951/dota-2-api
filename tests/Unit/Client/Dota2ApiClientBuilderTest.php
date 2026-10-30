<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Client;

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Client\Dota2ApiClientBuilder;
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
        // эвристика даёт TIGER = OFFLANE, fy = SUPPORT; position_est переставлен
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

    public function test_defaultMatchRolesFallBackToHeuristicWithoutPositionEst()
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
        $this->assertSame(PlayerRole::OFFLANE, $roles[self::TIGER]);
        $this->assertSame(PlayerRole::SUPPORT, $roles[self::FY]);
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
