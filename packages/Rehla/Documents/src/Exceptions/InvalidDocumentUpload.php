<?php

declare(strict_types=1);

namespace Rehla\Documents\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class InvalidDocumentUpload extends RuntimeException
{
    public function __construct(public readonly ProblemCode $problemCode)
    {
        parent::__construct();
    }
}
