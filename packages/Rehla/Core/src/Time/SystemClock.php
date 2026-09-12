<?php

declare(strict_types=1);

namespace Rehla\Core\Time;

use Carbon\CarbonImmutable;

final class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}
