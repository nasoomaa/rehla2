<?php

declare(strict_types=1);

namespace Rehla\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Catalog\Contracts\CatalogAuthorizer;
use Rehla\Catalog\Data\ServiceData;
use Rehla\Catalog\Data\ServiceSnapshot;
use Rehla\Catalog\Exceptions\ServiceNotFound;
use Rehla\Catalog\Exceptions\ServiceNotPublishable;
use Rehla\Catalog\Queries\GetServiceDetails;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Documents\Contracts\PublicDocuments;
use Rehla\Documents\Enums\DocumentPurpose;

final readonly class UpdateServiceContent
{
    public function __construct(
        private CatalogAuthorizer $authorizer,
        private PublicDocuments $documents,
        private AuditWriter $audit,
        private Clock $clock,
        private GetServiceDetails $details,
    ) {}

    public function handle(string $serviceId, ServiceData $data, string $actorId, string $correlationId): ServiceSnapshot
    {
        OpaqueId::fromString($serviceId);
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        $this->authorizer->assertCanManage($actorId);

        return DB::transaction(function () use ($serviceId, $data, $actorId, $correlationId): ServiceSnapshot {
            $service = DB::table('services')->where('id', $serviceId)->lockForUpdate()->first();
            if ($service === null) {
                throw new ServiceNotFound;
            }
            if ((int) $service->current_price_minor !== $data->priceMinor) {
                throw new ServiceNotPublishable;
            }
            $ids = array_map(static fn ($media): string => $media->documentId, $data->media);
            $this->documents->assertCleanPublic($ids, $actorId, array_fill(0, count($ids), DocumentPurpose::ServiceMedia));
            $now = $this->clock->now();
            DB::table('services')->where('id', $serviceId)->update([
                'slug' => $data->slug,
                'name_en' => trim($data->name['en']), 'name_ar' => trim($data->name['ar']),
                'short_description_en' => trim($data->shortDescription['en']), 'short_description_ar' => trim($data->shortDescription['ar']),
                'detailed_description_en' => trim($data->detailedDescription['en']), 'detailed_description_ar' => trim($data->detailedDescription['ar']),
                'expected_duration_en' => trim($data->expectedDuration['en']), 'expected_duration_ar' => trim($data->expectedDuration['ar']),
                'notes_en' => trim($data->notes['en']), 'notes_ar' => trim($data->notes['ar']),
                'sort_order' => $data->sortOrder, 'updated_at' => $now,
            ]);
            DB::table('service_requirements')->where('service_id', $serviceId)->delete();
            DB::table('service_media')->where('service_id', $serviceId)->delete();
            foreach ($data->requirements as $requirement) {
                DB::table('service_requirements')->insert([
                    'id' => OpaqueId::generate()->value(), 'service_id' => $serviceId,
                    'text_en' => trim($requirement->text['en']), 'text_ar' => trim($requirement->text['ar']),
                    'sort_order' => $requirement->sortOrder,
                ]);
            }
            foreach ($data->media as $media) {
                DB::table('service_media')->insert([
                    'id' => OpaqueId::generate()->value(), 'service_id' => $serviceId,
                    'document_id' => $media->documentId, 'alt_en' => trim($media->alt['en']),
                    'alt_ar' => trim($media->alt['ar']), 'sort_order' => $media->sortOrder,
                ]);
            }
            $this->audit->append(new AppendAuditData(
                'staff', $actorId, 'service.content_updated', 'service', $serviceId, [], null,
                ['content_updated' => true], null, $correlationId,
            ));

            return $this->details->getById($serviceId);
        });
    }
}
