<?php

declare(strict_types=1);

namespace Rehla\Notifications\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Notifications\Contracts\NotificationReader;
use Rehla\Notifications\Data\NotificationPage;
use Rehla\Notifications\Data\NotificationSnapshot;

final class ListOwnedNotifications implements NotificationReader
{
    public function listOwned(
        string $accountId,
        int $page,
        int $perPage,
        bool $unreadOnly = false,
    ): NotificationPage {
        OpaqueId::fromString($accountId);
        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid notification pagination.');
        }

        $query = DB::table('notifications')->where('user_id', $accountId);
        $unreadCount = (clone $query)->whereNull('read_at')->count();
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }
        $items = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (object $row): NotificationSnapshot => $this->snapshot(get_object_vars($row)))
            ->all();

        return new NotificationPage(array_values($items), $unreadCount, $page, $perPage);
    }

    /** @param array<string, mixed> $row */
    private function snapshot(array $row): NotificationSnapshot
    {
        $payload = json_decode((string) ($row['payload'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload)
            || ! isset($payload['title']['en'], $payload['title']['ar'], $payload['body']['en'], $payload['body']['ar'])
            || ! is_string($payload['target_link'] ?? null)) {
            throw new InvalidArgumentException('Invalid stored notification payload.');
        }

        return new NotificationSnapshot(
            (string) ($row['id'] ?? ''),
            (string) ($row['type'] ?? ''),
            ['en' => (string) $payload['title']['en'], 'ar' => (string) $payload['title']['ar']],
            ['en' => (string) $payload['body']['en'], 'ar' => (string) $payload['body']['ar']],
            $payload['target_link'],
            ($row['read_at'] ?? null) === null ? null : CarbonImmutable::parse((string) $row['read_at']),
            CarbonImmutable::parse((string) ($row['created_at'] ?? '')),
        );
    }
}
