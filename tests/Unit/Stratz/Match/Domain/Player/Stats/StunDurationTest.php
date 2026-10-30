<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Match\Domain\Player\Stats;

use Histel951\Dota2Api\Stratz\Match\Domain\Exception\InvalidStunDurationException;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\StunDuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StunDurationTest extends TestCase
{
    #[DataProvider('validProvider')]
    public function test_validDurationIsKept(float $seconds)
    {
        $this->assertSame($seconds, (new StunDuration($seconds))->getSeconds());
    }

    /**
     * @return array<string, array{float}>
     */
    public static function validProvider(): array
    {
        return ['zero' => [0.0], 'fraction' => [12.14], 'large' => [101.83]];
    }

    #[DataProvider('invalidProvider')]
    public function test_negativeDurationThrows(float $seconds)
    {
        $this->expectException(InvalidStunDurationException::class);

        new StunDuration($seconds);
    }

    /**
     * @return array<string, array{float}>
     */
    public static function invalidProvider(): array
    {
        return ['small' => [-0.01], 'integer' => [-1.0]];
    }
}
