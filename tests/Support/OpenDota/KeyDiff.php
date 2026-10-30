<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Support\OpenDota;

/**
 * Сравнивает структуру двух ответов OpenDota (живого и сохранённого в фикстуре) по ключам.
 *
 * Не зависит от PHPUnit. Сравниваются:
 *  - корень ответа (если ответ — список, то первый элемент списка, пути с префиксом "[].");
 *  - массив players[] у матча — по объединению ключей всех игроков (пути "players[].key").
 *
 * Смена типа определяется через get_debug_type(); null против значения сменой типа не считается
 * (у OpenDota null встречается часто).
 */
final class KeyDiff
{
    /**
     * @param mixed $live ответ источника (декодированный JSON)
     * @param mixed $fixture содержимое фикстуры
     *
     * @return array{added: list<string>, removed: list<string>, changed: list<string>}
     *         changed — строки вида "key: int -> string" (тип в фикстуре -> тип в живом ответе)
     */
    public static function compare(mixed $live, mixed $fixture): array
    {
        $liveShape = self::shape($live);
        $fixtureShape = self::shape($fixture);

        $added = array_keys(array_diff_key($liveShape, $fixtureShape));
        $removed = array_keys(array_diff_key($fixtureShape, $liveShape));
        $changed = [];

        foreach (array_intersect_key($liveShape, $fixtureShape) as $path => $liveType) {
            $fixtureType = $fixtureShape[$path];

            if ($liveType === 'null' || $fixtureType === 'null' || $liveType === $fixtureType) {
                continue;
            }

            $changed[] = sprintf('%s: %s -> %s', $path, $fixtureType, $liveType);
        }

        sort($added);
        sort($removed);
        sort($changed);

        return ['added' => $added, 'removed' => $removed, 'changed' => $changed];
    }

    /**
     * Готовые строки для печати: "+ key", "- key", "~ key: int -> string".
     *
     * @param array{added: list<string>, removed: list<string>, changed: list<string>} $diff
     * @return list<string>
     */
    public static function format(array $diff): array
    {
        return [
            ...array_map(static fn(string $k): string => '+ ' . $k, $diff['added']),
            ...array_map(static fn(string $k): string => '- ' . $k, $diff['removed']),
            ...array_map(static fn(string $k): string => '~ ' . $k, $diff['changed']),
        ];
    }

    /**
     * Плоское описание структуры: путь => тип (несколько типов склеены через "|", отсортированы).
     *
     * @return array<string, string>
     */
    private static function shape(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            $first = $data[0] ?? null;

            return is_array($first) ? self::shapeOfObjects([$first], '[].') : [];
        }

        $shape = self::shapeOfObjects([$data], '');

        if (isset($data['players']) && is_array($data['players']) && array_is_list($data['players'])) {
            $players = array_values(array_filter($data['players'], 'is_array'));
            $shape += self::shapeOfObjects($players, 'players[].');
        }

        return $shape;
    }

    /**
     * Объединение ключей (верхнего уровня) нескольких объектов.
     *
     * @param list<array<mixed>> $objects
     * @return array<string, string>
     */
    private static function shapeOfObjects(array $objects, string $prefix): array
    {
        /** @var array<string, array<string, true>> $types */
        $types = [];

        foreach ($objects as $object) {
            foreach ($object as $key => $value) {
                $types[$prefix . $key][get_debug_type($value)] = true;
            }
        }

        $shape = [];
        foreach ($types as $path => $seen) {
            $names = array_keys($seen);
            // null игнорируем, если у ключа есть значения других типов
            $nonNull = array_values(array_filter($names, static fn(string $t): bool => $t !== 'null'));
            $names = $nonNull === [] ? ['null'] : $nonNull;
            sort($names);
            $shape[$path] = implode('|', $names);
        }

        return $shape;
    }
}
