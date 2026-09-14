<?php

declare(strict_types=1);

namespace Rehla\Content\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class ContentValidationFailed extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    /** @param array<string, list<string>> $errors */
    public function __construct(public readonly array $errors = [])
    {
        $this->problemCode = ProblemCode::ContentValidationFailed;
        parent::__construct($this->problemCode->value);
    }
}
