<?php

declare(strict_types=1);

namespace Rehla\Notifications\Contracts;

use Rehla\Notifications\Data\NotificationData;
use Rehla\Notifications\Data\NotificationSnapshot;

interface NotificationRecorder
{
    public function record(NotificationData $data): NotificationSnapshot;
}
