<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\SharedKernel;

use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
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
