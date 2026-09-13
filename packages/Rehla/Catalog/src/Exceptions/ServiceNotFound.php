<?php

declare(strict_types=1);

namespace Rehla\Catalog\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class ServiceNotFound extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    public function __construct()
    {
        $this->problemCode = ProblemCode::ServiceNotFound;
        parent::__construct($this->problemCode->value);
    }
}
