<?php

declare(strict_types=1);

namespace Rehla\Documents\Queries;

use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Documents\Contracts\DocumentDownloadAuthorizer;
use Rehla\Documents\Enums\DocumentStatus;
use Rehla\Documents\Exceptions\DocumentAccessDenied;
use Rehla\Identity\Contracts\AuthorizesActor;
use Rehla\Identity\Data\ActorData;
use Rehla\Identity\Enums\AbilityName;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class AuthorizeDocumentDownload implements DocumentDownloadAuthorizer
{
    public function __construct(
        private AuthorizesActor $authorization,
        private FilesystemManager $filesystems,
    ) {}

    public function authorize(string $documentId, ActorData $actor): StreamedResponse
    {
        OpaqueId::fromString($documentId);
        $row = DB::table('documents')->where('id', $documentId)->first();
        if ($row === null
            || ! in_array($row->status, [DocumentStatus::Clean->value, DocumentStatus::Attached->value], true)
            || $row->disk !== 'private'
            || ! is_string($row->storage_key)
            || ! is_string($row->detected_mime)) {
            throw new DocumentAccessDenied;
        }

        $allowed = ($actor->type === 'customer' && $row->owner_id === $actor->id)
            || ($actor->type === 'staff'
                && $this->authorization->allows($actor, AbilityName::DocumentsViewSensitive));
        if (! $allowed) {
            throw new DocumentAccessDenied;
        }

        $disk = (string) $row->disk;
        $storageKey = $row->storage_key;
        $fallback = 'document-'.$documentId.$this->extensionFor($row->detected_mime);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            (string) $row->original_name,
            $fallback,
        );

        return new StreamedResponse(function () use ($disk, $storageKey): void {
            $stream = $this->filesystems->disk($disk)->readStream($storageKey);
            if (! is_resource($stream)) {
                throw new RuntimeException;
            }
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $row->detected_mime,
            'Content-Disposition' => $disposition,
            'Content-Security-Policy' => "default-src 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Expires' => '0',
        ]);
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => '.pdf',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            default => throw new DocumentAccessDenied,
        };
    }
}
