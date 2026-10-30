<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Provider;

use Histel951\Dota2Api\Domain\Common\Enums\PlayerRole;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Infrastructure\Http\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\OpenDota\Provider\OpenDotaProviderFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenDotaProviderFactoryTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';

    public function test_defaultMatchProviderTakesRolesFromPositionEst()
    {
        $data = json_decode(file_get_contents(self::FIXTURE), true);
        // эвристика даёт TIGER = OFFLANE, fy = SUPPORT; position_est переставлен
        foreach ($data['players'] as &$player) {
            if (98887913 === $player['account_id']) {
                $player['position_est'] = 4;
            } elseif (101695162 === $player['account_id']) {
                $player['position_est'] = 3;
            }
        }
        unset($player);

        $gateway = $this->createStub(ApiGatewayInterface::class);
        $gateway->method('request')->willReturn(
            (new MockHttpClient([new MockResponse(json_encode($data))]))->request('GET', 'https://example.test/matches/1')
        );

        $result = (new OpenDotaProviderFactory($gateway))->createMatchProvider()->getMatch(new MatchId(8794744726));

        $this->assertNull($result->getError());
        $roles = [];
        foreach ($result->getMatchDetail()->getPlayers()->getValue() as $p) {
            $roles[$p->getIdentity()->getPlayerId()->toInt()] = $p->getRole()->getValue();
        }
        $this->assertSame(PlayerRole::SUPPORT, $roles[98887913]);
        $this->assertSame(PlayerRole::OFFLANE, $roles[101695162]);
    }
}
