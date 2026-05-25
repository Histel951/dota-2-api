<?php

namespace Histel951\Dota2Api\Infrastructure\Provider\Contracts;

interface ExtractorInterface
{
    public function extract(string $rawJson): array;
}