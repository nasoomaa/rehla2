<?php

declare(strict_types=1);

namespace Rehla\Forms\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class FormNotFound extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    public function __construct()
    {
        $this->problemCode = ProblemCode::FormNotFound;
        parent::__construct($this->problemCode->value);
    }
}
