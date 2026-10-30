<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\SharedKernel;

use Histel951\Dota2Api\SharedKernel\Exception\InvalidKDAException;
use Histel951\Dota2Api\SharedKernel\KDA;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KDATest extends TestCase
{
    #[DataProvider('coefficientProvider')]
    public function test_getCoefficient(int $kills, int $deaths, int $assists, float $expected)
    {
        $kda = new KDA(kills: $kills, deaths: $deaths, assists: $assists);

        $this->assertSame($expected, $kda->getCoefficient());
    }

    public static function coefficientProvider(): array
    {
        return [
            'deaths divide the sum' => [3, 2, 5, 4.0],
            'no deaths: divisor is 1' => [3, 0, 5, 8.0],
            'all zero' => [0, 0, 0, 0.0],
            'rounded to 2 digits' => [1, 3, 1, 0.67],
        ];
    }

    public function test_negativeValueThrows()
    {
        $this->expectException(InvalidKDAException::class);

        new KDA(kills: -1);
    }
}
