<?php

declare(strict_types=1);

namespace Rehla\Catalog\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Rehla\Catalog\Contracts\PublishedFulfillmentPolicyReader;
use Rehla\Catalog\Data\FulfillmentPolicyData;
use Rehla\Catalog\Exceptions\FulfillmentPolicyMissing;
use Rehla\Core\Identifiers\OpaqueId;

final class GetPublishedFulfillmentPolicy implements PublishedFulfillmentPolicyReader
{
    public function forService(string $serviceId): FulfillmentPolicyData
    {
        OpaqueId::fromString($serviceId);
        $row = DB::table('fulfillment_policy_versions')->where('service_id', $serviceId)->orderByDesc('version')->first();
        if ($row === null) {
            throw new FulfillmentPolicyMissing;
        }
        $policy = json_decode((string) $row->policy, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($policy)) {
            throw new FulfillmentPolicyMissing;
        }

        return FulfillmentPolicyData::published(
            $policy, (string) $row->id, (string) $row->service_id, (int) $row->version,
            (string) $row->checksum, CarbonImmutable::parse((string) $row->published_at),
        );
    }
}
