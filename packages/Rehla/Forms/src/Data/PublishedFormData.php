<?php

declare(strict_types=1);

namespace Rehla\Forms\Data;

use Carbon\CarbonImmutable;

final readonly class PublishedFormData
{
    /** @param list<FormFieldData> $fields */
    public function __construct(
        public string $id,
        public string $serviceId,
        public int $version,
        public array $fields,
        public string $checksum,
        public CarbonImmutable $publishedAt,
    ) {}
}
