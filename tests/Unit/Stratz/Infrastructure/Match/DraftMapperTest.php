<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure\Match;

use Histel951\Dota2Api\SharedKernel\Exception\InvalidDraftOrderException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidHeroIdException;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\DraftMapper;
use Histel951\Dota2Api\Tests\Support\Stratz\StratzFixtures;
use PHPUnit\Framework\TestCase;

class DraftMapperTest extends TestCase
{
    public function test_mapNullGivesNull()
    {
        $this->assertNull((new DraftMapper())->map(null));
    }

    public function test_mapEmptyListGivesEmptyDraft()
    {
        $draft = (new DraftMapper())->map([]);

        $this->assertNotNull($draft);
        $this->assertSame([], $draft->getPicks());
        $this->assertSame([], $draft->getBans());
    }

    public function test_mapFixtureGivesTenPicksAndFourteenBans()
    {
        $pickBans = StratzFixtures::match(StratzFixtures::PARSED_MATCH_ID)['pickBans'];

        $draft = (new DraftMapper())->map($pickBans);

        $this->assertNotNull($draft);
        $this->assertCount(10, $draft->getPicks());
        $this->assertCount(14, $draft->getBans());

        // первый бан: heroId 80, order 0, Dire
        $firstBan = $draft->getBans()[0];
        $this->assertSame(80, $firstBan->getHeroId()->getValue());
        $this->assertSame(0, $firstBan->getDraftOrder()->getValue());
        $this->assertTrue($firstBan->isDire());
        $this->assertFalse($firstBan->isPick());

        // первый пик: heroId 69, order 7, Dire
        $firstPick = $draft->getPicks()[0];
        $this->assertSame(69, $firstPick->getHeroId()->getValue());
        $this->assertSame(7, $firstPick->getDraftOrder()->getValue());
        $this->assertTrue($firstPick->isDire());
        $this->assertTrue($firstPick->isPick());

        // последний пик: heroId 17, order 23, Radiant
        $lastPick = $draft->getPicks()[9];
        $this->assertSame(17, $lastPick->getHeroId()->getValue());
        $this->assertSame(23, $lastPick->getDraftOrder()->getValue());
        $this->assertTrue($lastPick->isRadiant());
    }

    public function test_mapSplitsSidesByIsRadiant()
    {
        $draft = (new DraftMapper())->map([
            ['isPick' => true, 'heroId' => 5, 'order' => 1, 'isRadiant' => true],
            ['isPick' => true, 'heroId' => 6, 'order' => 2, 'isRadiant' => false],
        ]);

        $this->assertNotNull($draft);
        $this->assertTrue($draft->getPicks()[0]->isRadiant());
        $this->assertTrue($draft->getPicks()[1]->isDire());
        $this->assertSame([], $draft->getBans());
    }

    public function test_mapMissingIsPickIsTreatedAsBan()
    {
        $draft = (new DraftMapper())->map([['heroId' => 5, 'order' => 1, 'isRadiant' => true]]);

        $this->assertNotNull($draft);
        $this->assertCount(1, $draft->getBans());
        $this->assertSame([], $draft->getPicks());
    }

    public function test_mapInvalidHeroIdThrows()
    {
        $this->expectException(InvalidHeroIdException::class);

        (new DraftMapper())->map([['isPick' => true, 'heroId' => 0, 'order' => 1, 'isRadiant' => true]]);
    }

    public function test_mapNegativeOrderThrows()
    {
        $this->expectException(InvalidDraftOrderException::class);

        (new DraftMapper())->map([['isPick' => true, 'heroId' => 5, 'order' => -1, 'isRadiant' => true]]);
    }

    public function test_mapOrderAboveRangeThrows()
    {
        $this->expectException(InvalidDraftOrderException::class);

        (new DraftMapper())->map([['isPick' => false, 'heroId' => 5, 'order' => 24, 'isRadiant' => true]]);
    }
}
