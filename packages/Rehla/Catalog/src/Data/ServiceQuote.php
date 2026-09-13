<?php

declare(strict_types=1);

namespace Rehla\Catalog\Data;

final readonly class ServiceQuote
{
    public function __construct(
        public string $serviceId,
        public int $priceMinor,
        public string $currency,
        public int $quoteVersion,
        public bool $available,
    ) {}
}
