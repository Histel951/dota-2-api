<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\SourceFields;

use Histel951\Dota2Api\Tests\Support\OpenDota\SourceFieldsChecker;
use PHPUnit\Framework\TestCase;

class MatchSourceFieldsTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';

    public function test_fixtureContainsAllFieldsExpectedByCode()
    {
        $match = json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([], SourceFieldsChecker::checkMatch($match));
    }
}
