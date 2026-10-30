<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\SharedKernel\Exception;

use Histel951\Dota2Api\Exception\Dota2ApiExceptionInterface;

class DomainException extends \Exception implements Dota2ApiExceptionInterface {}
