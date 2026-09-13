<?php

declare(strict_types=1);

namespace Rehla\Catalog\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class FulfillmentPolicyImmutable extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    public function __construct()
    {
        $this->problemCode = ProblemCode::FulfillmentPolicyImmutable;
        parent::__construct($this->problemCode->value);
    }
}
