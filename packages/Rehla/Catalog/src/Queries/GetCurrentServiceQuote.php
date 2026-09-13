<?php

declare(strict_types=1);

namespace Rehla\Catalog\Queries;

use Illuminate\Support\Facades\DB;
use Rehla\Catalog\Contracts\ServiceQuoteReader;
use Rehla\Catalog\Data\ServiceQuote;
use Rehla\Catalog\Enums\ServiceStatus;
use Rehla\Catalog\Exceptions\ServiceNotFound;
use Rehla\Core\Identifiers\OpaqueId;

final class GetCurrentServiceQuote implements ServiceQuoteReader
{
    public function currentQuote(string $serviceId): ServiceQuote
    {
        OpaqueId::fromString($serviceId);
        $service = DB::table('services')->where('id', $serviceId)->first();
        if ($service === null) {
            throw new ServiceNotFound;
        }

        return new ServiceQuote(
            (string) $service->id, (int) $service->current_price_minor, (string) $service->currency,
            (int) $service->price_version, $service->status === ServiceStatus::Active->value,
        );
    }
}
