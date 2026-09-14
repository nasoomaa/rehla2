<?php

declare(strict_types=1);

namespace Rehla\Content\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class ContentPageNotFound extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    public function __construct()
    {
        $this->problemCode = ProblemCode::ContentPageNotFound;
        parent::__construct($this->problemCode->value);
    }
}
