<?php

declare(strict_types=1);

namespace Rehla\Catalog\Contracts;

use Rehla\Catalog\Data\FulfillmentPolicyData;

interface PublishedFulfillmentPolicyReader
{
    public function forService(string $serviceId): FulfillmentPolicyData;
}
