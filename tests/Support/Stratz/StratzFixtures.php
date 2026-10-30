<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Support\Stratz;

use RuntimeException;

/**
 * Читает реальные ответы STRATZ из tests/Fixtures/stratz
 */
final class StratzFixtures
{
    public const int PARSED_MATCH_ID = 9026055036;
    public const int UNPARSED_MATCH_ID = 8794744726;

    /**
     * Корневое поле m0 фикстуры матча
     *
     * @return array<string, mixed>
     */
    public static function match(int $id): array
    {
        return json_decode(self::read($id), true, flags: JSON_THROW_ON_ERROR)['data']['m0'];
    }

    /**
     * Сырой JSON ответа матча (`{"data":{"m0":...}}`)
     */
    public static function matchJson(int $id): string
    {
        return self::read($id);
    }

    private static function read(int $id): string
    {
        $path = __DIR__ . '/../../Fixtures/stratz/match_' . $id . '.json';
        $json = file_get_contents($path);

        if (false === $json) {
            throw new RuntimeException('Cannot read fixture ' . $path);
        }

        return $json;
    }
}
