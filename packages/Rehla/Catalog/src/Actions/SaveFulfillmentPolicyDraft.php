<?php

declare(strict_types=1);

namespace Rehla\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Catalog\Contracts\CatalogAuthorizer;
use Rehla\Catalog\Data\FulfillmentPolicyData;
use Rehla\Catalog\Exceptions\ServiceNotFound;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;

final readonly class SaveFulfillmentPolicyDraft
{
    public function __construct(private CatalogAuthorizer $authorizer, private AuditWriter $audit, private Clock $clock) {}

    public function handle(string $serviceId, FulfillmentPolicyData $data, string $actorId, string $correlationId): void
    {
        OpaqueId::fromString($serviceId);
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        $this->authorizer->assertCanManage($actorId);
        DB::transaction(function () use ($serviceId, $data, $actorId, $correlationId): void {
            if (! DB::table('services')->where('id', $serviceId)->lockForUpdate()->exists()) {
                throw new ServiceNotFound;
            }
            $now = $this->clock->now();
            DB::table('fulfillment_policy_drafts')->upsert([[
                'id' => OpaqueId::generate()->value(),
                'service_id' => $serviceId,
                'policy' => json_encode($data->policy, JSON_THROW_ON_ERROR),
                'updated_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['service_id'], ['policy', 'updated_by', 'updated_at']);
            $this->audit->append(new AppendAuditData(
                'staff', $actorId, 'fulfillment_policy.draft_saved', 'service', $serviceId,
                [], null, ['draft_saved' => true], null, $correlationId,
            ));
        });
    }
}
