<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure;

use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlGateway;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\StratzMatchSource;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzSourceFactory;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\StratzTeamSource;
use Histel951\Dota2Api\Stratz\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\Stratz\Team\Port\TeamSourceInterface;
use Histel951\Dota2Api\Tests\Support\Stratz\StratzFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class StratzSourceFactoryTest extends TestCase
{
    public function test_createMatchSourceReturnsStratzMatchSource()
    {
        $source = $this->factory([])->createMatchSource();

        $this->assertInstanceOf(MatchSourceInterface::class, $source);
        $this->assertInstanceOf(StratzMatchSource::class, $source);
    }

    public function test_createTeamSourceReturnsStratzTeamSource()
    {
        $source = $this->factory([])->createTeamSource();

        $this->assertInstanceOf(TeamSourceInterface::class, $source);
        $this->assertInstanceOf(StratzTeamSource::class, $source);
    }

    public function test_matchSourceUsesGivenGateway()
    {
        $requests = 0;
        $factory = $this->factory([new MockResponse(StratzFixtures::matchJson(StratzFixtures::PARSED_MATCH_ID))], $requests);

        $match = $factory->createMatchSource()->getMatch(new MatchId(StratzFixtures::PARSED_MATCH_ID));

        $this->assertSame(StratzFixtures::PARSED_MATCH_ID, $match->getId()->getValue());
        $this->assertSame(1, $requests);
    }

    public function test_teamSourceUsesGivenGateway()
    {
        $requests = 0;
        $json = file_get_contents(__DIR__ . '/../../../Fixtures/stratz/team_8261500.json');
        $factory = $this->factory([new MockResponse($json)], $requests);

        $team = $factory->createTeamSource()->getTeam(new TeamId(8261500));

        $this->assertSame(8261500, $team->getId()->toInt());
        $this->assertSame(1, $requests);
    }

    public function test_factoryCreatesNewSourceOnEachCall()
    {
        $factory = $this->factory([]);

        $this->assertNotSame($factory->createMatchSource(), $factory->createMatchSource());
        $this->assertNotSame($factory->createTeamSource(), $factory->createTeamSource());
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function factory(array $responses, int &$requests = 0): StratzSourceFactory
    {
        $http = new MockHttpClient(static function () use (&$responses, &$requests): MockResponse {
            ++$requests;

            return array_shift($responses) ?? new MockResponse('{}', ['http_code' => 500]);
        }, 'https://api.stratz.com/graphql');

        return new StratzSourceFactory(new GraphQlGateway($http));
    }
}
