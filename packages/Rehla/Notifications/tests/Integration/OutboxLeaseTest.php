<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Notifications\Actions\ClaimOutboxBatch;
use Rehla\Notifications\Actions\MarkDelivered;
use Rehla\Notifications\Actions\MarkFailed;
use Rehla\Notifications\Contracts\OutboxWriter;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE notifications, outbox_messages, users, audit_entries RESTART IDENTITY CASCADE');
    config()->set('rehla-notifications.outbox_lease_seconds', 60);
});

function pendingOutboxMessage(string $key): string
{
    return app(OutboxWriter::class)->append(notificationOutboxData($key));
}

it('allows only one of two PostgreSQL workers to claim a message', function (): void {
    $messageId = pendingOutboxMessage('race:outbox:1');
    $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($sockets === false) {
        throw new RuntimeException('Could not create worker synchronization sockets.');
    }

    $pid = pcntl_fork();
    if ($pid === -1) {
        throw new RuntimeException('Could not fork outbox worker test.');
    }

    if ($pid === 0) {
        fclose($sockets[0]);
        DB::purge();
        DB::reconnect();
        fwrite($sockets[1], 'r');
        fread($sockets[1], 1);
        $claimed = app(ClaimOutboxBatch::class)->handle(1, 'child-worker');
        fwrite($sockets[1], json_encode(array_column($claimed, 'id'), JSON_THROW_ON_ERROR));
        fclose($sockets[1]);
        exit(0);
    }

    fclose($sockets[1]);
    fread($sockets[0], 1);
    fwrite($sockets[0], 'g');
    $parentClaimed = app(ClaimOutboxBatch::class)->handle(1, 'parent-worker');
    $childIds = json_decode(stream_get_contents($sockets[0]), true, 512, JSON_THROW_ON_ERROR);
    pcntl_waitpid($pid, $status);
    fclose($sockets[0]);

    $claimedIds = array_merge(array_column($parentClaimed, 'id'), $childIds);
    expect($claimedIds)->toBe([$messageId])
        ->and(pcntl_wexitstatus($status))->toBe(0);
});

it('reclaims an expired lease with a fresh token and fences the old worker', function (): void {
    $messageId = pendingOutboxMessage('lease:outbox:1');
    $old = app(ClaimOutboxBatch::class)->handle(1, 'old-worker')[0];
    DB::table('outbox_messages')->where('id', $messageId)->update([
        'lease_expires_at' => now()->subSecond(),
    ]);
    $replacement = app(ClaimOutboxBatch::class)->handle(1, 'replacement-worker')[0];

    expect($replacement->lockToken)->not->toBe($old->lockToken)
        ->and(app(MarkDelivered::class)->handle($messageId, 'old-worker', $old->lockToken))->toBeFalse()
        ->and(app(MarkFailed::class)->handle(
            $messageId,
            'old-worker',
            $old->lockToken,
            'stale failure',
            OpaqueId::generate()->value(),
        ))->toBeFalse()
        ->and(app(MarkDelivered::class)->handle($messageId, 'replacement-worker', $replacement->lockToken))->toBeTrue()
        ->and(DB::table('outbox_messages')->where('id', $messageId)->value('status'))->toBe('delivered');
});

it('backs off four failures and dead letters the fifth with safe diagnostics', function (): void {
    $messageId = pendingOutboxMessage('failure:outbox:1');
    $delays = [30, 60, 120, 240];

    foreach ($delays as $index => $delay) {
        $claim = app(ClaimOutboxBatch::class)->handle(1, 'failure-worker')[0];
        $before = now('UTC');
        expect(app(MarkFailed::class)->handle(
            $messageId,
            'failure-worker',
            $claim->lockToken,
            'provider unavailable',
            OpaqueId::generate()->value(),
        ))->toBeTrue();
        $availableAt = DB::table('outbox_messages')->where('id', $messageId)->value('available_at');
        expect(CarbonImmutable::parse((string) $availableAt)->betweenIncluded(
            $before->addSeconds($delay),
            now('UTC')->addSeconds($delay + 2),
        ))->toBeTrue();
        DB::table('outbox_messages')->where('id', $messageId)->update(['available_at' => now()->subSecond()]);
        expect(DB::table('outbox_messages')->where('id', $messageId)->value('attempts'))->toBe($index + 1);
    }

    $claim = app(ClaimOutboxBatch::class)->handle(1, 'failure-worker')[0];
    $traceId = OpaqueId::generate()->value();
    app(MarkFailed::class)->handle(
        $messageId,
        'failure-worker',
        $claim->lockToken,
        "provider\n<script>".str_repeat('x', 300),
        $traceId,
    );
    $row = DB::table('outbox_messages')->where('id', $messageId)->first();

    expect($row?->status)->toBe('dead_letter')
        ->and($row?->dead_lettered_at)->not->toBeNull()
        ->and((string) $row?->last_error)->not->toContain("\n", '<', '>')
        ->and(strlen((string) $row?->last_error))->toBeLessThanOrEqual(255)
        ->and($row?->last_trace_id)->toBe($traceId)
        ->and(DB::table('audit_entries')->where('subject_id', $messageId)->value('action'))->toBe('notifications.outbox_dead_lettered');
});
