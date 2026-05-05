<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Mapper;

use DateTimeImmutable;
use Histel951\Dota2Api\Infrastructure\Exceptions\MappingException;
use Throwable;

abstract class AbstractMapper
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws MappingException
     */
    abstract function toEntity(array $data): object;

    /**
     * @param array $data
     * @return array
     *
     * @throws MappingException
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
     * @throws MappingException
     */
    protected function validate(array $data, array $requiredFields): void
    {
        if (empty($data)) {
            throw new MappingException('Empty data response');
        }

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new MappingException(
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
     * @throws MappingException
     */
    protected function createDateTime(int $timestamp): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable('@' . $timestamp);
        } catch (Throwable $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}