<?php

declare(strict_types=1);

namespace Rehla\Travelers\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class DuplicatePassport extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    public function __construct()
    {
        $this->problemCode = ProblemCode::TravelerPassportConflict;
        parent::__construct($this->problemCode->value);
    }
}
