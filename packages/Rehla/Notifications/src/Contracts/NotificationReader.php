<?php

declare(strict_types=1);

namespace Rehla\Notifications\Contracts;

use Rehla\Notifications\Data\NotificationPage;

interface NotificationReader
{
    public function listOwned(
        string $accountId,
        int $page,
        int $perPage,
        bool $unreadOnly = false,
    ): NotificationPage;
}
