<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Documents\Actions\BeginUpload;
use Rehla\Documents\Actions\ScanDocument;
use Rehla\Documents\Actions\StoreUpload;
use Rehla\Documents\Contracts\DocumentScanner;
use Rehla\Documents\Contracts\PublicDocuments;
use Rehla\Documents\Data\BeginUploadData;
use Rehla\Documents\Data\ScanResult;
use Rehla\Documents\Data\StoreUploadData;
use Rehla\Documents\Enums\DocumentPurpose;
use Rehla\Documents\Enums\DocumentStatus;
use Rehla\Documents\Exceptions\DocumentAccessDenied;
use Rehla\Documents\Exceptions\DocumentNotClean;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE documents, upload_sessions, users, audit_entries RESTART IDENTITY CASCADE');
    Storage::fake('private');
    Storage::fake('public');
});

function publicMediaPng(): string
{
    $image = imagecreatetruecolor(2, 2);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);
    if (! is_string($contents)) {
        throw new RuntimeException;
    }

    return $contents;
}

function createPublicDocumentOwner(string $email): string
{
    $id = OpaqueId::generate()->value();
    DB::table('users')->insert([
        'id' => $id,
        'name' => 'Public Media Owner',
        'email' => $email,
        'password' => 'not-used',
        'status' => 'active',
        'preferred_locale' => 'en',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('publishes sanitized service media only after a successful scan', function (): void {
    $ownerId = createPublicDocumentOwner('public-media@example.test');
    $uploadId = app(BeginUpload::class)->handle(new BeginUploadData($ownerId, DocumentPurpose::ServiceMedia));
    $document = app(StoreUpload::class)->handle(new StoreUploadData(
        $uploadId,
        $ownerId,
        'banner.png',
        'image/png',
        publicMediaPng(),
    ));
    $staged = DB::table('documents')->where('id', $document->id)->first();

    expect($staged?->disk)->toBe('private')
        ->and(Storage::disk('public')->allFiles())->toBe([]);

    $scanner = Mockery::mock(DocumentScanner::class);
    $scanner->shouldReceive('scan')->once()->andReturn(ScanResult::clean());
    app()->instance(DocumentScanner::class, $scanner);
    $clean = app(ScanDocument::class)->handle($document->id);
    $published = DB::table('documents')->where('id', $document->id)->first();

    expect($clean->status)->toBe(DocumentStatus::Clean)
        ->and($published?->disk)->toBe('public')
        ->and(Storage::disk('private')->allFiles())->toBe([])
        ->and(Storage::disk('public')->exists((string) $published?->storage_key))->toBeTrue();
});

it('attaches only clean public media owned by the caller', function (): void {
    $ownerA = createPublicDocumentOwner('public-owner-a@example.test');
    $ownerB = createPublicDocumentOwner('public-owner-b@example.test');
    $uploadId = app(BeginUpload::class)->handle(new BeginUploadData($ownerA, DocumentPurpose::ServiceMedia));
    $document = app(StoreUpload::class)->handle(new StoreUploadData(
        $uploadId,
        $ownerA,
        'banner.png',
        'image/png',
        publicMediaPng(),
    ));

    expect(fn () => DB::transaction(fn () => app(PublicDocuments::class)->assertCleanPublic(
        [$document->id],
        $ownerA,
        [DocumentPurpose::ServiceMedia],
    )))->toThrow(DocumentNotClean::class);

    $scanner = Mockery::mock(DocumentScanner::class);
    $scanner->shouldReceive('scan')->once()->andReturn(ScanResult::clean());
    app()->instance(DocumentScanner::class, $scanner);
    app(ScanDocument::class)->handle($document->id);

    expect(fn () => DB::transaction(fn () => app(PublicDocuments::class)->assertCleanPublic(
        [$document->id],
        $ownerB,
        [DocumentPurpose::ServiceMedia],
    )))->toThrow(DocumentAccessDenied::class);

    $attached = DB::transaction(fn () => app(PublicDocuments::class)->assertCleanPublic(
        [$document->id],
        $ownerA,
        [DocumentPurpose::ServiceMedia],
    ));

    expect($attached)->toHaveCount(1)
        ->and($attached[0]->status)->toBe(DocumentStatus::Attached)
        ->and((array) $attached[0])->not->toHaveKey('storageKey');

    $reattached = DB::transaction(fn () => app(PublicDocuments::class)->assertCleanPublic(
        [$document->id],
        $ownerA,
        [DocumentPurpose::ServiceMedia],
    ));

    expect($reattached)->toHaveCount(1)
        ->and($reattached[0]->status)->toBe(DocumentStatus::Attached);
});
