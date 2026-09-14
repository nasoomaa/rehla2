<?php

declare(strict_types=1);

namespace Rehla\Forms\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class FormValidationFailed extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    /** @param array<string, list<string>> $errors */
    public function __construct(public readonly array $errors = [])
    {
        $this->problemCode = ProblemCode::FormValidationFailed;
        parent::__construct($this->problemCode->value);
    }
}
