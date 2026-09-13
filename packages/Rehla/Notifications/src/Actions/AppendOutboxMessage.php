<?php

declare(strict_types=1);

namespace Rehla\Notifications\Actions;

use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Notifications\Contracts\OutboxWriter;
use Rehla\Notifications\Data\OutboxMessageData;

final readonly class AppendOutboxMessage implements OutboxWriter
{
    public function __construct(private Clock $clock) {}

    public function append(OutboxMessageData $data): string
    {
        $id = OpaqueId::generate()->value();
        DB::table('outbox_messages')->insert([
            'id' => $id,
            'event_name' => $data->eventName,
            'aggregate_type' => $data->aggregateType,
            'aggregate_id' => $data->aggregateId,
            'payload_version' => $data->payloadVersion,
            'payload' => json_encode($data->payload, JSON_THROW_ON_ERROR),
            'deduplication_key' => $data->deduplicationKey,
            'status' => 'available',
            'available_at' => $data->availableAt,
            'attempts' => 0,
            'created_at' => $this->clock->now(),
        ]);

        return $id;
    }
}
