<?php

declare(strict_types=1);

namespace Rehla\Travelers\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Travelers\Contracts\TravelerReader;
use Rehla\Travelers\Data\TravelerSnapshot;
use Rehla\Travelers\Enums\Gender;

final class ListOwnedTravelers implements TravelerReader
{
    public function listOwned(string $accountId, int $page, int $perPage): array
    {
        OpaqueId::fromString($accountId);
        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid traveler pagination.');
        }

        $travelers = DB::table('travelers')
            ->where('owner_id', $accountId)
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(static fn (object $row): TravelerSnapshot => new TravelerSnapshot(
                (string) $row->id,
                (string) $row->full_name,
                CarbonImmutable::parse((string) $row->date_of_birth),
                Gender::from((string) $row->gender),
                (string) $row->normalized_passport_number,
                CarbonImmutable::parse((string) $row->passport_issued_at),
                CarbonImmutable::parse((string) $row->passport_expires_at),
            ))
            ->all();

        return array_values($travelers);
    }
}
