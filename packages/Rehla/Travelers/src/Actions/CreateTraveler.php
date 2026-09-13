<?php

declare(strict_types=1);

namespace Rehla\Travelers\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Travelers\Data\TravelerData;
use Rehla\Travelers\Data\TravelerSnapshot;
use Rehla\Travelers\Exceptions\DuplicatePassport;
use Rehla\Travelers\Support\NormalizePassportNumber;

final readonly class CreateTraveler
{
    public function __construct(
        private NormalizePassportNumber $passports,
        private AuditWriter $audit,
        private Clock $clock,
    ) {}

    public function handle(TravelerData $data): TravelerSnapshot
    {
        try {
            return DB::transaction(function () use ($data): TravelerSnapshot {
                $id = OpaqueId::generate()->value();
                $correlationId = OpaqueId::generate()->value();
                $normalizedPassport = $this->passports->normalize($data->passportNumber);
                $now = $this->clock->now();

                DB::table('travelers')->insert([
                    'id' => $id,
                    'owner_id' => $data->ownerId,
                    'full_name' => trim($data->fullName),
                    'date_of_birth' => $data->dateOfBirth->toDateString(),
                    'gender' => $data->gender->value,
                    'passport_number' => trim($data->passportNumber),
                    'normalized_passport_number' => $normalizedPassport,
                    'passport_issued_at' => $data->passportIssuedAt->toDateString(),
                    'passport_expires_at' => $data->passportExpiresAt->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->audit->append(new AppendAuditData(
                    actorType: 'customer',
                    actorId: $data->ownerId,
                    action: 'traveler.created',
                    subjectType: 'traveler',
                    subjectId: $id,
                    metadata: [],
                    oldState: null,
                    newState: ['profile_created' => true],
                    reason: null,
                    correlationId: $correlationId,
                ));

                return new TravelerSnapshot(
                    $id,
                    trim($data->fullName),
                    CarbonImmutable::parse($data->dateOfBirth->toDateString()),
                    $data->gender,
                    $normalizedPassport,
                    CarbonImmutable::parse($data->passportIssuedAt->toDateString()),
                    CarbonImmutable::parse($data->passportExpiresAt->toDateString()),
                );
            });
        } catch (QueryException $exception) {
            if ($this->isPassportConflict($exception)) {
                throw new DuplicatePassport;
            }

            throw $exception;
        }
    }

    private function isPassportConflict(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23505'
            && str_contains($exception->getMessage(), 'travelers_normalized_passport_number_unique');
    }
}
