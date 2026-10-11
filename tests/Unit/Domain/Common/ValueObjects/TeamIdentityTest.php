<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamName;
use PHPUnit\Framework\TestCase;

class TeamIdentityTest extends TestCase
{
    public function test_teamNameAcceptsNull()
    {
        $name = new TeamName(null);

        $this->assertNull($name->getValue());
        $this->assertSame('', (string)$name);
    }

    public function test_teamIdAcceptsNull()
    {
        $id = new TeamId(null);

        $this->assertNull($id->getValue());
        $this->assertSame('', (string)$id);
    }

    public function test_teamIdNormalizesInt()
    {
        $id = new TeamId(8261500);

        $this->assertSame('8261500', $id->getValue());
        $this->assertSame(8261500, $id->toInt());
    }
}
