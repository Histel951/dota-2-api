<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlResult;
use PHPUnit\Framework\TestCase;

class GraphQlResultTest extends TestCase
{
    public function test_fieldReturnsArray()
    {
        $result = new GraphQlResult(['match' => ['id' => 1]]);

        $this->assertSame(['id' => 1], $result->field('match'));
    }

    public function test_nullFieldThrowsRequestExceptionWithFieldError()
    {
        $result = new GraphQlResult(
            ['match' => null, 'other' => null],
            [
                ['message' => 'other failed', 'path' => ['other']],
                ['message' => 'match failed', 'path' => ['match', 'id']],
            ],
        );

        $this->expectException(SourceRequestException::class);
        $this->expectExceptionMessage('match failed');

        $result->field('match');
    }

    public function test_nullFieldWithoutOwnErrorThrowsObjectNotFound()
    {
        $result = new GraphQlResult(['match' => null], [['message' => 'other', 'path' => ['x']]]);

        $this->expectException(SourceRequestException::class);
        $this->expectExceptionMessage('Object not found');

        $result->field('match');
    }

    public function test_missingFieldThrowsDataException()
    {
        $this->expectException(SourceDataException::class);

        (new GraphQlResult(['a' => []]))->field('match');
    }

    public function test_nonArrayFieldThrowsDataException()
    {
        $this->expectException(SourceDataException::class);

        (new GraphQlResult(['match' => 'str']))->field('match');
    }
}
