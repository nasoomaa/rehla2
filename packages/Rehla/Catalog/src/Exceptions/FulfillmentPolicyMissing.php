<?php

declare(strict_types=1);

namespace Rehla\Catalog\Exceptions;

use Rehla\Core\Errors\ProblemCode;
use RuntimeException;

final class FulfillmentPolicyMissing extends RuntimeException
{
    public readonly ProblemCode $problemCode;

    public function __construct()
    {
        $this->problemCode = ProblemCode::ServiceFulfillmentPolicyMissing;
        parent::__construct($this->problemCode->value);
    }
}
