<?php

declare(strict_types=1);

namespace Rehla\Notifications\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Notifications\Data\NotificationSnapshot;
use Rehla\Notifications\Exceptions\NotificationNotFound;

final readonly class MarkNotificationRead
{
    public function __construct(private Clock $clock) {}

    public function handle(string $accountId, string $notificationId): NotificationSnapshot
    {
        OpaqueId::fromString($accountId);
        OpaqueId::fromString($notificationId);

        return DB::transaction(function () use ($accountId, $notificationId): NotificationSnapshot {
            $row = DB::table('notifications')
                ->where('id', $notificationId)
                ->where('user_id', $accountId)
                ->lockForUpdate()
                ->first();
            if ($row === null) {
                throw new NotificationNotFound;
            }

            $readAt = $row->read_at === null
                ? $this->clock->now()->setMicrosecond(0)
                : CarbonImmutable::parse((string) $row->read_at);
            if ($row->read_at === null) {
                DB::table('notifications')->where('id', $notificationId)->update(['read_at' => $readAt]);
            }

            $payload = json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)
                || ! isset($payload['title']['en'], $payload['title']['ar'], $payload['body']['en'], $payload['body']['ar'])
                || ! is_string($payload['target_link'] ?? null)) {
                throw new InvalidArgumentException;
            }

            return new NotificationSnapshot(
                (string) $row->id,
                (string) $row->type,
                ['en' => (string) $payload['title']['en'], 'ar' => (string) $payload['title']['ar']],
                ['en' => (string) $payload['body']['en'], 'ar' => (string) $payload['body']['ar']],
                $payload['target_link'],
                $readAt,
                CarbonImmutable::parse((string) $row->created_at),
            );
        });
    }
}
