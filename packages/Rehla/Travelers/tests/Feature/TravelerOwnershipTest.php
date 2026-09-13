<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rehla\Core\Errors\ProblemCode;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Travelers\Actions\CreateTraveler;
use Rehla\Travelers\Actions\UpdateTraveler;
use Rehla\Travelers\Contracts\TravelerReader;
use Rehla\Travelers\Contracts\TravelerSnapshotReader;
use Rehla\Travelers\Data\TravelerData;
use Rehla\Travelers\Enums\Gender;
use Rehla\Travelers\Exceptions\InvalidTraveler;
use Rehla\Travelers\Exceptions\TravelerNotFound;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE travelers, users, audit_entries RESTART IDENTITY CASCADE');
});

function createTravelerOwner(string $email): string
{
    $id = OpaqueId::generate()->value();
    DB::table('users')->insert([
        'id' => $id,
        'name' => 'Traveler Owner',
        'email' => $email,
        'password' => 'not-used',
        'status' => 'active',
        'preferred_locale' => 'en',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function validTravelerData(string $ownerId, string $passport = ' p 012-345 67 '): TravelerData
{
    return new TravelerData(
        ownerId: $ownerId,
        fullName: 'Ahmed Mohammed Osman',
        dateOfBirth: CarbonImmutable::parse('1990-08-20'),
        gender: Gender::Male,
        passportNumber: $passport,
        passportIssuedAt: CarbonImmutable::parse('2022-03-01'),
        passportExpiresAt: CarbonImmutable::parse('2027-02-28'),
    );
}

it('stores all required traveler fields and returns an immutable normalized snapshot', function (): void {
    $ownerId = createTravelerOwner('traveler-owner@example.test');
    $snapshot = app(CreateTraveler::class)->handle(validTravelerData($ownerId));
    $stored = DB::table('travelers')->where('id', $snapshot->id)->first();
    if ($stored === null) {
        throw new RuntimeException('Traveler row was not stored.');
    }

    expect($snapshot->fullName)->toBe('Ahmed Mohammed Osman')
        ->and($snapshot->dateOfBirth->toDateString())->toBe('1990-08-20')
        ->and($snapshot->gender)->toBe(Gender::Male)
        ->and($snapshot->passportNumber)->toBe('P01234567')
        ->and($snapshot->passportIssuedAt->toDateString())->toBe('2022-03-01')
        ->and($snapshot->passportExpiresAt->toDateString())->toBe('2027-02-28')
        ->and($stored->normalized_passport_number)->toBe('P01234567')
        ->and(Schema::hasColumn('travelers', 'nationality'))->toBeFalse()
        ->and(Schema::hasColumn('travelers', 'passport_country'))->toBeFalse()
        ->and(DB::table('audit_entries')->where('subject_id', $snapshot->id)->value('action'))->toBe('traveler.created');
});

it('lists and reads only travelers owned by the account', function (): void {
    $ownerA = createTravelerOwner('traveler-a@example.test');
    $ownerB = createTravelerOwner('traveler-b@example.test');
    $travelerA = app(CreateTraveler::class)->handle(validTravelerData($ownerA, 'P123456'));
    app(CreateTraveler::class)->handle(validTravelerData($ownerB, 'P654321'));

    $owned = app(TravelerReader::class)->listOwned($ownerA, 1, 20);

    expect($owned)->toHaveCount(1)
        ->and($owned[0]->id)->toBe($travelerA->id)
        ->and(app(TravelerSnapshotReader::class)->getOwned($ownerA, $travelerA->id))->toEqual($travelerA);

    expect(fn () => app(TravelerSnapshotReader::class)->getOwned($ownerB, $travelerA->id))
        ->toThrow(TravelerNotFound::class);
});

it('does not reveal or update a traveler belonging to another account', function (): void {
    $ownerA = createTravelerOwner('update-owner-a@example.test');
    $ownerB = createTravelerOwner('update-owner-b@example.test');
    $traveler = app(CreateTraveler::class)->handle(validTravelerData($ownerA, 'P123456'));
    $auditCount = DB::table('audit_entries')->count();

    expect(fn () => app(UpdateTraveler::class)->handle(
        $traveler->id,
        validTravelerData($ownerB, 'P999999'),
    ))->toThrow(TravelerNotFound::class)
        ->and(DB::table('travelers')->where('id', $traveler->id)->value('normalized_passport_number'))->toBe('P123456')
        ->and(DB::table('audit_entries')->count())->toBe($auditCount);
});

it('updates future traveler data without mutating a previously returned snapshot', function (): void {
    $ownerId = createTravelerOwner('renewal-owner@example.test');
    $original = app(CreateTraveler::class)->handle(validTravelerData($ownerId, 'P123456'));
    $renewed = app(UpdateTraveler::class)->handle($original->id, validTravelerData($ownerId, 'P987654'));

    expect($original->passportNumber)->toBe('P123456')
        ->and($renewed->passportNumber)->toBe('P987654')
        ->and(DB::table('audit_entries')->where('subject_id', $original->id)->count())->toBe(2);
});

it('rejects invalid biographical and passport date ranges', function (
    CarbonImmutable $birth,
    CarbonImmutable $issued,
    CarbonImmutable $expires,
): void {
    $ownerId = createTravelerOwner(OpaqueId::generate()->value().'@example.test');

    try {
        new TravelerData(
            ownerId: $ownerId,
            fullName: 'Valid Name',
            dateOfBirth: $birth,
            gender: Gender::Female,
            passportNumber: 'P123456',
            passportIssuedAt: $issued,
            passportExpiresAt: $expires,
        );
        $exception = null;
    } catch (InvalidTraveler $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(InvalidTraveler::class)
        ->and($exception?->problemCode)->toBe(ProblemCode::TravelerInvalidDates);
})->with([
    'future birth' => [
        CarbonImmutable::now()->addDay(),
        CarbonImmutable::now()->addDays(2),
        CarbonImmutable::now()->addYear(),
    ],
    'older than 120 years' => [
        CarbonImmutable::now()->subYears(121),
        CarbonImmutable::now()->subYears(20),
        CarbonImmutable::now()->addYear(),
    ],
    'issue before birth' => [
        CarbonImmutable::parse('2000-01-01'),
        CarbonImmutable::parse('1999-01-01'),
        CarbonImmutable::now()->addYear(),
    ],
    'issue in future' => [
        CarbonImmutable::parse('2000-01-01'),
        CarbonImmutable::now()->addDay(),
        CarbonImmutable::now()->addYear(),
    ],
    'expiry before issue' => [
        CarbonImmutable::parse('2000-01-01'),
        CarbonImmutable::parse('2020-01-01'),
        CarbonImmutable::parse('2019-01-01'),
    ],
    'expiry not future' => [
        CarbonImmutable::parse('2000-01-01'),
        CarbonImmutable::parse('2020-01-01'),
        CarbonImmutable::now()->subDay(),
    ],
]);
