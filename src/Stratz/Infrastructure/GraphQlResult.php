<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;

/**
 * Разобранный ответ GraphQL: объект data и список errors (при частичном ответе заполнены оба).
 */
final readonly class GraphQlResult
{
    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $errors
     */
    public function __construct(
        private array $data,
        private array $errors = [],
    )
    {
    }

    /**
     * Корневое поле ответа как массив.
     *
     * @return array<mixed>
     * @throws SourceRequestException значение null (текст ошибки поля или 'Object not found')
     * @throws SourceDataException поля нет или оно не массив
     */
    public function field(string $name): array
    {
        if (array_key_exists($name, $this->data) && null === $this->data[$name]) {
            throw new SourceRequestException($this->errorFor($name) ?? 'Object not found');
        }

        if (!isset($this->data[$name]) || !is_array($this->data[$name])) {
            throw new SourceDataException(
                sprintf('Field "%s" is missing or is not an object in GraphQL response', $name)
            );
        }

        return $this->data[$name];
    }

    private function errorFor(string $name): ?string
    {
        foreach ($this->errors as $error) {
            $path = $error['path'] ?? null;

            if (is_array($path) && ($path[0] ?? null) === $name && is_string($error['message'] ?? null)) {
                return $error['message'];
            }
        }

        return null;
    }
}
