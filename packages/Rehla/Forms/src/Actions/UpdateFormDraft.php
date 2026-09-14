<?php

declare(strict_types=1);

namespace Rehla\Forms\Actions;

use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Forms\Contracts\FormsAuthorizer;
use Rehla\Forms\Data\FormFieldData;
use Rehla\Forms\Exceptions\FormDraftNotFound;
use Rehla\Forms\Exceptions\FormValidationFailed;

final readonly class UpdateFormDraft
{
    public function __construct(private FormsAuthorizer $authorizer, private AuditWriter $audit, private Clock $clock) {}

    /** @param list<FormFieldData> $fields */
    public function handle(string $draftId, array $fields, string $actorId, string $correlationId): void
    {
        OpaqueId::fromString($draftId);
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        $keys = array_map(static fn (FormFieldData $field): string => $field->key, $fields);
        $orders = array_map(static fn (FormFieldData $field): int => $field->order, $fields);
        if (count($keys) !== count(array_unique($keys)) || count($orders) !== count(array_unique($orders))) {
            throw new FormValidationFailed(['schema' => ['form.duplicate_field_or_order']]);
        }
        usort($fields, static fn (FormFieldData $left, FormFieldData $right): int => $left->order <=> $right->order);
        $this->authorizer->assertCanDraft($actorId);

        DB::transaction(function () use ($draftId, $fields, $actorId, $correlationId): void {
            if (! DB::table('form_drafts')->where('id', $draftId)->lockForUpdate()->exists()) {
                throw new FormDraftNotFound;
            }
            $now = $this->clock->now();
            DB::table('form_drafts')->where('id', $draftId)->update([
                'schema' => json_encode(['fields' => array_map(static fn (FormFieldData $field): array => $field->toArray(), $fields)], JSON_THROW_ON_ERROR),
                'updated_by' => $actorId, 'updated_at' => $now,
            ]);
            $this->audit->append(new AppendAuditData(
                'staff', $actorId, 'form.draft_updated', 'form_draft', $draftId,
                ['field_count' => count($fields)], null, ['schema_updated' => true], null, $correlationId,
            ));
        });
    }
}
