<?php

declare(strict_types=1);

namespace Rehla\Notifications\Data;

final readonly class NotificationPage
{
    /** @param list<NotificationSnapshot> $items */
    public function __construct(
        public array $items,
        public int $unreadCount,
        public int $page,
        public int $perPage,
    ) {}
}
