<?php

declare(strict_types=1);

namespace Rehla\Notifications\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;

final class MarkDelivered
{
    public function handle(string $messageId, string $workerId, string $lockToken): bool
    {
        OpaqueId::fromString($messageId);
        OpaqueId::fromString($lockToken);
        if (trim($workerId) === '' || strlen($workerId) > 100) {
            throw new InvalidArgumentException;
        }

        return DB::table('outbox_messages')
            ->where('id', $messageId)
            ->where('status', 'locked')
            ->where('locked_by', $workerId)
            ->where('lock_token', $lockToken)
            ->where('lease_expires_at', '>', DB::raw('CURRENT_TIMESTAMP'))
            ->update([
                'status' => 'delivered',
                'locked_at' => null,
                'locked_by' => null,
                'lock_token' => null,
                'lease_expires_at' => null,
                'delivered_at' => DB::raw('CURRENT_TIMESTAMP'),
                'last_error' => null,
                'last_trace_id' => null,
            ]) === 1;
    }
}
