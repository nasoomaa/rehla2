<?php

declare(strict_types=1);

namespace Rehla\Notifications\Data;

use Carbon\CarbonImmutable;

final readonly class NotificationSnapshot
{
    /**
     * @param  array{en: string, ar: string}  $title
     * @param  array{en: string, ar: string}  $body
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $title,
        public array $body,
        public string $targetLink,
        public ?CarbonImmutable $readAt,
        public CarbonImmutable $createdAt,
    ) {}
}
