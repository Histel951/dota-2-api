<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure\Team;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TeamMapperTest extends TestCase
{
    private TeamMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TeamMapper();
    }

    public function test_toEntityMapsFixtureTeam()
    {
        $team = $this->mapper->toEntity($this->fixture());

        $this->assertSame('8261500', $team->getId()->getValue());
        $this->assertSame('Xtreme Gaming', $team->getName()?->getValue());
        $this->assertSame('XG', $team->getTag()?->getValue());
        $this->assertSame(1148, $team->getRecord()->getGamesPlayed());
        $this->assertSame(628, $team->getRecord()->getGamesWon());
        $this->assertSame(520, $team->getRecord()->getGamesLost());
        $this->assertSame(1792247338, $team->getLastMatchTime()->getTimestamp());
    }

    #[DataProvider('blankProvider')]
    public function test_blankNameAndTagBecomeNull(mixed $blank)
    {
        $team = $this->mapper->toEntity(['name' => $blank, 'tag' => $blank] + $this->fixture());

        $this->assertNull($team->getName());
        $this->assertNull($team->getTag());
    }

    public static function blankProvider(): array
    {
        return ['null' => [null], 'empty' => [''], 'spaces' => ['   ']];
    }

    public function test_absentNameAndTagBecomeNull()
    {
        $data = $this->fixture();
        unset($data['name'], $data['tag']);

        $team = $this->mapper->toEntity($data);

        $this->assertNull($team->getName());
        $this->assertNull($team->getTag());
    }

    #[DataProvider('requiredProvider')]
    public function test_missingRequiredFieldThrows(string $field)
    {
        $data = $this->fixture();
        unset($data[$field]);

        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity($data);
    }

    public static function requiredProvider(): array
    {
        return [['id'], ['winCount'], ['lossCount'], ['lastMatchDateTime']];
    }

    public function test_unexpectedTypeThrowsSourceDataException()
    {
        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity(['winCount' => 'many'] + $this->fixture());
    }

    public function test_emptyDataThrows()
    {
        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity([]);
    }

    private function fixture(): array
    {
        $json = json_decode(
            file_get_contents(__DIR__ . '/../../../../Fixtures/stratz/team_8261500.json'),
            true,
        );

        return $json['data']['team'];
    }
}
