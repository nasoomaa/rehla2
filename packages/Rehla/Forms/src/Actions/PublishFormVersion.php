<?php

declare(strict_types=1);

namespace Rehla\Forms\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Forms\Contracts\FormsAuthorizer;
use Rehla\Forms\Data\FormFieldData;
use Rehla\Forms\Data\PublishedFormData;
use Rehla\Forms\Enums\FormVersionStatus;
use Rehla\Forms\Exceptions\FormDraftNotFound;
use Rehla\Forms\Exceptions\FormValidationFailed;
use stdClass;

final readonly class PublishFormVersion
{
    public function __construct(private FormsAuthorizer $authorizer, private AuditWriter $audit, private Clock $clock) {}

    public function handle(string $draftId, string $actorId, string $correlationId): PublishedFormData
    {
        OpaqueId::fromString($draftId);
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        $this->authorizer->assertCanPublish($actorId);

        return DB::transaction(function () use ($draftId, $actorId, $correlationId): PublishedFormData {
            $draft = DB::table('form_drafts')->where('id', $draftId)->lockForUpdate()->first();
            if ($draft === null) {
                throw new FormDraftNotFound;
            }
            $draftSchema = json_decode((string) $draft->schema, true, flags: JSON_THROW_ON_ERROR);
            $rawFields = is_array($draftSchema) ? ($draftSchema['fields'] ?? null) : null;
            if (! is_array($rawFields) || $rawFields === []) {
                throw new FormValidationFailed(['schema' => ['form.empty_schema']]);
            }
            $fields = array_values(array_map(
                static fn (mixed $field): FormFieldData => is_array($field)
                    ? FormFieldData::fromArray($field)
                    : throw new FormValidationFailed(['schema' => ['form.invalid_field_definition']]),
                $rawFields,
            ));
            $latest = DB::table('form_versions')->where('service_id', $draft->service_id)
                ->orderByDesc('version')->lockForUpdate()->first();
            $version = $latest === null ? 1 : (int) $latest->version + 1;
            $schema = ['fields' => array_map(static fn (FormFieldData $field): array => $field->toArray(), $fields), 'version' => $version];
            $canonical = $this->canonicalize($schema);
            $checksum = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $id = OpaqueId::generate()->value();
            $now = $this->clock->now();
            DB::table('form_versions')->insert([
                'id' => $id, 'service_id' => $draft->service_id, 'version' => $version,
                'schema' => json_encode($schema, JSON_THROW_ON_ERROR), 'checksum' => $checksum,
                'status' => FormVersionStatus::Published->value, 'published_by' => $actorId,
                'published_at' => $now, 'created_at' => $now,
            ]);
            DB::table('form_drafts')->where('id', $draftId)->update([
                'current_version_id' => $id,
                'updated_by' => $actorId,
                'updated_at' => $now,
            ]);
            $this->audit->append(new AppendAuditData(
                'staff', $actorId, 'form.version_published', 'form_version', $id,
                ['service_id' => (string) $draft->service_id], null,
                ['version' => $version, 'checksum' => $checksum], null, $correlationId,
            ));

            return new PublishedFormData(
                $id, (string) $draft->service_id, $version, $fields, $checksum, CarbonImmutable::instance($now),
            );
        });
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties);
            $canonical = new stdClass;
            foreach ($properties as $key => $item) {
                $canonical->{$key} = $this->canonicalize($item);
            }

            return $canonical;
        }
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
