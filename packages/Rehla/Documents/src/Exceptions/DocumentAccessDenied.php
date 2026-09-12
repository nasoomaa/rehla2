<?php

declare(strict_types=1);

namespace Rehla\Documents\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class DocumentAccessDenied extends RuntimeException
{
    public function __construct(
        public readonly ProblemCode $problemCode = ProblemCode::ForbiddenResource,
    ) {
        parent::__construct();
    }
}
