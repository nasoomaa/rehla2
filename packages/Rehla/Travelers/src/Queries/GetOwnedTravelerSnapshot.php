<?php

declare(strict_types=1);

namespace Rehla\Travelers\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Travelers\Contracts\TravelerSnapshotReader;
use Rehla\Travelers\Data\TravelerSnapshot;
use Rehla\Travelers\Enums\Gender;
use Rehla\Travelers\Exceptions\TravelerNotFound;

final class GetOwnedTravelerSnapshot implements TravelerSnapshotReader
{
    public function handle(string $accountId, string $travelerId): TravelerSnapshot
    {
        return $this->getOwned($accountId, $travelerId);
    }

    public function getOwned(string $accountId, string $travelerId): TravelerSnapshot
    {
        OpaqueId::fromString($accountId);
        OpaqueId::fromString($travelerId);
        $row = DB::table('travelers')
            ->where('id', $travelerId)
            ->where('owner_id', $accountId)
            ->first();
        if ($row === null) {
            throw new TravelerNotFound;
        }

        return new TravelerSnapshot(
            (string) $row->id,
            (string) $row->full_name,
            CarbonImmutable::parse((string) $row->date_of_birth),
            Gender::from((string) $row->gender),
            (string) $row->normalized_passport_number,
            CarbonImmutable::parse((string) $row->passport_issued_at),
            CarbonImmutable::parse((string) $row->passport_expires_at),
        );
    }
}
