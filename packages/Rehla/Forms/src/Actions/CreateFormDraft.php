<?php

declare(strict_types=1);

namespace Rehla\Forms\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Catalog\Contracts\ServiceCatalog;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Forms\Contracts\FormsAuthorizer;
use Rehla\Forms\Exceptions\FormNotFound;
use Rehla\Forms\Exceptions\FormValidationFailed;

final readonly class CreateFormDraft
{
    public function __construct(
        private FormsAuthorizer $authorizer,
        private ServiceCatalog $services,
        private AuditWriter $audit,
        private Clock $clock,
    ) {}

    public function handle(string $serviceId, ?string $baseVersionId, string $actorId, string $correlationId): string
    {
        OpaqueId::fromString($serviceId);
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        if ($baseVersionId !== null) {
            OpaqueId::fromString($baseVersionId);
        }
        $this->authorizer->assertCanDraft($actorId);
        $this->services->getById($serviceId);

        try {
            return DB::transaction(function () use ($serviceId, $baseVersionId, $actorId, $correlationId): string {
                $schema = ['fields' => []];
                $currentVersionId = null;
                if ($baseVersionId !== null) {
                    $version = DB::table('form_versions')->where('id', $baseVersionId)->where('service_id', $serviceId)->first();
                    if ($version === null) {
                        throw new FormNotFound;
                    }
                    $publishedSchema = json_decode((string) $version->schema, true, flags: JSON_THROW_ON_ERROR);
                    if (! is_array($publishedSchema) || ! is_array($publishedSchema['fields'] ?? null)) {
                        throw new FormNotFound;
                    }
                    $schema = ['fields' => $publishedSchema['fields']];
                    $currentVersionId = $baseVersionId;
                }
                $id = OpaqueId::generate()->value();
                $now = $this->clock->now();
                DB::table('form_drafts')->insert([
                    'id' => $id, 'service_id' => $serviceId,
                    'schema' => json_encode($schema, JSON_THROW_ON_ERROR),
                    'current_version_id' => $currentVersionId, 'updated_by' => $actorId,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $this->audit->append(new AppendAuditData(
                    'staff', $actorId, 'form.draft_created', 'form_draft', $id,
                    ['service_id' => $serviceId], null, ['base_version_id' => $baseVersionId], null, $correlationId,
                ));

                return $id;
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505'
                && str_contains($exception->getMessage(), 'form_drafts_service_id_unique')) {
                throw new FormValidationFailed(['service_id' => ['form.active_draft_exists']]);
            }

            throw $exception;
        }
    }
}
