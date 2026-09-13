<?php

declare(strict_types=1);

namespace Rehla\Travelers\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class TravelerNotFound extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    public function __construct()
    {
        $this->problemCode = ProblemCode::TravelerNotFound;
        parent::__construct($this->problemCode->value);
    }
}
