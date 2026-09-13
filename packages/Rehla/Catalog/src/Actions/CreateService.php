<?php

declare(strict_types=1);

namespace Rehla\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Catalog\Contracts\CatalogAuthorizer;
use Rehla\Catalog\Data\ServiceData;
use Rehla\Catalog\Data\ServiceSnapshot;
use Rehla\Catalog\Enums\ServiceStatus;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Documents\Contracts\PublicDocuments;
use Rehla\Documents\Enums\DocumentPurpose;

final readonly class CreateService
{
    public function __construct(
        private CatalogAuthorizer $authorizer,
        private PublicDocuments $documents,
        private AuditWriter $audit,
        private Clock $clock,
    ) {}

    public function handle(ServiceData $data, string $actorId, string $correlationId): ServiceSnapshot
    {
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        $this->authorizer->assertCanManage($actorId);

        return DB::transaction(function () use ($data, $actorId, $correlationId): ServiceSnapshot {
            $id = OpaqueId::generate()->value();
            $now = $this->clock->now();
            $documentIds = array_map(static fn ($media): string => $media->documentId, $data->media);
            $this->documents->assertCleanPublic(
                $documentIds,
                $actorId,
                array_fill(0, count($documentIds), DocumentPurpose::ServiceMedia),
            );

            DB::table('services')->insert([
                'id' => $id,
                'slug' => $data->slug,
                'name_en' => trim($data->name['en']),
                'name_ar' => trim($data->name['ar']),
                'short_description_en' => trim($data->shortDescription['en']),
                'short_description_ar' => trim($data->shortDescription['ar']),
                'detailed_description_en' => trim($data->detailedDescription['en']),
                'detailed_description_ar' => trim($data->detailedDescription['ar']),
                'expected_duration_en' => trim($data->expectedDuration['en']),
                'expected_duration_ar' => trim($data->expectedDuration['ar']),
                'notes_en' => trim($data->notes['en']),
                'notes_ar' => trim($data->notes['ar']),
                'current_price_minor' => $data->priceMinor,
                'currency' => 'SDG',
                'price_version' => 1,
                'status' => ServiceStatus::Draft->value,
                'sort_order' => $data->sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('service_price_history')->insert([
                'id' => OpaqueId::generate()->value(),
                'service_id' => $id,
                'price_minor' => $data->priceMinor,
                'currency' => 'SDG',
                'version' => 1,
                'changed_by' => $actorId,
                'effective_at' => $now,
            ]);
            foreach ($data->requirements as $requirement) {
                DB::table('service_requirements')->insert([
                    'id' => OpaqueId::generate()->value(),
                    'service_id' => $id,
                    'text_en' => trim($requirement->text['en']),
                    'text_ar' => trim($requirement->text['ar']),
                    'sort_order' => $requirement->sortOrder,
                ]);
            }
            foreach ($data->media as $media) {
                DB::table('service_media')->insert([
                    'id' => OpaqueId::generate()->value(),
                    'service_id' => $id,
                    'document_id' => $media->documentId,
                    'alt_en' => trim($media->alt['en']),
                    'alt_ar' => trim($media->alt['ar']),
                    'sort_order' => $media->sortOrder,
                ]);
            }
            $this->audit->append(new AppendAuditData(
                'staff', $actorId, 'service.created', 'service', $id, [], null,
                ['status' => ServiceStatus::Draft->value, 'price_minor' => $data->priceMinor],
                null, $correlationId,
            ));

            return new ServiceSnapshot(
                $id, $data->slug, $data->name,
                ['short' => $data->shortDescription, 'detailed' => $data->detailedDescription],
                $data->expectedDuration, $data->notes, $data->requirements, $data->media,
            );
        });
    }
}
