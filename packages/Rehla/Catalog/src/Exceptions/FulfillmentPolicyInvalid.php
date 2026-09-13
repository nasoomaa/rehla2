<?php

declare(strict_types=1);

namespace Rehla\Catalog\Exceptions;

use InvalidArgumentException;

final class FulfillmentPolicyInvalid extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('fulfillment.policy_invalid');
    }
}
