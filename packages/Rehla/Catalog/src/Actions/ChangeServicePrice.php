<?php

declare(strict_types=1);

namespace Rehla\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Catalog\Contracts\CatalogAuthorizer;
use Rehla\Catalog\Data\ServiceQuote;
use Rehla\Catalog\Enums\ServiceStatus;
use Rehla\Catalog\Exceptions\ServiceNotFound;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;

final readonly class ChangeServicePrice
{
    public function __construct(private CatalogAuthorizer $authorizer, private AuditWriter $audit, private Clock $clock) {}

    public function handle(string $serviceId, int $priceMinor, string $actorId, string $correlationId): ServiceQuote
    {
        OpaqueId::fromString($serviceId);
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        if ($priceMinor < 1) {
            throw new InvalidArgumentException((string) Lang::get('rehla-catalog::messages.validation.price_positive'));
        }
        $this->authorizer->assertCanManage($actorId);

        return DB::transaction(function () use ($serviceId, $priceMinor, $actorId, $correlationId): ServiceQuote {
            $service = DB::table('services')->where('id', $serviceId)->lockForUpdate()->first();
            if ($service === null) {
                throw new ServiceNotFound;
            }
            if ((int) $service->current_price_minor === $priceMinor) {
                throw new InvalidArgumentException((string) Lang::get('rehla-catalog::messages.validation.price_different'));
            }
            $version = (int) $service->price_version + 1;
            $now = $this->clock->now();
            DB::table('services')->where('id', $serviceId)->update([
                'current_price_minor' => $priceMinor,
                'price_version' => $version,
                'updated_at' => $now,
            ]);
            DB::table('service_price_history')->insert([
                'id' => OpaqueId::generate()->value(), 'service_id' => $serviceId,
                'price_minor' => $priceMinor, 'currency' => 'SDG', 'version' => $version,
                'changed_by' => $actorId, 'effective_at' => $now,
            ]);
            $this->audit->append(new AppendAuditData(
                'staff', $actorId, 'service.price_changed', 'service', $serviceId, [],
                ['price_minor' => (int) $service->current_price_minor, 'version' => (int) $service->price_version],
                ['price_minor' => $priceMinor, 'version' => $version], null, $correlationId,
            ));

            return new ServiceQuote($serviceId, $priceMinor, 'SDG', $version, $service->status === ServiceStatus::Active->value);
        });
    }
}
