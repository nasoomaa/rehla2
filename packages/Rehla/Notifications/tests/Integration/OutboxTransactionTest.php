<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Identity\Actions\RegisterCustomer;
use Rehla\Identity\Contracts\RegistrationWalletInitializer;
use Rehla\Identity\Data\RegisterCustomerData;
use Rehla\Notifications\Contracts\OutboxWriter;
use Rehla\Notifications\Data\OutboxMessageData;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE notifications, outbox_messages, users, audit_entries RESTART IDENTITY CASCADE');
});

function notificationOutboxData(string $deduplicationKey = 'topup_approved:401'): OutboxMessageData
{
    return new OutboxMessageData(
        eventName: 'topup.approved',
        aggregateType: 'top_up',
        aggregateId: OpaqueId::generate()->value(),
        payloadVersion: 1,
        payload: ['amount_minor' => 100_00, 'currency' => 'SDG'],
        deduplicationKey: $deduplicationKey,
        availableAt: CarbonImmutable::now('UTC'),
    );
}

it('rolls the outbox message back with its caller owned transaction', function (): void {
    try {
        DB::transaction(function (): void {
            app(OutboxWriter::class)->append(notificationOutboxData());
            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('outbox_messages')->count())->toBe(0);
});

it('commits a registration welcome notification and enabled external channel atomically', function (): void {
    config()->set('rehla-notifications.registration_channels', ['email']);
    $wallet = Mockery::mock(RegistrationWalletInitializer::class);
    $wallet->shouldReceive('initialize')->once();
    app()->instance(RegistrationWalletInitializer::class, $wallet);

    $customer = app(RegisterCustomer::class)->handle(new RegisterCustomerData(
        name: 'Welcome Customer',
        email: 'welcome@example.test',
        password: 'Secret-12345',
        preferredLocale: 'ar',
    ));

    $notification = DB::table('notifications')->where('user_id', $customer->id)->first();
    $outbox = DB::table('outbox_messages')->where('aggregate_id', $customer->id)->first();

    expect($notification)->not->toBeNull()
        ->and(json_decode((string) $notification?->payload, true, 512, JSON_THROW_ON_ERROR))->toMatchArray([
            'title' => ['en' => 'Welcome to Rehla', 'ar' => 'مرحبًا بك في رحلة'],
        ])
        ->and($outbox?->event_name)->toBe('identity.customer_registered')
        ->and($outbox?->status)->toBe('available')
        ->and(DB::table('users')->count())->toBe(1);
});

it('rolls registration audit notification and outbox back when welcome recording fails', function (): void {
    config()->set('rehla-notifications.registration_channels', ['email', 'email']);
    $wallet = Mockery::mock(RegistrationWalletInitializer::class);
    $wallet->shouldReceive('initialize')->once();
    app()->instance(RegistrationWalletInitializer::class, $wallet);

    expect(fn () => app(RegisterCustomer::class)->handle(new RegisterCustomerData(
        name: 'Rollback Customer',
        email: 'welcome-rollback@example.test',
        password: 'Secret-12345',
    )))->toThrow(QueryException::class)
        ->and(DB::table('users')->count())->toBe(0)
        ->and(DB::table('audit_entries')->count())->toBe(0)
        ->and(DB::table('notifications')->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0);
});

it('lets PostgreSQL reject duplicate deterministic outbox keys', function (): void {
    app(OutboxWriter::class)->append(notificationOutboxData());

    expect(fn () => app(OutboxWriter::class)->append(notificationOutboxData()))
        ->toThrow(QueryException::class)
        ->and(DB::table('outbox_messages')->count())->toBe(1);
});
