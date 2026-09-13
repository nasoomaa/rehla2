<?php

declare(strict_types=1);

namespace Rehla\Documents\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Rehla\Core\Errors\ProblemCode;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Documents\Contracts\PublicDocuments;
use Rehla\Documents\Data\DocumentReference;
use Rehla\Documents\Enums\DocumentPurpose;
use Rehla\Documents\Enums\DocumentStatus;
use Rehla\Documents\Exceptions\DocumentAccessDenied;
use Rehla\Documents\Exceptions\DocumentNotClean;

final readonly class AttachPublicDocuments implements PublicDocuments
{
    public function __construct(private Clock $clock) {}

    public function assertCleanPublic(array $documentIds, string $ownerId, array $requiredPurposes): array
    {
        OpaqueId::fromString($ownerId);
        if (DB::transactionLevel() === 0
            || $documentIds === []
            || count($documentIds) !== count($requiredPurposes)
            || count($documentIds) !== count(array_unique($documentIds))) {
            throw new InvalidArgumentException;
        }

        $requiredById = [];
        foreach ($documentIds as $index => $documentId) {
            OpaqueId::fromString($documentId);
            $purpose = $requiredPurposes[$index] ?? null;
            if (! $purpose instanceof DocumentPurpose || ! $purpose->isPublic()) {
                throw new InvalidArgumentException;
            }
            $requiredById[$documentId] = $purpose;
        }
        ksort($requiredById);

        $rows = DB::table('documents')
            ->whereIn('id', array_keys($requiredById))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $references = [];
        $now = $this->clock->now();
        foreach ($requiredById as $documentId => $purpose) {
            $row = $rows->get($documentId);
            if ($row === null || $row->owner_id !== $ownerId) {
                throw new DocumentAccessDenied;
            }
            if ($row->status !== DocumentStatus::Clean->value || $row->disk !== 'public') {
                throw new DocumentNotClean;
            }
            if ($row->purpose !== $purpose->value) {
                throw new DocumentNotClean(ProblemCode::DocumentInvalidAttachment);
            }

            DB::table('documents')->where('id', $documentId)->update([
                'status' => DocumentStatus::Attached->value,
                'attached_at' => $now,
                'updated_at' => $now,
            ]);
            $references[] = new DocumentReference(
                id: $documentId,
                purpose: $purpose,
                status: DocumentStatus::Attached,
                originalName: (string) $row->original_name,
                detectedMime: is_string($row->detected_mime) ? $row->detected_mime : null,
                sizeBytes: (int) $row->size_bytes,
                sha256: (string) $row->sha256,
            );
        }

        return $references;
    }
}
