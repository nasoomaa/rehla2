<?php

declare(strict_types=1);

namespace Rehla\Catalog\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Catalog\Contracts\CatalogAuthorizer;
use Rehla\Catalog\Data\FulfillmentPolicyData;
use Rehla\Catalog\Exceptions\FulfillmentPolicyMissing;
use Rehla\Catalog\Exceptions\ServiceNotFound;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;

final readonly class PublishFulfillmentPolicy
{
    public function __construct(private CatalogAuthorizer $authorizer, private AuditWriter $audit, private Clock $clock) {}

    public function handle(string $serviceId, string $actorId, string $correlationId): FulfillmentPolicyData
    {
        OpaqueId::fromString($serviceId);
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        $this->authorizer->assertCanManage($actorId);

        return DB::transaction(function () use ($serviceId, $actorId, $correlationId): FulfillmentPolicyData {
            if (! DB::table('services')->where('id', $serviceId)->lockForUpdate()->exists()) {
                throw new ServiceNotFound;
            }
            $draft = DB::table('fulfillment_policy_drafts')->where('service_id', $serviceId)->lockForUpdate()->first();
            if ($draft === null) {
                throw new FulfillmentPolicyMissing;
            }
            $policy = json_decode((string) $draft->policy, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($policy)) {
                throw new FulfillmentPolicyMissing;
            }
            $latest = DB::table('fulfillment_policy_versions')
                ->where('service_id', $serviceId)->orderByDesc('version')->lockForUpdate()->first();
            $lastVersion = $latest === null ? 0 : (int) $latest->version;
            $version = $lastVersion + 1;
            $id = OpaqueId::generate()->value();
            $now = $this->clock->now();
            $checksum = hash('sha256', json_encode($this->canonicalize($policy), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            DB::table('fulfillment_policy_versions')->insert([
                'id' => $id, 'service_id' => $serviceId, 'version' => $version,
                'policy' => json_encode($policy, JSON_THROW_ON_ERROR), 'checksum' => $checksum,
                'published_by' => $actorId, 'published_at' => $now, 'created_at' => $now,
            ]);
            $this->audit->append(new AppendAuditData(
                'staff', $actorId, 'fulfillment_policy.published', 'fulfillment_policy', $id,
                ['service_id' => $serviceId], null, ['version' => $version, 'checksum' => $checksum], null, $correlationId,
            ));

            return FulfillmentPolicyData::published($policy, $id, $serviceId, $version, $checksum, CarbonImmutable::instance($now));
        });
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
