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

final readonly class PublishService
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
            $hasRequirements = DB::table('service_requirements')->where('service_id', $serviceId)->exists();
            $mediaCount = DB::table('service_media')->where('service_id', $serviceId)->count();
            $cleanPublicMediaCount = DB::table('service_media')
                ->join('documents', 'documents.id', '=', 'service_media.document_id')
                ->where('service_media.service_id', $serviceId)
                ->where('documents.status', 'attached')
                ->where('documents.disk', 'public')
                ->where('documents.purpose', 'service_media')
                ->count();
            if ($service->status === ServiceStatus::Active->value
                || (int) $service->current_price_minor < 1
                || ! $hasRequirements || $mediaCount === 0 || $mediaCount !== $cleanPublicMediaCount) {
                throw new ServiceNotPublishable;
            }
            $now = $this->clock->now();
            DB::table('services')->where('id', $serviceId)->update([
                'status' => ServiceStatus::Active->value,
                'published_at' => $now,
                'updated_at' => $now,
            ]);
            $this->audit->append(new AppendAuditData(
                'staff', $actorId, 'service.published', 'service', $serviceId, [],
                ['status' => (string) $service->status], ['status' => ServiceStatus::Active->value], null, $correlationId,
            ));
        });
    }
}
