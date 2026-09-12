<?php

declare(strict_types=1);

namespace Rehla\Documents\Actions;

use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Rehla\Core\Errors\ProblemCode;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Documents\Data\DocumentReference;
use Rehla\Documents\Data\StoreUploadData;
use Rehla\Documents\Enums\DocumentPurpose;
use Rehla\Documents\Enums\DocumentStatus;
use Rehla\Documents\Exceptions\DocumentAccessDenied;
use Rehla\Documents\Exceptions\InvalidDocumentUpload;
use Throwable;

final readonly class StoreUpload
{
    /** @var array<string, list<string>> */
    private const array EXTENSIONS_BY_MIME = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    public function __construct(
        private FilesystemManager $filesystems,
        private Clock $clock,
    ) {}

    public function handle(StoreUploadData $data): DocumentReference
    {
        $purpose = $this->sessionPurpose($data);
        $extension = strtolower(pathinfo($data->originalName, PATHINFO_EXTENSION));
        $size = strlen($data->contents);

        if (! in_array($data->declaredMime, $purpose->allowedMimeTypes(), true)
            || ! in_array($extension, self::EXTENSIONS_BY_MIME[$data->declaredMime] ?? [], true)) {
            throw new InvalidDocumentUpload(ProblemCode::DocumentUnsupportedType);
        }
        if ($size > $purpose->maximumBytes($data->declaredMime)) {
            throw new InvalidDocumentUpload(ProblemCode::DocumentFileTooLarge);
        }
        if ($size === 0) {
            throw new InvalidDocumentUpload(ProblemCode::DocumentVerificationFailed);
        }

        $documentId = OpaqueId::generate()->value();
        $storageKey = 'staging/'.OpaqueId::generate()->value().'.'.$extension;
        $disk = 'private';

        if (! $this->filesystems->disk($disk)->put($storageKey, $data->contents)) {
            throw new InvalidDocumentUpload(ProblemCode::DocumentVerificationFailed);
        }

        try {
            $now = $this->clock->now();
            DB::transaction(function () use ($data, $purpose, $documentId, $disk, $storageKey, $size, $now): void {
                $claimed = DB::table('upload_sessions')
                    ->where('id', $data->uploadSessionId)
                    ->where('owner_id', $data->ownerId)
                    ->where('purpose', $purpose->value)
                    ->whereNull('claimed_at')
                    ->where('expires_at', '>', $now)
                    ->lockForUpdate()
                    ->first();
                if ($claimed === null) {
                    throw new DocumentAccessDenied;
                }

                DB::table('documents')->insert([
                    'id' => $documentId,
                    'upload_session_id' => $data->uploadSessionId,
                    'owner_id' => $data->ownerId,
                    'purpose' => $purpose->value,
                    'disk' => $disk,
                    'storage_key' => $storageKey,
                    'original_name' => $data->originalName,
                    'declared_mime' => $data->declaredMime,
                    'size_bytes' => $size,
                    'sha256' => hash('sha256', $data->contents),
                    'status' => DocumentStatus::PendingScan->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('upload_sessions')->where('id', $data->uploadSessionId)->update([
                    'claimed_at' => $now,
                    'updated_at' => $now,
                ]);
            });
        } catch (Throwable $exception) {
            $this->filesystems->disk($disk)->delete($storageKey);

            throw $exception;
        }

        return new DocumentReference(
            id: $documentId,
            purpose: $purpose,
            status: DocumentStatus::PendingScan,
            originalName: $data->originalName,
            detectedMime: null,
            sizeBytes: $size,
            sha256: hash('sha256', $data->contents),
        );
    }

    private function sessionPurpose(StoreUploadData $data): DocumentPurpose
    {
        $purpose = DB::table('upload_sessions')
            ->where('id', $data->uploadSessionId)
            ->where('owner_id', $data->ownerId)
            ->whereNull('claimed_at')
            ->value('purpose');

        if (! is_string($purpose)) {
            throw new DocumentAccessDenied;
        }

        return DocumentPurpose::from($purpose);
    }
}
