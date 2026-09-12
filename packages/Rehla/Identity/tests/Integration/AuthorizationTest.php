<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Identity\Actions\AssignRole;
use Rehla\Identity\Actions\RevokeRole;
use Rehla\Identity\Contracts\AuthorizesActor;
use Rehla\Identity\Data\ActorData;
use Rehla\Identity\Enums\AbilityName;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Auth::forgetGuards();
    DB::statement('TRUNCATE TABLE users, roles, audit_entries RESTART IDENTITY CASCADE');
});

function createIdentityUser(string $email, bool $staff = false, ?string $mfaConfirmedAt = null): string
{
    $id = OpaqueId::generate()->value();
    DB::table('users')->insert([
        'id' => $id,
        'name' => 'Test User',
        'email' => $email,
        'password' => Hash::make('Secret-12345'),
        'status' => 'active',
        'preferred_locale' => 'en',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if ($staff) {
        DB::table('staff_profiles')->insert([
            'user_id' => $id,
            'department' => 'operations',
            'is_active' => true,
            'mfa_confirmed_at' => $mfaConfirmedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $id;
}

function grantIdentityAbility(string $userId, AbilityName $ability, string $roleName = 'test-role'): void
{
    $roleId = OpaqueId::generate()->value();
    DB::table('roles')->insert(['id' => $roleId, 'name' => $roleName, 'created_at' => now(), 'updated_at' => now()]);
    $abilityId = DB::table('abilities')->where('name', $ability->value)->value('id');
    DB::table('role_ability')->insert(['role_id' => $roleId, 'ability_id' => $abilityId]);
    DB::table('user_role')->insert(['user_id' => $userId, 'role_id' => $roleId]);
}

it('contains exactly the canonical thirty staff abilities', function (): void {
    $expected = [
        'admin.overview.view', 'services.view', 'services.manage', 'forms.view', 'forms.draft', 'forms.publish',
        'customers.view', 'customers.view_sensitive', 'customers.manage_status', 'travelers.view',
        'travelers.view_sensitive', 'wallets.view', 'banks.view', 'banks.manage', 'topups.view', 'topups.review',
        'topups.settings.manage', 'orders.view', 'executions.view', 'executions.view_sensitive',
        'executions.transition', 'executions.note', 'documents.view_sensitive', 'content.view', 'content.manage',
        'notifications.view', 'notifications.replay', 'access.view', 'access.manage', 'audit.view',
    ];

    expect(array_column(AbilityName::cases(), 'value'))->toBe($expected)
        ->and(DB::table('abilities')->orderBy('position')->pluck('name')->all())->toBe($expected)
        ->and(DB::table('abilities')->where('name', 'top_up.review')->doesntExist())->toBeTrue();
});

it('uses citext email and the Sanctum compatible token schema without an admin flag', function (): void {
    $emailType = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', 'users')
        ->where('column_name', 'email')
        ->value('udt_name');

    expect($emailType)->toBe('citext')
        ->and(Schema::hasColumn('users', 'is_admin'))->toBeFalse()
        ->and(Schema::hasColumns('personal_access_tokens', [
            'id', 'tokenable_type', 'tokenable_id', 'name', 'token', 'abilities',
            'last_used_at', 'expires_at', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

it('denies customers and staff without an explicit ability', function (): void {
    $customerId = createIdentityUser('customer@example.test');
    $staffId = createIdentityUser('staff@example.test', staff: true, mfaConfirmedAt: now()->toISOString());
    $authorization = app(AuthorizesActor::class);

    expect($authorization->allows(new ActorData($customerId, 'customer'), AbilityName::ServicesView))->toBeFalse()
        ->and($authorization->allows(new ActorData($staffId, 'staff'), AbilityName::ServicesView))->toBeFalse();
});

it('authorizes only each canonical granted ability', function (string $abilityValue): void {
    $ability = AbilityName::from($abilityValue);
    $otherAbility = $ability === AbilityName::AdminOverviewView
        ? AbilityName::ServicesView
        : AbilityName::AdminOverviewView;
    $mfaConfirmedAt = CarbonImmutable::parse(now()->subHour()->toISOString());
    $staffId = createIdentityUser(
        'grant-'.$ability->name.'@example.test',
        staff: true,
        mfaConfirmedAt: $mfaConfirmedAt->toISOString(),
    );
    grantIdentityAbility($staffId, $ability, 'grant-'.$ability->name);
    $actor = new ActorData($staffId, 'staff', $mfaConfirmedAt);
    $authorization = app(AuthorizesActor::class);

    expect($authorization->allows($actor, $ability))->toBeTrue()
        ->and($authorization->allows($actor, $otherAbility))->toBeFalse();
})->with(array_column(AbilityName::cases(), 'value'));

it('requires MFA no older than four hours for every high risk ability', function (string $abilityValue): void {
    $ability = AbilityName::from($abilityValue);
    $freshId = createIdentityUser('fresh-'.$ability->name.'@example.test', staff: true, mfaConfirmedAt: now()->subHours(3)->toISOString());
    $staleId = createIdentityUser('stale-'.$ability->name.'@example.test', staff: true, mfaConfirmedAt: now()->subHours(4)->subSecond()->toISOString());
    grantIdentityAbility($freshId, $ability, 'fresh-'.$ability->name);
    grantIdentityAbility($staleId, $ability, 'stale-'.$ability->name);
    $authorization = app(AuthorizesActor::class);

    expect($authorization->allows(new ActorData(
        $freshId,
        'staff',
        CarbonImmutable::parse(now()->subHours(3)->toISOString()),
    ), $ability))->toBeTrue()
        ->and($authorization->allows(new ActorData(
            $staleId,
            'staff',
            CarbonImmutable::parse(now()->subHours(4)->subSecond()->toISOString()),
        ), $ability))->toBeFalse()
        ->and($authorization->allows(new ActorData($freshId, 'staff'), $ability))->toBeFalse();
})->with([
    'topups.review',
    'topups.settings.manage',
    'access.manage',
    'audit.view',
]);

it('allows a granted ordinary ability without recent MFA', function (): void {
    $staffId = createIdentityUser('catalog-staff@example.test', staff: true);
    grantIdentityAbility($staffId, AbilityName::ServicesView);

    expect(app(AuthorizesActor::class)->allows(new ActorData($staffId, 'staff'), AbilityName::ServicesView))->toBeTrue();
});

it('uses a staff-only provider for the admin guard', function (): void {
    createIdentityUser('customer-login@example.test');
    createIdentityUser('staff-login@example.test', staff: true);

    expect(Auth::guard('admin')->attempt(['email' => 'customer-login@example.test', 'password' => 'Secret-12345']))->toBeFalse()
        ->and(Auth::guard('admin')->attempt(['email' => 'staff-login@example.test', 'password' => 'Secret-12345']))->toBeTrue()
        ->and(Auth::guard('web')->attempt(['email' => 'staff-login@example.test', 'password' => 'Secret-12345']))->toBeFalse()
        ->and(Auth::guard('web')->attempt(['email' => 'customer-login@example.test', 'password' => 'Secret-12345']))->toBeTrue();
});

it('rejects suspended accounts from both credential providers', function (): void {
    $customerId = createIdentityUser('suspended-customer@example.test');
    $staffId = createIdentityUser('suspended-staff@example.test', staff: true);
    DB::table('users')->whereIn('id', [$customerId, $staffId])->update(['status' => 'suspended']);
    Auth::forgetGuards();

    expect(Auth::guard('web')->attempt(['email' => 'suspended-customer@example.test', 'password' => 'Secret-12345']))->toBeFalse()
        ->and(Auth::guard('admin')->attempt(['email' => 'suspended-staff@example.test', 'password' => 'Secret-12345']))->toBeFalse();
});

it('requires access manage with fresh MFA to assign and revoke a role', function (): void {
    $actorId = createIdentityUser('access-admin@example.test', staff: true, mfaConfirmedAt: now()->subHour()->toISOString());
    $targetId = createIdentityUser('target@example.test');
    grantIdentityAbility($actorId, AbilityName::AccessManage, 'access-administrator');
    $targetRoleId = OpaqueId::generate()->value();
    DB::table('roles')->insert(['id' => $targetRoleId, 'name' => 'reviewer', 'created_at' => now(), 'updated_at' => now()]);
    $actor = new ActorData($actorId, 'staff', CarbonImmutable::parse(now()->subHour()->toISOString()));
    $correlationId = OpaqueId::generate()->value();

    app(AssignRole::class)->handle($actor, $targetId, 'reviewer', $correlationId);
    expect(DB::table('user_role')->where(['user_id' => $targetId, 'role_id' => $targetRoleId])->exists())->toBeTrue();

    app(RevokeRole::class)->handle($actor, $targetId, 'reviewer', OpaqueId::generate()->value());
    expect(DB::table('user_role')->where(['user_id' => $targetId, 'role_id' => $targetRoleId])->exists())->toBeFalse()
        ->and(DB::table('audit_entries')->where('subject_id', $targetId)->count())->toBe(2);
});

it('does not mutate roles when the staff actor lacks access manage', function (): void {
    $actorId = createIdentityUser('limited-staff@example.test', staff: true, mfaConfirmedAt: now()->toISOString());
    $targetId = createIdentityUser('protected-target@example.test');
    $roleId = OpaqueId::generate()->value();
    DB::table('roles')->insert(['id' => $roleId, 'name' => 'protected-role', 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => app(AssignRole::class)->handle(
        new ActorData($actorId, 'staff', CarbonImmutable::parse(now()->toISOString())),
        $targetId,
        'protected-role',
        OpaqueId::generate()->value(),
    ))->toThrow(AuthorizationException::class)
        ->and(DB::table('user_role')->where('user_id', $targetId)->doesntExist())->toBeTrue()
        ->and(DB::table('audit_entries')->where('subject_id', $targetId)->doesntExist())->toBeTrue();
});
