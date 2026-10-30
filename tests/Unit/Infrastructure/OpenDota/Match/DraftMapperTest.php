<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Match;

use Histel951\Dota2Api\Infrastructure\OpenDota\Match\DraftMapper;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidDraftOrderException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidHeroIdException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ValueError;

class DraftMapperTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';

    public function test_mapSplitsFixtureIntoPicksAndBans()
    {
        $data = json_decode(file_get_contents(self::FIXTURE), true);

        $draft = (new DraftMapper())->map($data['picks_bans']);

        $this->assertCount(10, $draft->getPicks());
        $this->assertCount(14, $draft->getBans());
        $this->assertTrue($draft->getBans()[0]->isDire());
        $this->assertSame(34, $draft->getBans()[0]->getHeroId()->getValue());
        $this->assertSame(0, $draft->getBans()[0]->getDraftOrder()->getValue());
        $this->assertTrue($draft->getBans()[2]->isRadiant());
        foreach ($draft->getPicks() as $pick) {
            $this->assertTrue($pick->isPick());
        }
    }

    public function test_mapReturnsEmptyDraftForNull()
    {
        $draft = (new DraftMapper())->map(null);

        $this->assertSame([], $draft->getPicks());
        $this->assertSame([], $draft->getBans());
    }

    public function test_mapReturnsEmptyDraftForEmptyList()
    {
        $draft = (new DraftMapper())->map([]);

        $this->assertSame([], $draft->getPicks());
        $this->assertSame([], $draft->getBans());
    }

    public function test_mapKeepsSourceOrderInsideLists()
    {
        $draft = (new DraftMapper())->map([
            ['is_pick' => true, 'hero_id' => 10, 'team' => 0, 'order' => 5],
            ['is_pick' => false, 'hero_id' => 20, 'team' => 1, 'order' => 1],
            ['is_pick' => true, 'hero_id' => 30, 'team' => 1, 'order' => 6],
        ]);

        $this->assertSame([10, 30], array_map(static fn ($d) => $d->getHeroId()->getValue(), $draft->getPicks()));
        $this->assertSame([20], array_map(static fn ($d) => $d->getHeroId()->getValue(), $draft->getBans()));
        $this->assertTrue($draft->getPicks()[0]->isRadiant());
    }

    #[DataProvider('invalidOrderProvider')]
    public function test_mapThrowsOnInvalidDraftOrder(int $order)
    {
        $this->expectException(InvalidDraftOrderException::class);

        (new DraftMapper())->map([['is_pick' => true, 'hero_id' => 10, 'team' => 0, 'order' => $order]]);
    }

    public static function invalidOrderProvider(): array
    {
        return ['negative' => [-1], 'too big' => [24]];
    }

    public function test_mapThrowsOnInvalidHeroId()
    {
        $this->expectException(InvalidHeroIdException::class);

        (new DraftMapper())->map([['is_pick' => true, 'hero_id' => -5, 'team' => 0, 'order' => 1]]);
    }

    public function test_mapThrowsValueErrorOnUnknownSide()
    {
        // сырую ошибку оборачивает только MatchMapper::toEntity()
        $this->expectException(ValueError::class);

        (new DraftMapper())->map([['is_pick' => true, 'hero_id' => 10, 'team' => 5, 'order' => 1]]);
    }
}
