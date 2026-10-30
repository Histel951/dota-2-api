<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Team\Domain\History;

use Histel951\Dota2Api\Stratz\Team\Domain\Exception\InvalidScoreException;
use Histel951\Dota2Api\Stratz\Team\Domain\History\Score;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScoreTest extends TestCase
{
    public function test_createsScoreWithZeroAndPositiveKills()
    {
        $score = new Score(0, 25);

        $this->assertSame(0, $score->getRadiantKills());
        $this->assertSame(25, $score->getDireKills());
    }

    #[DataProvider('negativeProvider')]
    public function test_negativeKillsThrow(int $radiant, int $dire)
    {
        $this->expectException(InvalidScoreException::class);

        new Score($radiant, $dire);
    }

    public static function negativeProvider(): array
    {
        return [
            'radiant' => [-1, 5],
            'dire' => [5, -1],
            'both' => [-1, -1],
        ];
    }
}
