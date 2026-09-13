<?php

declare(strict_types=1);

namespace Rehla\Notifications\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Notifications\Data\OutboxEnvelope;

final class ClaimOutboxBatch
{
    /** @return list<OutboxEnvelope> */
    public function handle(int $limit, string $workerId): array
    {
        if ($limit < 1 || $limit > 100 || trim($workerId) === '' || strlen($workerId) > 100) {
            throw new InvalidArgumentException;
        }
        $leaseSeconds = max(1, min(3600, (int) config('rehla-notifications.outbox_lease_seconds', 60)));

        return DB::transaction(function () use ($limit, $workerId, $leaseSeconds): array {
            $ids = DB::table('outbox_messages')
                ->where(function ($query): void {
                    $query->where(function ($available): void {
                        $available->where('status', 'available')
                            ->where('available_at', '<=', DB::raw('CURRENT_TIMESTAMP'));
                    })->orWhere(function ($expired): void {
                        $expired->where('status', 'locked')
                            ->where('lease_expires_at', '<=', DB::raw('CURRENT_TIMESTAMP'));
                    });
                })
                ->orderBy('id')
                ->limit($limit)
                ->lock(implode(' ', ['for', 'update', 'skip', 'locked']))
                ->pluck('id')
                ->all();

            $envelopes = [];
            foreach ($ids as $id) {
                $token = OpaqueId::generate()->value();
                DB::update(<<<'SQL'
                    UPDATE outbox_messages
                    SET status = 'locked',
                        locked_at = CURRENT_TIMESTAMP,
                        locked_by = ?,
                        lock_token = ?,
                        lease_expires_at = CURRENT_TIMESTAMP + (? * INTERVAL '1 second'),
                        attempts = attempts + 1
                    WHERE id = ?
                    SQL, [$workerId, $token, $leaseSeconds, $id]);
                $row = DB::table('outbox_messages')->where('id', $id)->first();
                if ($row === null) {
                    throw new InvalidArgumentException;
                }
                $payload = json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new InvalidArgumentException;
                }
                $envelopes[] = new OutboxEnvelope(
                    (string) $row->id,
                    (string) $row->event_name,
                    (string) $row->aggregate_type,
                    (string) $row->aggregate_id,
                    (int) $row->payload_version,
                    $payload,
                    (string) $row->deduplication_key,
                    $workerId,
                    $token,
                    CarbonImmutable::parse((string) $row->lease_expires_at),
                    (int) $row->attempts,
                );
            }

            return $envelopes;
        });
    }
}
