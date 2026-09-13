<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Rehla\Core\Errors\ProblemCode;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Travelers\Actions\CreateTraveler;
use Rehla\Travelers\Actions\UpdateTraveler;
use Rehla\Travelers\Data\TravelerData;
use Rehla\Travelers\Enums\Gender;
use Rehla\Travelers\Exceptions\DuplicatePassport;
use Rehla\Travelers\Support\NormalizePassportNumber;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE travelers, users, audit_entries RESTART IDENTITY CASCADE');
});

function createPassportOwner(string $email): string
{
    $id = OpaqueId::generate()->value();
    DB::table('users')->insert([
        'id' => $id,
        'name' => 'Passport Owner',
        'email' => $email,
        'password' => 'not-used',
        'status' => 'active',
        'preferred_locale' => 'en',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function passportTravelerData(string $ownerId, string $passport): TravelerData
{
    return new TravelerData(
        $ownerId,
        'Passport Traveler',
        CarbonImmutable::parse('1990-08-20'),
        Gender::Male,
        $passport,
        CarbonImmutable::parse('2022-03-01'),
        CarbonImmutable::parse('2027-02-28'),
    );
}

it('normalizes the complete canonical passport punctuation set', function (): void {
    $normalizer = app(NormalizePassportNumber::class);

    expect($normalizer->normalize(" p\u{00A0}01_2/3.45-67 "))->toBe('P01234567');
});

it('maps global passport conflicts to the stable domain problem', function (): void {
    $ownerA = createPassportOwner('passport-owner-a@example.test');
    $ownerB = createPassportOwner('passport-owner-b@example.test');
    app(CreateTraveler::class)->handle(passportTravelerData($ownerA, ' p-12 34 56 '));

    try {
        app(CreateTraveler::class)->handle(passportTravelerData($ownerB, 'P123456'));
        $exception = null;
    } catch (DuplicatePassport $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(DuplicatePassport::class)
        ->and($exception?->problemCode)->toBe(ProblemCode::TravelerPassportConflict)
        ->and(DB::table('travelers')->count())->toBe(1);
});

it('protects uniqueness when updating to another travelers passport', function (): void {
    $ownerId = createPassportOwner('passport-update@example.test');
    $first = app(CreateTraveler::class)->handle(passportTravelerData($ownerId, 'P123456'));
    $second = app(CreateTraveler::class)->handle(passportTravelerData($ownerId, 'P654321'));

    expect(fn () => app(UpdateTraveler::class)->handle($second->id, passportTravelerData($ownerId, 'p-12 34 56')))
        ->toThrow(DuplicatePassport::class)
        ->and(DB::table('travelers')->where('id', $first->id)->value('normalized_passport_number'))->toBe('P123456')
        ->and(DB::table('travelers')->where('id', $second->id)->value('normalized_passport_number'))->toBe('P654321');
});

it('converts a concurrent PostgreSQL unique violation into one domain conflict', function (): void {
    $ownerA = createPassportOwner('race-passport-a@example.test');
    $ownerB = createPassportOwner('race-passport-b@example.test');
    $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($sockets === false) {
        throw new RuntimeException('Could not create process synchronization sockets.');
    }

    $pid = pcntl_fork();
    if ($pid === -1) {
        throw new RuntimeException('Could not fork uniqueness test process.');
    }

    if ($pid === 0) {
        fclose($sockets[0]);
        DB::purge();
        DB::reconnect();
        fwrite($sockets[1], 'r');
        fread($sockets[1], 1);
        try {
            app(CreateTraveler::class)->handle(passportTravelerData($ownerB, 'P123456'));
            exit(0);
        } catch (DuplicatePassport) {
            exit(42);
        } catch (Throwable) {
            exit(99);
        }
    }

    fclose($sockets[1]);
    fread($sockets[0], 1);
    DB::beginTransaction();
    DB::table('travelers')->insert([
        'id' => OpaqueId::generate()->value(),
        'owner_id' => $ownerA,
        'full_name' => 'Concurrent Winner',
        'date_of_birth' => '1990-08-20',
        'gender' => 'male',
        'passport_number' => ' p-12 34 56 ',
        'normalized_passport_number' => 'P123456',
        'passport_issued_at' => '2022-03-01',
        'passport_expires_at' => '2027-02-28',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    fwrite($sockets[0], 'g');
    usleep(200_000);
    DB::commit();

    pcntl_waitpid($pid, $status);
    fclose($sockets[0]);

    expect(pcntl_wifexited($status))->toBeTrue()
        ->and(pcntl_wexitstatus($status))->toBe(42)
        ->and(DB::table('travelers')->count())->toBe(1);
});
