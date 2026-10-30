<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Mapping;

use DateTimeImmutable;
use Histel951\Dota2Api\Exception\SourceDataException;
use Throwable;

abstract class AbstractMapper
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws SourceDataException
     */
    abstract function toEntity(array $data): object;

    /**
     * @param array $data
     * @return array
     *
     * @throws SourceDataException
     */
    public function toCollection(array $data): array
    {
        $result = [];
        foreach ($data as $arrayEntity) {
            $result[] = $this->toEntity($arrayEntity);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @param string[] $requiredFields
     * @throws SourceDataException
     */
    protected function validate(array $data, array $requiredFields): void
    {
        if (empty($data)) {
            throw new SourceDataException('Empty data response');
        }

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new SourceDataException(
                    sprintf('Missing required field "%s" in API response', $field)
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param string[] $optionalFields
     */
    protected function extractOptionalFields(array $data, array $optionalFields): array
    {
        $result = [];
        foreach ($optionalFields as $field) {
            $result[$field] = $data[$field] ?? null;
        }

        return $result;
    }

    /**
     * @throws SourceDataException
     */
    protected function createDateTime(int $timestamp): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable('@' . $timestamp);
        } catch (Throwable $e) {
            throw new SourceDataException($e->getMessage(), $e->getCode(), $e);
        }
    }
}