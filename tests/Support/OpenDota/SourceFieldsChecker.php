<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Support\OpenDota;

use Histel951\Dota2Api\Infrastructure\OpenDota\Match\MatchExtractor;
use Histel951\Dota2Api\Infrastructure\OpenDota\Match\MatchMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamHeroMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamMatchMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamPlayerMapper;
use LogicException;
use ReflectionClass;

/**
 * Проверяет, что в ответе OpenDota есть ключи, которые читает код бандла.
 *
 * Не зависит от PHPUnit: используется и тестами на фикстурах, и dev-скриптом против живого API.
 * Все методы возвращают список нарушений (пустой массив = всё в порядке).
 */
final class SourceFieldsChecker
{
    /**
     * Вложенные ключи игрока матча. Захардкожены в PlayerMapper (не вынесены в константы),
     * поэтому перечислены здесь явно. Путь через точку, достаточно одного игрока с ключом.
     */
    private const array MATCH_PLAYER_NESTED_ANY = [
        'item_uses.smoke_of_deceit',
        'ability_uses.ability_lamp_use',
    ];

    /** Ключи каждого элемента picks_bans[]; захардкожены в DraftMapper. */
    private const array MATCH_PICKS_BANS_EACH = ['hero_id', 'order', 'team', 'is_pick'];

    /** Тип события objectives[], которое читает PlayerMapper. */
    private const string MINIBOSS_OBJECTIVE_TYPE = 'CHAT_MESSAGE_MINIBOSS_KILL';

    /**
     * Читает список полей из (в том числе private) константы класса.
     *
     * @param class-string $class
     * @return list<string>
     *
     * @throws LogicException если константы нет или она не массив
     */
    public static function readFields(string $class, string $constant): array
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->hasConstant($constant)) {
            throw new LogicException(sprintf('Constant %s::%s not found.', $class, $constant));
        }

        $value = $reflection->getConstant($constant);

        if (!is_array($value)) {
            throw new LogicException(sprintf('Constant %s::%s is not an array.', $class, $constant));
        }

        return array_values($value);
    }

    /**
     * REQUIRED: ключ есть и не null в каждом элементе (как AbstractMapper::validate()).
     *
     * @param list<array<string, mixed>> $items
     * @param list<string> $fields
     * @return string[]
     */
    public static function checkRequired(array $items, array $fields, string $context, string $source): array
    {
        $violations = [];

        foreach ($items as $index => $item) {
            foreach ($fields as $field) {
                if (!is_array($item) || !isset($item[$field])) {
                    $violations[] = sprintf(
                        '%s [%s]: required "%s" is missing or null (%s)',
                        $context,
                        $index,
                        $field,
                        $source,
                    );
                }
            }
        }

        return $violations;
    }

    /**
     * OPTIONAL: ключ есть (null допустим) хотя бы в одном элементе.
     *
     * @param list<array<string, mixed>> $items
     * @param list<string> $fields
     * @return string[]
     */
    public static function checkOptional(array $items, array $fields, string $context, string $source): array
    {
        $violations = [];

        foreach ($fields as $field) {
            $found = false;

            foreach ($items as $item) {
                if (is_array($item) && array_key_exists($field, $item)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $violations[] = sprintf(
                    '%s: optional "%s" is absent in all %d element(s) (%s)',
                    $context,
                    $field,
                    count($items),
                    $source,
                );
            }
        }

        return $violations;
    }

    /**
     * EACH: ключ есть (null допустим) в каждом элементе.
     *
     * @param list<array<string, mixed>> $items
     * @param list<string> $fields
     * @return string[]
     */
    public static function checkEach(array $items, array $fields, string $context, string $source): array
    {
        $violations = [];

        foreach ($items as $index => $item) {
            foreach ($fields as $field) {
                if (!is_array($item) || !array_key_exists($field, $item)) {
                    $violations[] = sprintf('%s [%s]: missing "%s" (%s)', $context, $index, $field, $source);
                }
            }
        }

        return $violations;
    }

    /**
     * NESTED-ANY: вложенный путь (ключи через точку) встречается хотя бы у одного элемента.
     *
     * @param list<array<string, mixed>> $items
     * @param list<string> $paths
     * @return string[]
     */
    public static function checkNestedAny(array $items, array $paths, string $context, string $source): array
    {
        $violations = [];

        foreach ($paths as $path) {
            $segments = explode('.', $path);
            $found = false;

            foreach ($items as $item) {
                if (self::hasPath($item, $segments)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $violations[] = sprintf(
                    '%s: nested "%s" is absent in all %d element(s) (%s)',
                    $context,
                    $path,
                    count($items),
                    $source,
                );
            }
        }

        return $violations;
    }

    /**
     * Проверка ответа matches/{id}.
     *
     * @param array<string, mixed> $match
     * @return string[]
     */
    public static function checkMatch(array $match): array
    {
        $context = sprintf('matches/%s', $match['match_id'] ?? '?');
        $root = [$match];

        $violations = [
            ...self::checkRequired(
                $root,
                self::readFields(MatchExtractor::class, 'REQUIRED_FIELDS'),
                $context,
                'MatchExtractor::REQUIRED_FIELDS',
            ),
            ...self::checkOptional(
                $root,
                self::readFields(MatchExtractor::class, 'OPTIONAL_FIELDS'),
                $context,
                'MatchExtractor::OPTIONAL_FIELDS',
            ),
            ...self::checkRequired(
                $root,
                self::readFields(MatchMapper::class, 'REQUIRED_FIELDS'),
                $context,
                'MatchMapper::REQUIRED_FIELDS',
            ),
            ...self::checkOptional(
                $root,
                self::readFields(MatchMapper::class, 'OPTIONAL_FIELDS'),
                $context,
                'MatchMapper::OPTIONAL_FIELDS',
            ),
        ];

        $players = $match['players'] ?? null;
        if (!is_array($players) || $players === []) {
            $violations[] = sprintf('%s: "players" is missing or empty', $context);
        } else {
            $violations = [
                ...$violations,
                ...self::checkEach(
                    $players,
                    self::readFields(MatchExtractor::class, 'PLAYER_FIELDS'),
                    $context . ' players',
                    'MatchExtractor::PLAYER_FIELDS',
                ),
                ...self::checkNestedAny(
                    $players,
                    self::MATCH_PLAYER_NESTED_ANY,
                    $context . ' players',
                    'MatchMapper, hardcoded',
                ),
            ];
        }

        return [...$violations, ...self::checkObjectives($match, $context), ...self::checkPicksBans($match, $context)];
    }

    /**
     * Проверка ответа teams/{id}.
     *
     * @param array<string, mixed> $team
     * @return string[]
     */
    public static function checkTeam(array $team): array
    {
        return self::checkMapperList(
            [$team],
            TeamMapper::class,
            sprintf('teams/%s', $team['team_id'] ?? '?'),
        );
    }

    /**
     * Проверка ответа teams (список).
     *
     * @param list<array<string, mixed>> $teams
     * @return string[]
     */
    public static function checkTeams(array $teams): array
    {
        return self::checkMapperList($teams, TeamMapper::class, 'teams');
    }

    /**
     * Проверка ответа teams/{id}/matches (список).
     *
     * @param list<array<string, mixed>> $matches
     * @return string[]
     */
    public static function checkTeamMatches(array $matches): array
    {
        return self::checkMapperList($matches, TeamMatchMapper::class, 'teams/{id}/matches');
    }

    /**
     * Проверка ответа teams/{id}/players (список).
     *
     * @param list<array<string, mixed>> $players
     * @return string[]
     */
    public static function checkTeamPlayers(array $players): array
    {
        return self::checkMapperList($players, TeamPlayerMapper::class, 'teams/{id}/players');
    }

    /**
     * Проверка ответа teams/{id}/heroes (список).
     *
     * @param list<array<string, mixed>> $heroes
     * @return string[]
     */
    public static function checkTeamHeroes(array $heroes): array
    {
        return self::checkMapperList($heroes, TeamHeroMapper::class, 'teams/{id}/heroes');
    }

    /**
     * REQUIRED/OPTIONAL маппера применяются к списку элементов. Пустой ответ — нарушение.
     * Константа OPTIONAL_FIELDS необязательна (у TeamHeroMapper её нет).
     *
     * @param list<array<string, mixed>> $items
     * @param class-string $mapper
     * @return string[]
     */
    private static function checkMapperList(array $items, string $mapper, string $context): array
    {
        if ($items === []) {
            return [sprintf('%s: response is empty', $context)];
        }

        $short = substr(strrchr($mapper, '\\') ?: '\\' . $mapper, 1);

        $violations = self::checkRequired(
            $items,
            self::readFields($mapper, 'REQUIRED_FIELDS'),
            $context,
            $short . '::REQUIRED_FIELDS',
        );

        if ((new ReflectionClass($mapper))->hasConstant('OPTIONAL_FIELDS')) {
            $violations = [
                ...$violations,
                ...self::checkOptional(
                    $items,
                    self::readFields($mapper, 'OPTIONAL_FIELDS'),
                    $context,
                    $short . '::OPTIONAL_FIELDS',
                ),
            ];
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $match
     * @return string[]
     */
    private static function checkObjectives(array $match, string $context): array
    {
        $objectives = $match['objectives'] ?? null;
        $source = 'MatchMapper, hardcoded';

        if (!is_array($objectives) || $objectives === []) {
            return [sprintf('%s: "objectives" is missing or empty (%s)', $context, $source)];
        }

        $violations = self::checkEach($objectives, ['type'], $context . ' objectives', $source);

        $minibossKills = array_filter(
            $objectives,
            static fn(mixed $o): bool => is_array($o) && ($o['type'] ?? null) === self::MINIBOSS_OBJECTIVE_TYPE,
        );

        if ($minibossKills === []) {
            $violations[] = sprintf(
                '%s objectives: no element with type "%s" (%s)',
                $context,
                self::MINIBOSS_OBJECTIVE_TYPE,
                $source,
            );
        }

        return [...$violations, ...self::checkEach($minibossKills, ['player_slot'], $context . ' objectives', $source)];
    }

    /**
     * @param array<string, mixed> $match
     * @return string[]
     */
    private static function checkPicksBans(array $match, string $context): array
    {
        $picksBans = $match['picks_bans'] ?? null;

        if (!is_array($picksBans) || $picksBans === []) {
            return [sprintf('%s: "picks_bans" is missing or empty (MatchMapper, hardcoded)', $context)];
        }

        return self::checkEach(
            $picksBans,
            self::MATCH_PICKS_BANS_EACH,
            $context . ' picks_bans',
            'MatchMapper, hardcoded',
        );
    }

    /**
     * @param list<string> $segments
     */
    private static function hasPath(mixed $item, array $segments): bool
    {
        foreach ($segments as $segment) {
            if (!is_array($item) || !array_key_exists($segment, $item)) {
                return false;
            }
            $item = $item[$segment];
        }

        return true;
    }
}
