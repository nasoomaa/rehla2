<?php

declare(strict_types=1);

namespace Rehla\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Catalog\Contracts\CatalogAuthorizer;
use Rehla\Catalog\Enums\ServiceStatus;
use Rehla\Catalog\Exceptions\ServiceNotFound;
use Rehla\Catalog\Exceptions\ServiceNotPublishable;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;

final readonly class DeactivateService
{
    public function __construct(private CatalogAuthorizer $authorizer, private AuditWriter $audit, private Clock $clock) {}

    public function handle(string $serviceId, string $actorId, string $correlationId): void
    {
        OpaqueId::fromString($serviceId);
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        $this->authorizer->assertCanManage($actorId);
        DB::transaction(function () use ($serviceId, $actorId, $correlationId): void {
            $service = DB::table('services')->where('id', $serviceId)->lockForUpdate()->first();
            if ($service === null) {
                throw new ServiceNotFound;
            }
            if ($service->status !== ServiceStatus::Active->value) {
                throw new ServiceNotPublishable;
            }
            $now = $this->clock->now();
            DB::table('services')->where('id', $serviceId)->update(['status' => ServiceStatus::Inactive->value, 'updated_at' => $now]);
            $this->audit->append(new AppendAuditData(
                'staff', $actorId, 'service.deactivated', 'service', $serviceId, [],
                ['status' => ServiceStatus::Active->value], ['status' => ServiceStatus::Inactive->value], null, $correlationId,
            ));
        });
    }
}
