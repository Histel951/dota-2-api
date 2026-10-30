<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz;

use Histel951\Dota2Api\Stratz\Match\Application\MatchService;
use Histel951\Dota2Api\Stratz\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\Stratz\StratzClient;
use Histel951\Dota2Api\Stratz\Team\Port\TeamSourceInterface;
use PHPUnit\Framework\TestCase;

class StratzClientTest extends TestCase
{
    public function test_gettersReturnGivenServices()
    {
        $matches = new MatchService($this->createStub(MatchSourceInterface::class));
        $teams = $this->createStub(TeamSourceInterface::class);

        $client = new StratzClient($matches, $teams);

        $this->assertSame($matches, $client->matches());
        $this->assertSame($teams, $client->teams());
    }
}
