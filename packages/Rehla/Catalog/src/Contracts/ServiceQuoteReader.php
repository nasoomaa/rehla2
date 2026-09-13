<?php

declare(strict_types=1);

namespace Rehla\Catalog\Contracts;

use Rehla\Catalog\Data\ServiceQuote;

interface ServiceQuoteReader
{
    public function currentQuote(string $serviceId): ServiceQuote;
}
