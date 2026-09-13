<?php

declare(strict_types=1);

namespace Rehla\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Catalog\Contracts\CatalogAuthorizer;
use Rehla\Catalog\Exceptions\ServiceNotFound;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;

final readonly class ReorderServices
{
    public function __construct(private CatalogAuthorizer $authorizer, private AuditWriter $audit, private Clock $clock) {}

    /** @param list<string> $serviceIds */
    public function handle(array $serviceIds, string $actorId, string $correlationId): void
    {
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        if ($serviceIds === [] || count($serviceIds) !== count(array_unique($serviceIds))) {
            throw new InvalidArgumentException((string) Lang::get('rehla-catalog::messages.validation.service_order'));
        }
        foreach ($serviceIds as $serviceId) {
            OpaqueId::fromString($serviceId);
        }
        $this->authorizer->assertCanManage($actorId);

        DB::transaction(function () use ($serviceIds, $actorId, $correlationId): void {
            $lockedIds = DB::table('services')->whereIn('id', $serviceIds)->orderBy('id')->lockForUpdate()->pluck('id')->all();
            if (count($lockedIds) !== count($serviceIds)) {
                throw new ServiceNotFound;
            }
            $now = $this->clock->now();
            foreach ($serviceIds as $position => $serviceId) {
                DB::table('services')->where('id', $serviceId)->update(['sort_order' => $position + 1, 'updated_at' => $now]);
                $this->audit->append(new AppendAuditData(
                    'staff', $actorId, 'service.reordered', 'service', $serviceId,
                    ['sort_order' => $position + 1], null, ['sort_order' => $position + 1], null, $correlationId,
                ));
            }
        });
    }
}
