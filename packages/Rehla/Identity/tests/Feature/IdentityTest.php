<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Rehla\Identity\Actions\RegisterCustomer;
use Rehla\Identity\Contracts\AuthorizesActor;
use Rehla\Identity\Contracts\IdentityReader;
use Rehla\Identity\Contracts\RegistrationNotificationRecorder;
use Rehla\Identity\Contracts\RegistrationWalletInitializer;
use Rehla\Identity\Data\RegisterCustomerData;
use Rehla\Identity\Enums\AbilityName;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE users, audit_entries RESTART IDENTITY CASCADE');
});

/** @param array<string, int|string> $observations */
function bindSuccessfulIdentityRegistrationPorts(array &$observations): void
{
    $wallet = Mockery::mock(RegistrationWalletInitializer::class);
    $wallet->shouldReceive('initialize')->once()->withArgs(function (string $accountId) use (&$observations): bool {
        $observations['wallet_transaction_level'] = DB::transactionLevel();
        $observations['wallet_account_id'] = $accountId;

        return true;
    });

    $notification = Mockery::mock(RegistrationNotificationRecorder::class);
    $notification->shouldReceive('recordWelcome')->once()->withArgs(function (string $accountId, string $locale, string $correlationId) use (&$observations): bool {
        $observations['notification_transaction_level'] = DB::transactionLevel();
        $observations['notification_account_id'] = $accountId;
        $observations['locale'] = $locale;
        $observations['correlation_id'] = $correlationId;

        return true;
    });

    app()->instance(RegistrationWalletInitializer::class, $wallet);
    app()->instance(RegistrationNotificationRecorder::class, $notification);
}

it('registers a normalized customer atomically without staff powers', function (): void {
    $observations = [];
    bindSuccessfulIdentityRegistrationPorts($observations);

    $user = app(RegisterCustomer::class)->handle(new RegisterCustomerData(
        name: 'Ahmed Ali',
        email: '  Ahmed@Example.Test ',
        password: 'Secret-12345',
        preferredLocale: 'ar',
    ));

    $stored = DB::table('users')->where('id', $user->id)->first();
    if ($stored === null) {
        throw new RuntimeException('Registered user was not persisted.');
    }

    expect($user->email)->toBe('ahmed@example.test')
        ->and($user->preferredLocale)->toBe('ar')
        ->and($stored)->not->toBeNull()
        ->and(Hash::check('Secret-12345', $stored->password))->toBeTrue()
        ->and($stored->password)->not->toBe('Secret-12345')
        ->and($observations['wallet_transaction_level'])->toBeGreaterThan(0)
        ->and($observations['notification_transaction_level'])->toBeGreaterThan(0)
        ->and($observations['wallet_account_id'])->toBe($user->id)
        ->and($observations['notification_account_id'])->toBe($user->id)
        ->and($observations['locale'])->toBe('ar')
        ->and($observations['correlation_id'])->toBeUuid()
        ->and(DB::table('audit_entries')->where('subject_id', $user->id)->count())->toBe(1)
        ->and(app(AuthorizesActor::class)->allows($user->actor, AbilityName::TopUpsReview))->toBeFalse();
});

it('rolls registration and audit back when a required collaborator fails', function (): void {
    $wallet = Mockery::mock(RegistrationWalletInitializer::class);
    $wallet->shouldReceive('initialize')->once();
    $notification = Mockery::mock(RegistrationNotificationRecorder::class);
    $notification->shouldReceive('recordWelcome')->once()->andThrow(new RuntimeException('notification storage unavailable'));
    app()->instance(RegistrationWalletInitializer::class, $wallet);
    app()->instance(RegistrationNotificationRecorder::class, $notification);

    expect(fn () => app(RegisterCustomer::class)->handle(new RegisterCustomerData(
        name: 'Rollback User',
        email: 'rollback@example.test',
        password: 'Secret-12345',
    )))->toThrow(RuntimeException::class, 'notification storage unavailable')
        ->and(DB::table('users')->count())->toBe(0)
        ->and(DB::table('audit_entries')->count())->toBe(0);
});

it('rejects a duplicate email regardless of case', function (): void {
    $observations = [];
    bindSuccessfulIdentityRegistrationPorts($observations);
    app(RegisterCustomer::class)->handle(new RegisterCustomerData(
        name: 'First Customer',
        email: 'same@example.test',
        password: 'Secret-12345',
    ));

    $wallet = Mockery::mock(RegistrationWalletInitializer::class);
    $wallet->shouldNotReceive('initialize');
    $notification = Mockery::mock(RegistrationNotificationRecorder::class);
    $notification->shouldNotReceive('recordWelcome');
    app()->instance(RegistrationWalletInitializer::class, $wallet);
    app()->instance(RegistrationNotificationRecorder::class, $notification);

    expect(fn () => app(RegisterCustomer::class)->handle(new RegisterCustomerData(
        name: 'Second Customer',
        email: 'SAME@EXAMPLE.TEST',
        password: 'Secret-12345',
    )))->toThrow(QueryException::class)
        ->and(DB::table('users')->count())->toBe(1);
});

it('returns immutable customer data through the identity reader', function (): void {
    $observations = [];
    bindSuccessfulIdentityRegistrationPorts($observations);
    $registered = app(RegisterCustomer::class)->handle(new RegisterCustomerData(
        name: 'Reader Customer',
        email: 'reader@example.test',
        password: 'Secret-12345',
    ));

    $found = app(IdentityReader::class)->findCustomer($registered->id);

    expect($found)->not->toBeNull()
        ->and($found?->id)->toBe($registered->id)
        ->and($found?->email)->toBe('reader@example.test')
        ->and($found)->not->toBeInstanceOf(Model::class);
});

it('keeps registration fail closed until both owner ports are bound', function (): void {
    expect(fn () => app(RegisterCustomer::class))->toThrow(BindingResolutionException::class)
        ->and(DB::table('users')->count())->toBe(0);
});
