<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class Dota2ApiBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}