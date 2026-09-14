<?php

declare(strict_types=1);

namespace Rehla\Content\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class ContentSlugExists extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    public function __construct()
    {
        $this->problemCode = ProblemCode::ContentSlugExists;
        parent::__construct($this->problemCode->value);
    }
}
