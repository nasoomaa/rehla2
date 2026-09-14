<?php

declare(strict_types=1);

namespace Rehla\Forms\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class FormVersionOutdated extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    public function __construct(public readonly string $currentVersionId)
    {
        $this->problemCode = ProblemCode::FormVersionOutdated;
        parent::__construct($this->problemCode->value);
    }
}
