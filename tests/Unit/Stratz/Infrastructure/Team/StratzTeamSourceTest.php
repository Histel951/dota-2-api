<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure\Team;

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\SharedKernel\Side;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlGateway;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzHttpClientFactory;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\StratzTeamSource;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamHeroMapper;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamMapper;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamMatchMapper;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamPlayerMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class StratzTeamSourceTest extends TestCase
{
    /** @var list<string> */
    private array $bodies = [];

    public function test_getTeamMapsFixture()
    {
        $source = $this->createSource([$this->fixtureResponse('team_8261500')]);

        $team = $source->getTeam(new TeamId(8261500));

        $this->assertSame('XG', $team->getTag()?->getValue());
        $this->assertSame(628, $team->getRecord()->getGamesWon());
        $this->assertSame(8261500, json_decode($this->bodies[0], true)['variables']['teamId']);
    }

    public function test_getMatchesByTeamMapsFixture()
    {
        $source = $this->createSource([$this->fixtureResponse('team_8261500_matches')]);

        $matches = $source->getMatchesByTeam(new TeamId(8261500));

        $this->assertCount(20, $matches);
        $this->assertSame(Side::RADIANT, $matches[0]->getSide());
        $this->assertSame(Side::DIRE, $matches[1]->getSide());
        $this->assertStringContainsString('"teamId":8261500', $this->bodies[0]);
    }

    public function test_getPlayersByTeamMapsFixture()
    {
        $source = $this->createSource([$this->fixtureResponse('team_8261500_players')]);

        $players = $source->getPlayersByTeam(new TeamId(8261500));

        $this->assertCount(11, $players);
        $this->assertCount(4, array_filter($players, static fn ($p): bool => $p->isCurrentMember()));
        $this->assertStringContainsString('"teamId":8261500', $this->bodies[0]);
    }

    public function test_getHeroesByTeamMapsFixtureWithConstantsNames()
    {
        $source = $this->createSource([$this->fixtureResponse('team_8261500_heroes')]);

        $heroes = $source->getHeroesByTeam(new TeamId(8261500));

        $this->assertCount(107, $heroes);
        $this->assertSame('Largo', $heroes[0]->getName()->getValue());
        $this->assertStringContainsString('"teamId":8261500', $this->bodies[0]);
    }

    public function test_teamIdVariableIsIntegerNotString()
    {
        $source = $this->createSource([$this->fixtureResponse('team_8261500')]);

        $source->getTeam(new TeamId('8261500'));

        $this->assertStringContainsString('"variables":{"teamId":8261500}', $this->bodies[0]);
        $this->assertStringNotContainsString('"teamId":"8261500"', $this->bodies[0]);
    }

    public function test_nullTeamThrowsObjectNotFound()
    {
        $source = $this->createSource([new MockResponse('{"data":{"team":null}}')]);

        $this->expectException(SourceRequestException::class);
        $this->expectExceptionMessage('Object not found');

        $source->getTeam(new TeamId(1));
    }

    public function test_nullTeamInListMethodsThrowsObjectNotFound()
    {
        $source = $this->createSource([new MockResponse('{"data":{"team":null}}')]);

        $this->expectException(SourceRequestException::class);

        $source->getMatchesByTeam(new TeamId(1));
    }

    public function test_forbiddenThrowsRequestException()
    {
        $source = $this->createSource([new MockResponse('', ['http_code' => 403])]);

        $this->expectException(SourceRequestException::class);

        $source->getTeam(new TeamId(8261500));
    }

    public function test_badRequestWithGraphQlErrorsCarriesMessage()
    {
        $source = $this->createSource([
            new MockResponse(
                '{"errors":[{"message":"Variable got invalid value"}]}',
                ['http_code' => 400],
            ),
        ]);

        try {
            $source->getPlayersByTeam(new TeamId(8261500));
            $this->fail('SourceRequestException expected');
        } catch (SourceRequestException $e) {
            $this->assertStringContainsString('got invalid value', $e->getMessage());
        }
    }

    public function test_nullListsGiveEmptyArrays()
    {
        $source = $this->createSource([
            new MockResponse('{"data":{"team":{"matches":null}}}'),
            new MockResponse('{"data":{"team":{"players":null}}}'),
            new MockResponse('{"data":{"team":{"heroes":null},"constants":{"heroes":[]}}}'),
        ]);
        $id = new TeamId(8261500);

        $this->assertSame([], $source->getMatchesByTeam($id));
        $this->assertSame([], $source->getPlayersByTeam($id));
        $this->assertSame([], $source->getHeroesByTeam($id));
    }

    public function test_nonArrayListThrowsSourceDataException()
    {
        $source = $this->createSource([new MockResponse('{"data":{"team":{"matches":"oops"}}}')]);

        $this->expectException(SourceDataException::class);

        $source->getMatchesByTeam(new TeamId(8261500));
    }

    public function test_nonObjectListItemThrowsSourceDataException()
    {
        $source = $this->createSource([new MockResponse('{"data":{"team":{"players":[1]}}}')]);

        $this->expectException(SourceDataException::class);

        $source->getPlayersByTeam(new TeamId(8261500));
    }

    public function test_heroWithoutNameInConstantsThrowsSourceDataException()
    {
        $source = $this->createSource([new MockResponse(
            '{"data":{"team":{"heroes":[{"heroId":155,"matchCount":13,"winCount":5}]},'
            . '"constants":{"heroes":[{"id":1,"displayName":"Anti-Mage"}]}}}'
        )]);

        $this->expectException(SourceDataException::class);

        $source->getHeroesByTeam(new TeamId(8261500));
    }

    public function test_missingConstantsThrowsSourceDataException()
    {
        $source = $this->createSource([new MockResponse(
            '{"data":{"team":{"heroes":[{"heroId":155,"matchCount":13,"winCount":5}]}}}'
        )]);

        $this->expectException(SourceDataException::class);

        $source->getHeroesByTeam(new TeamId(8261500));
    }

    private function fixtureResponse(string $name): MockResponse
    {
        return new MockResponse(file_get_contents(__DIR__ . '/../../../../Fixtures/stratz/' . $name . '.json'));
    }

    /**
     * @param MockResponse[] $responses
     */
    private function createSource(array $responses): StratzTeamSource
    {
        $mock = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $body = $options['body'] ?? '';
            $this->bodies[] = is_string($body) ? $body : '';

            return array_shift($responses);
        });

        $gateway = new GraphQlGateway(StratzHttpClientFactory::create(
            $mock,
            new ClientConfig(baseUrl: 'https://api.stratz.com/graphql', maxRetries: 1),
        ));

        return new StratzTeamSource(
            $gateway,
            new TeamMapper(),
            new TeamMatchMapper(),
            new TeamPlayerMapper(),
            new TeamHeroMapper(),
        );
    }
}
