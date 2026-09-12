<?php

declare(strict_types=1);

namespace Rehla\Core\Time;

use Carbon\CarbonImmutable;

interface Clock
{
    public function now(): CarbonImmutable;
}
