<?php

declare(strict_types=1);

namespace Rehla\Travelers\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class InvalidTraveler extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    public function __construct()
    {
        $this->problemCode = ProblemCode::TravelerInvalidDates;
        parent::__construct($this->problemCode->value);
    }
}
