<?php

declare(strict_types=1);

namespace Rehla\Forms\Contracts;

use Rehla\Forms\Data\PublishedFormData;

interface PublishedFormReader
{
    public function forService(string $serviceId): PublishedFormData;
}
