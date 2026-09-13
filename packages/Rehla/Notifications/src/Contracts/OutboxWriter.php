<?php

declare(strict_types=1);

namespace Rehla\Notifications\Contracts;

use Rehla\Notifications\Data\OutboxMessageData;

interface OutboxWriter
{
    public function append(OutboxMessageData $data): string;
}
