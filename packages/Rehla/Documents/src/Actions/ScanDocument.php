<?php

declare(strict_types=1);

namespace Rehla\Documents\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Documents\Contracts\DocumentScanner;
use Rehla\Documents\Data\DocumentReference;
use Rehla\Documents\Data\ScanResult;
use Rehla\Documents\Enums\DocumentPurpose;
use Rehla\Documents\Enums\DocumentStatus;
use Rehla\Documents\Exceptions\DocumentAccessDenied;
use RuntimeException;
use stdClass;

final readonly class ScanDocument
{
    public function __construct(
        private DocumentScanner $scanner,
        private FilesystemManager $filesystems,
        private AuditWriter $audit,
        private Clock $clock,
    ) {}

    public function handle(string $documentId): DocumentReference
    {
        OpaqueId::fromString($documentId);
        $claim = $this->claim($documentId);
        if ($claim === null) {
            return $this->reference($documentId);
        }

        $contents = $this->filesystems->disk($claim['disk'])->get($claim['storage_key']);
        if (! is_string($contents)) {
            throw new RuntimeException;
        }
        [$structuralResult, $detectedMime, $sanitized] = $this->inspect(
            $contents,
            $claim['declared_mime'],
        );
        $result = $structuralResult->clean ? $this->scanner->scan($contents) : $structuralResult;

        $purpose = DocumentPurpose::from($claim['purpose']);
        $candidateDisk = $result->clean && $purpose->isPublic() ? 'public' : $claim['disk'];
        $candidateContents = $result->clean ? $sanitized : $contents;
        $candidateKey = ($result->clean ? ($purpose->isPublic() ? 'media/' : 'clean/') : 'quarantine/')
            .OpaqueId::generate()->value()
            .$this->extensionFor($detectedMime !== '' ? $detectedMime : $claim['declared_mime']);
        if (! $this->filesystems->disk($candidateDisk)->put($candidateKey, $candidateContents)) {
            throw new RuntimeException;
        }

        $updated = $this->finalize(
            documentId: $documentId,
            token: $claim['token'],
            result: $result,
            detectedMime: $detectedMime,
            contents: $candidateContents,
            candidateKey: $candidateKey,
            candidateDisk: $candidateDisk,
        );

        if (! $updated) {
            $this->filesystems->disk($candidateDisk)->delete($candidateKey);

            return $this->reference($documentId);
        }

        $this->filesystems->disk($claim['disk'])->delete($claim['storage_key']);

        return $this->reference($documentId);
    }

    /**
     * @return array{disk: string, storage_key: string, declared_mime: string, purpose: string, token: string}|null
     */
    private function claim(string $documentId): ?array
    {
        return DB::transaction(function () use ($documentId): ?array {
            $now = $this->clock->now();
            $row = DB::table('documents')->where('id', $documentId)->lockForUpdate()->first();
            if ($row === null) {
                throw new DocumentAccessDenied;
            }

            $claimable = $row->status === DocumentStatus::PendingScan->value
                || ($row->status === DocumentStatus::Quarantined->value
                    && is_string($row->scan_lease_expires_at)
                    && CarbonImmutable::parse($row->scan_lease_expires_at)->isBefore($now));
            if (! $claimable) {
                return null;
            }

            $token = OpaqueId::generate()->value();
            DB::table('documents')->where('id', $documentId)->update([
                'status' => DocumentStatus::Quarantined->value,
                'scan_token' => $token,
                'scan_lease_expires_at' => $now->addSeconds(max(1, (int) config('rehla-documents.scan_lease_seconds', 600))),
                'updated_at' => $now,
            ]);

            return [
                'disk' => (string) $row->disk,
                'storage_key' => (string) $row->storage_key,
                'declared_mime' => (string) $row->declared_mime,
                'purpose' => (string) $row->purpose,
                'token' => $token,
            ];
        });
    }

    /**
     * @return array{ScanResult, string, string}
     */
    private function inspect(string $contents, string $declaredMime): array
    {
        $detectedMime = match (true) {
            str_starts_with($contents, '%PDF-') => 'application/pdf',
            str_starts_with($contents, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($contents, "\x89PNG\r\n\x1A\n") => 'image/png',
            default => '',
        };

        if ($detectedMime === '' || $detectedMime !== $declaredMime) {
            return [ScanResult::rejected('mime_mismatch'), $detectedMime, $contents];
        }

        if (str_contains($contents, "PK\x03\x04")
            || substr_count($contents, '%PDF-') > 1
            || ($detectedMime !== 'application/pdf' && str_contains($contents, '%PDF-'))) {
            return [ScanResult::rejected('polyglot'), $detectedMime, $contents];
        }

        if ($detectedMime === 'application/pdf') {
            if (preg_match('/%%EOF\s*\z/s', $contents) !== 1) {
                return [ScanResult::rejected('corrupt_file'), $detectedMime, $contents];
            }

            return [ScanResult::clean(), $detectedMime, $contents];
        }

        $sanitized = $this->sanitizeImage($contents, $detectedMime);
        if ($sanitized === null) {
            return [ScanResult::rejected('corrupt_file'), $detectedMime, $contents];
        }

        return [ScanResult::clean(), $detectedMime, $sanitized];
    }

    private function sanitizeImage(string $contents, string $mime): ?string
    {
        if (getimagesizefromstring($contents) === false) {
            return null;
        }

        set_error_handler(static fn (): bool => true);
        try {
            $image = imagecreatefromstring($contents);
        } finally {
            restore_error_handler();
        }
        if ($image === false) {
            return null;
        }

        ob_start();
        $encoded = $mime === 'image/jpeg'
            ? imagejpeg($image, null, 90)
            : imagepng($image, null, 6);
        $sanitized = ob_get_clean();
        imagedestroy($image);

        return $encoded && is_string($sanitized) ? $sanitized : null;
    }

    private function finalize(
        string $documentId,
        string $token,
        ScanResult $result,
        string $detectedMime,
        string $contents,
        string $candidateKey,
        string $candidateDisk,
    ): bool {
        return DB::transaction(function () use (
            $documentId,
            $token,
            $result,
            $detectedMime,
            $contents,
            $candidateKey,
            $candidateDisk,
        ): bool {
            $now = $this->clock->now();
            $values = [
                'detected_mime' => $detectedMime !== '' ? $detectedMime : null,
                'status' => $result->clean ? DocumentStatus::Clean->value : DocumentStatus::Rejected->value,
                'rejection_code' => $result->rejectionCode,
                'storage_key' => $candidateKey,
                'disk' => $candidateDisk,
                'size_bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'scan_token' => null,
                'scan_lease_expires_at' => null,
                'scanned_at' => $now,
                'updated_at' => $now,
            ];
            $updated = DB::table('documents')
                ->where('id', $documentId)
                ->where('status', DocumentStatus::Quarantined->value)
                ->where('scan_token', $token)
                ->where('scan_lease_expires_at', '>=', $now)
                ->update($values);
            if ($updated === 0) {
                return false;
            }

            if (! $result->clean) {
                $this->audit->append(new AppendAuditData(
                    actorType: 'system',
                    actorId: null,
                    action: 'document.scan_rejected',
                    subjectType: 'document',
                    subjectId: $documentId,
                    metadata: ['rejection_code' => $result->rejectionCode],
                    oldState: ['status' => DocumentStatus::Quarantined->value],
                    newState: ['status' => DocumentStatus::Rejected->value],
                    reason: null,
                    correlationId: OpaqueId::generate()->value(),
                ));
            }

            return true;
        });
    }

    private function reference(string $documentId): DocumentReference
    {
        $row = DB::table('documents')->where('id', $documentId)->first();
        if (! $row instanceof stdClass) {
            throw new DocumentAccessDenied;
        }

        return new DocumentReference(
            id: (string) $row->id,
            purpose: DocumentPurpose::from((string) $row->purpose),
            status: DocumentStatus::from((string) $row->status),
            originalName: (string) $row->original_name,
            detectedMime: is_string($row->detected_mime) ? $row->detected_mime : null,
            sizeBytes: (int) $row->size_bytes,
            sha256: (string) $row->sha256,
        );
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => '.pdf',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            default => throw new RuntimeException,
        };
    }
}
