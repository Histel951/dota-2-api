<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Support\OpenDota;

use Histel951\Dota2Api\Tests\Support\OpenDota\KeyDiff;
use PHPUnit\Framework\TestCase;

final class KeyDiffTest extends TestCase
{
    public function test_compareReturnsEmptyDiffForSameStructure(): void
    {
        $diff = KeyDiff::compare(['a' => 1, 'b' => 'x'], ['a' => 2, 'b' => 'y']);

        self::assertSame(['added' => [], 'removed' => [], 'changed' => []], $diff);
    }

    public function test_compareFindsAddedAndRemovedRootKeys(): void
    {
        $diff = KeyDiff::compare(['a' => 1, 'new' => 1], ['a' => 1, 'gone' => 1]);

        self::assertSame(['new'], $diff['added']);
        self::assertSame(['gone'], $diff['removed']);
        self::assertSame([], $diff['changed']);
    }

    public function test_compareFindsTypeChange(): void
    {
        $diff = KeyDiff::compare(['a' => 'text', 'b' => [1]], ['a' => 5, 'b' => [1]]);

        self::assertSame(['a: int -> string'], $diff['changed']);
    }

    public function test_compareIgnoresNullVersusValue(): void
    {
        $diff = KeyDiff::compare(['a' => null, 'b' => 1], ['a' => 1, 'b' => null]);

        self::assertSame(['added' => [], 'removed' => [], 'changed' => []], $diff);
    }

    public function test_compareUsesFirstElementOfLists(): void
    {
        $live = [['id' => 1, 'extra' => true], ['id' => 2, 'other' => 1]];
        $fixture = [['id' => 1, 'old' => 1]];

        $diff = KeyDiff::compare($live, $fixture);

        self::assertSame(['[].extra'], $diff['added']);
        self::assertSame(['[].old'], $diff['removed']);
    }

    public function test_compareTreatsEmptyListAsNoKeys(): void
    {
        $diff = KeyDiff::compare([], [['id' => 1]]);

        self::assertSame(['[].id'], $diff['removed']);
        self::assertSame([], $diff['added']);
    }

    public function test_compareUnitesKeysOfAllPlayers(): void
    {
        $live = ['match_id' => 1, 'players' => [['a' => 1], ['a' => 1, 'position_est' => 3]]];
        $fixture = ['match_id' => 1, 'players' => [['a' => 1, 'gone' => 0], ['a' => 1]]];

        $diff = KeyDiff::compare($live, $fixture);

        self::assertSame(['players[].position_est'], $diff['added']);
        self::assertSame(['players[].gone'], $diff['removed']);
    }

    public function test_compareDoesNotDescendIntoOtherNestedKeys(): void
    {
        $diff = KeyDiff::compare(['obj' => ['x' => 1]], ['obj' => ['y' => 1]]);

        self::assertSame(['added' => [], 'removed' => [], 'changed' => []], $diff);
    }

    public function test_compareNullPlayerValueDoesNotMaskOtherPlayersType(): void
    {
        $live = ['players' => [['k' => null], ['k' => 'str']]];
        $fixture = ['players' => [['k' => 1]]];

        $diff = KeyDiff::compare($live, $fixture);

        self::assertSame(['players[].k: int -> string'], $diff['changed']);
    }

    public function test_compareToleratesNonArrayFixture(): void
    {
        $diff = KeyDiff::compare(['a' => 1], null);

        self::assertSame(['a'], $diff['added']);
    }

    public function test_formatPrintsPrefixedLines(): void
    {
        $lines = KeyDiff::format(['added' => ['n'], 'removed' => ['r'], 'changed' => ['t: int -> string']]);

        self::assertSame(['+ n', '- r', '~ t: int -> string'], $lines);
    }
}
