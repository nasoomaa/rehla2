<?php

declare(strict_types=1);

namespace Rehla\Notifications\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Core\Identifiers\OpaqueId;

final readonly class MarkFailed
{
    private const array RETRY_DELAYS = [30, 60, 120, 240];

    public function __construct(private AuditWriter $audit) {}

    public function handle(
        string $messageId,
        string $workerId,
        string $lockToken,
        string $safeError,
        string $traceId,
    ): bool {
        OpaqueId::fromString($messageId);
        OpaqueId::fromString($lockToken);
        OpaqueId::fromString($traceId);
        if (trim($workerId) === '' || strlen($workerId) > 100) {
            throw new InvalidArgumentException;
        }
        $error = $this->sanitize($safeError);

        return DB::transaction(function () use ($messageId, $workerId, $lockToken, $traceId, $error): bool {
            $row = DB::table('outbox_messages')
                ->where('id', $messageId)
                ->where('status', 'locked')
                ->where('locked_by', $workerId)
                ->where('lock_token', $lockToken)
                ->where('lease_expires_at', '>', DB::raw('CURRENT_TIMESTAMP'))
                ->lockForUpdate()
                ->first();
            if ($row === null) {
                return false;
            }

            $attempts = (int) $row->attempts;
            $deadLetter = $attempts >= 5;
            $values = [
                'status' => $deadLetter ? 'dead_letter' : 'available',
                'available_at' => $deadLetter
                    ? $row->available_at
                    : DB::raw("CURRENT_TIMESTAMP + INTERVAL '".self::RETRY_DELAYS[$attempts - 1]." seconds'"),
                'locked_at' => null,
                'locked_by' => null,
                'lock_token' => null,
                'lease_expires_at' => null,
                'dead_lettered_at' => $deadLetter ? DB::raw('CURRENT_TIMESTAMP') : null,
                'last_error' => $error,
                'last_trace_id' => $traceId,
            ];
            $updated = DB::table('outbox_messages')
                ->where('id', $messageId)
                ->where('status', 'locked')
                ->where('locked_by', $workerId)
                ->where('lock_token', $lockToken)
                ->where('lease_expires_at', '>', DB::raw('CURRENT_TIMESTAMP'))
                ->update($values);
            if ($updated !== 1) {
                return false;
            }

            if ($deadLetter) {
                $this->audit->append(new AppendAuditData(
                    actorType: 'system',
                    actorId: null,
                    action: 'notifications.outbox_dead_lettered',
                    subjectType: 'outbox_message',
                    subjectId: $messageId,
                    metadata: ['attempts' => $attempts, 'error' => $error],
                    oldState: ['status' => 'locked'],
                    newState: ['status' => 'dead_letter'],
                    reason: null,
                    correlationId: $traceId,
                ));
                Log::critical('notifications.outbox_dead_lettered', [
                    'message_id' => $messageId,
                    'trace_id' => $traceId,
                ]);
            }

            return true;
        });
    }

    private function sanitize(string $error): string
    {
        $error = preg_replace('/[\x00-\x1F\x7F<>]+/u', ' ', $error);
        if (! is_string($error)) {
            return 'delivery_failure';
        }
        $error = preg_replace('/\s+/u', ' ', trim($error));

        return mb_substr(is_string($error) && $error !== '' ? $error : 'delivery_failure', 0, 255);
    }
}
