<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Support\OpenDota;

use Histel951\Dota2Api\Tests\Support\OpenDota\SourceFieldsChecker;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourceFieldsCheckerTest extends TestCase
{
    private const string SOURCE = 'Some::FIELDS';

    public function test_readFieldsReturnsPrivateConstant()
    {
        $this->assertSame(['a', 'b'], SourceFieldsChecker::readFields(FieldsStub::class, 'LIST'));
    }

    #[DataProvider('invalidConstantProvider')]
    public function test_readFieldsThrowsOnMissingOrNonArrayConstant(string $constant)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(FieldsStub::class . '::' . $constant);

        SourceFieldsChecker::readFields(FieldsStub::class, $constant);
    }

    public static function invalidConstantProvider(): array
    {
        return [
            'missing' => ['RENAMED'],
            'not an array' => ['SCALAR'],
        ];
    }

    public function test_checkRequiredPassesOnValidData()
    {
        $items = [['a' => 1, 'b' => 0], ['a' => false, 'b' => '']];

        $this->assertSame([], SourceFieldsChecker::checkRequired($items, ['a', 'b'], 'ctx', self::SOURCE));
    }

    public function test_checkRequiredReportsMissingKeyWithIndexAndSource()
    {
        $violations = SourceFieldsChecker::checkRequired([['a' => 1], ['b' => 1]], ['a'], 'ctx', self::SOURCE);

        $this->assertSame(['ctx [1]: required "a" is missing or null (Some::FIELDS)'], $violations);
    }

    public function test_checkRequiredReportsNullValue()
    {
        $violations = SourceFieldsChecker::checkRequired([['a' => null]], ['a'], 'ctx', self::SOURCE);

        $this->assertCount(1, $violations);
    }

    public function test_checkOptionalPassesWhenKeyPresentInAtLeastOneElementEvenIfNull()
    {
        $items = [['x' => 1], ['name' => null]];

        $this->assertSame([], SourceFieldsChecker::checkOptional($items, ['name'], 'ctx', self::SOURCE));
    }

    public function test_checkOptionalReportsKeyAbsentEverywhere()
    {
        $violations = SourceFieldsChecker::checkOptional([['x' => 1], ['y' => 1]], ['name'], 'ctx', self::SOURCE);

        $this->assertSame(['ctx: optional "name" is absent in all 2 element(s) (Some::FIELDS)'], $violations);
    }

    public function test_checkEachPassesWithNullValues()
    {
        $items = [['a' => null], ['a' => 5]];

        $this->assertSame([], SourceFieldsChecker::checkEach($items, ['a'], 'players', self::SOURCE));
    }

    public function test_checkEachReportsPlayerIndexAndKey()
    {
        $items = [['lane_role' => 1], ['lane_role' => 2], ['lane_role' => 3], ['hero_id' => 1]];

        $violations = SourceFieldsChecker::checkEach($items, ['lane_role'], 'players', self::SOURCE);

        $this->assertSame(['players [3]: missing "lane_role" (Some::FIELDS)'], $violations);
    }

    public function test_checkNestedAnyPassesWhenOneElementHasPath()
    {
        $items = [['item_uses' => []], ['item_uses' => ['smoke' => 1]], []];

        $this->assertSame([], SourceFieldsChecker::checkNestedAny($items, ['item_uses.smoke'], 'p', self::SOURCE));
    }

    #[DataProvider('absentNestedProvider')]
    public function test_checkNestedAnyReportsPathAbsentEverywhere(array $items)
    {
        $violations = SourceFieldsChecker::checkNestedAny($items, ['item_uses.smoke'], 'p', self::SOURCE);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('item_uses.smoke', $violations[0]);
    }

    public static function absentNestedProvider(): array
    {
        return [
            'no parent' => [[['a' => 1], ['b' => 2]]],
            'parent without child' => [[['item_uses' => ['other' => 1]]]],
            'parent not an array' => [[['item_uses' => null]]],
            'empty list' => [[]],
        ];
    }

    public function test_checkMatchReportsMissingPlayerFieldAndNestedAndObjectives()
    {
        $violations = SourceFieldsChecker::checkMatch([
            'match_id' => 1,
            'players' => [['hero_id' => 1]],
            'objectives' => [['type' => 'OTHER']],
            'picks_bans' => [['hero_id' => 1]],
        ]);

        $this->assertContains('matches/1 players [0]: missing "lane_role" (MatchExtractor::PLAYER_FIELDS)', $violations);
        $this->assertNotEmpty(array_filter($violations, static fn(string $v): bool => str_contains($v, 'smoke_of_deceit')));
        $this->assertNotEmpty(array_filter($violations, static fn(string $v): bool => str_contains($v, 'CHAT_MESSAGE_MINIBOSS_KILL')));
        $this->assertNotEmpty(array_filter($violations, static fn(string $v): bool => str_contains($v, 'picks_bans [0]: missing "order"')));
    }

    public function test_checkMatchReportsMinibossKillWithoutPlayerSlot()
    {
        $violations = SourceFieldsChecker::checkMatch([
            'match_id' => 1,
            'players' => [],
            'objectives' => [['type' => 'CHAT_MESSAGE_MINIBOSS_KILL']],
        ]);

        $this->assertContains(
            'matches/1 objectives [0]: missing "player_slot" (MatchOpenDotaMapper, hardcoded)',
            $violations,
        );
    }

    public function test_checkMatchReportsMissingPlayers()
    {
        $violations = SourceFieldsChecker::checkMatch(['match_id' => 1]);

        $this->assertContains('matches/1: "players" is missing or empty', $violations);
    }

    public function test_checkTeamsReportsEmptyResponse()
    {
        $this->assertSame(['teams: response is empty'], SourceFieldsChecker::checkTeams([]));
    }

    public function test_checkTeamHeroesWorksWithoutOptionalConstant()
    {
        $violations = SourceFieldsChecker::checkTeamHeroes([['hero_id' => 1]]);

        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('TeamHeroOpenDotaMapper::REQUIRED_FIELDS', $violations[0]);
    }

    public function test_checkTeamReportsNullRequiredField()
    {
        $violations = SourceFieldsChecker::checkTeam(['team_id' => 5, 'rating' => null]);

        $this->assertNotEmpty($violations);
        $this->assertStringStartsWith('teams/5 [0]:', $violations[0]);
    }
}

/**
 * Заглушка с private-константами для проверки чтения через Reflection.
 */
final class FieldsStub
{
    private const array LIST = ['a', 'b'];
    private const string SCALAR = 'x';
}
