<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Core\Identifiers\OpaqueId;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::table('audit_entries')->truncate();
});

/** @param array<array-key, mixed> $metadata */
function appendAuditFixture(array $metadata = []): string
{
    return app(AuditWriter::class)->append(new AppendAuditData(
        actorType: 'staff',
        actorId: OpaqueId::generate()->value(),
        action: 'top_up.approved',
        subjectType: 'top_up',
        subjectId: OpaqueId::generate()->value(),
        metadata: $metadata,
        oldState: ['status' => 'pending'],
        newState: ['status' => 'approved'],
        reason: 'Receipt verified',
        correlationId: OpaqueId::generate()->value(),
    ));
}

it('appends an audit entry with a stable correlation id and UTC timestamp', function (): void {
    $id = appendAuditFixture(['amount_minor' => 500_000]);
    $entry = DB::table('audit_entries')->where('id', $id)->first();
    if ($entry === null) {
        throw new RuntimeException('The appended audit entry was not found.');
    }

    expect($entry->action)->toBe('top_up.approved')
        ->and(json_decode($entry->metadata, true, flags: JSON_THROW_ON_ERROR))->toBe(['amount_minor' => 500_000])
        ->and($entry->correlation_id)->toBeUuid()
        ->and($entry->occurred_at)->not->toBeNull();
});

it('removes secrets recursively before persisting audit context', function (): void {
    $id = appendAuditFixture([
        'email' => 'customer@example.test',
        'password' => 'never-store-me',
        'nested' => ['access_token' => 'secret-token', 'safe' => 'value', ['token' => 'hidden', 'id' => 7]],
    ]);
    $metadata = json_decode(
        DB::table('audit_entries')->where('id', $id)->value('metadata'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($metadata['email'])->toBe('customer@example.test')
        ->and($metadata)->not->toHaveKey('password')
        ->and($metadata['nested']['safe'])->toBe('value')
        ->and($metadata['nested'][0])->toBe(['id' => 7])
        ->and($metadata['nested'])->not->toHaveKey('access_token');
});

it('participates in the transaction owned by its caller', function (): void {
    try {
        DB::transaction(function (): never {
            appendAuditFixture();

            throw new RuntimeException('abort owner operation');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('abort owner operation');
    }

    expect(DB::table('audit_entries')->count())->toBe(0);
});

it('rejects direct updates at the PostgreSQL boundary', function (): void {
    $id = appendAuditFixture();

    expect(fn (): int => DB::table('audit_entries')->where('id', $id)->update(['action' => 'changed']))
        ->toThrow(QueryException::class, 'append-only');
});

it('rejects direct deletes at the PostgreSQL boundary', function (): void {
    $id = appendAuditFixture();

    expect(fn (): int => DB::table('audit_entries')->where('id', $id)->delete())
        ->toThrow(QueryException::class, 'append-only');
});
