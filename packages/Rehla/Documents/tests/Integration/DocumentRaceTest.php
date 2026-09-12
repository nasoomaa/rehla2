<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Documents\Actions\BeginUpload;
use Rehla\Documents\Actions\DeleteExpiredUploads;
use Rehla\Documents\Actions\ScanDocument;
use Rehla\Documents\Actions\StoreUpload;
use Rehla\Documents\Contracts\DocumentScanner;
use Rehla\Documents\Contracts\OwnedDocuments;
use Rehla\Documents\Data\BeginUploadData;
use Rehla\Documents\Data\DocumentReference;
use Rehla\Documents\Data\ScanResult;
use Rehla\Documents\Data\StoreUploadData;
use Rehla\Documents\Enums\DocumentPurpose;
use Rehla\Documents\Enums\DocumentStatus;
use Rehla\Documents\Exceptions\DocumentNotClean;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE documents, upload_sessions, users, roles, audit_entries RESTART IDENTITY CASCADE');
    Storage::fake('private');
});

function createRaceDocumentOwner(): string
{
    $id = OpaqueId::generate()->value();
    DB::table('users')->insert([
        'id' => $id,
        'name' => 'Race Owner',
        'email' => $id.'@example.test',
        'password' => 'not-used',
        'status' => 'active',
        'preferred_locale' => 'en',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function beginAndStoreRaceDocument(string $ownerId, string $contents = ''): DocumentReference
{
    $uploadId = app(BeginUpload::class)->handle(new BeginUploadData($ownerId, DocumentPurpose::PassportScan));

    return app(StoreUpload::class)->handle(new StoreUploadData(
        $uploadId,
        $ownerId,
        'race.pdf',
        'application/pdf',
        $contents !== '' ? $contents : "%PDF-1.4\n%%EOF\n",
    ));
}

function bindCleanRaceScanner(): void
{
    $scanner = Mockery::mock(DocumentScanner::class);
    $scanner->shouldReceive('scan')->andReturn(ScanResult::clean());
    app()->instance(DocumentScanner::class, $scanner);
}

it('does not let a stale scanner publish its result', function (): void {
    $ownerId = createRaceDocumentOwner();
    $document = beginAndStoreRaceDocument($ownerId);
    $scanner = Mockery::mock(DocumentScanner::class);
    $scanner->shouldReceive('scan')->once()->andReturnUsing(function () use ($document): ScanResult {
        DB::table('documents')->where('id', $document->id)->update([
            'scan_token' => OpaqueId::generate()->value(),
        ]);

        return ScanResult::clean();
    });
    app()->instance(DocumentScanner::class, $scanner);

    $result = app(ScanDocument::class)->handle($document->id);

    expect($result->status)->toBe(DocumentStatus::Quarantined)
        ->and(DB::table('documents')->where('id', $document->id)->value('status'))->toBe('quarantined');
});

it('never prunes an attached document', function (): void {
    $ownerId = createRaceDocumentOwner();
    $document = beginAndStoreRaceDocument($ownerId);
    bindCleanRaceScanner();
    app(ScanDocument::class)->handle($document->id);
    DB::transaction(fn () => app(OwnedDocuments::class)->assertCleanOwned(
        [$document->id],
        $ownerId,
        [DocumentPurpose::PassportScan],
    ));
    DB::table('documents')->where('id', $document->id)->update(['created_at' => now()->subDays(40)]);

    expect(app(DeleteExpiredUploads::class)->handle())->toBe(0)
        ->and(DB::table('documents')->where('id', $document->id)->value('status'))->toBe('attached');
});

it('prevents attachment after cleanup claims an orphan and fences a stale cleanup worker', function (): void {
    $ownerId = createRaceDocumentOwner();
    $document = beginAndStoreRaceDocument($ownerId);
    bindCleanRaceScanner();
    app(ScanDocument::class)->handle($document->id);
    DB::table('documents')->where('id', $document->id)->update(['created_at' => now()->subHours(25)]);

    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->once()->andReturnTrue();
    $disk->shouldReceive('delete')->once()->andReturnUsing(function () use ($document): bool {
        DB::table('documents')->where('id', $document->id)->update([
            'cleanup_claim_token' => OpaqueId::generate()->value(),
        ]);

        return true;
    });
    $filesystems = Mockery::mock(FilesystemManager::class);
    $filesystems->shouldReceive('disk')->once()->with('private')->andReturn($disk);
    app()->instance(FilesystemManager::class, $filesystems);

    expect(app(DeleteExpiredUploads::class)->handle())->toBe(0)
        ->and(DB::table('documents')->where('id', $document->id)->value('status'))->toBe('cleanup_claimed');

    expect(fn () => DB::transaction(fn () => app(OwnedDocuments::class)->assertCleanOwned(
        [$document->id],
        $ownerId,
        [DocumentPurpose::PassportScan],
    )))->toThrow(DocumentNotClean::class);
});

it('enforces exact orphan and rejected retention windows', function (
    DocumentStatus $status,
    int $age,
    string $unit,
    bool $eligible,
): void {
    $ownerId = createRaceDocumentOwner();
    $document = $status === DocumentStatus::Rejected
        ? beginAndStoreRaceDocument($ownerId, "%PDF-1.4\nbroken")
        : beginAndStoreRaceDocument($ownerId);

    if ($status === DocumentStatus::Clean) {
        bindCleanRaceScanner();
        app(ScanDocument::class)->handle($document->id);
    } elseif ($status === DocumentStatus::Rejected) {
        $scanner = Mockery::mock(DocumentScanner::class);
        $scanner->shouldNotReceive('scan');
        app()->instance(DocumentScanner::class, $scanner);
        app(ScanDocument::class)->handle($document->id);
    }

    $past = $unit === 'hours' ? now()->subHours($age) : now()->subDays($age);
    DB::table('documents')->where('id', $document->id)->update(
        $status === DocumentStatus::Rejected
            ? ['scanned_at' => $past]
            : ['created_at' => $past],
    );

    expect(app(DeleteExpiredUploads::class)->handle())->toBe($eligible ? 1 : 0)
        ->and(DB::table('documents')->where('id', $document->id)->value('status'))
        ->toBe($eligible ? 'purged' : $status->value);
})->with([
    'pending before 24 hours' => [DocumentStatus::PendingScan, 23, 'hours', false],
    'pending at 24 hours' => [DocumentStatus::PendingScan, 24, 'hours', true],
    'clean before 24 hours' => [DocumentStatus::Clean, 23, 'hours', false],
    'clean at 24 hours' => [DocumentStatus::Clean, 24, 'hours', true],
    'rejected before 30 days' => [DocumentStatus::Rejected, 29, 'days', false],
    'rejected at 30 days' => [DocumentStatus::Rejected, 30, 'days', true],
]);

it('treats a missing orphan blob as an idempotent cleanup success', function (): void {
    $ownerId = createRaceDocumentOwner();
    $document = beginAndStoreRaceDocument($ownerId);
    $key = DB::table('documents')->where('id', $document->id)->value('storage_key');
    if (! is_string($key)) {
        throw new RuntimeException('Orphan storage key was not found.');
    }
    DB::table('documents')->where('id', $document->id)->update(['created_at' => now()->subHours(25)]);
    Storage::disk('private')->delete($key);

    expect(app(DeleteExpiredUploads::class)->handle())->toBe(1)
        ->and(DB::table('documents')->where('id', $document->id)->value('status'))->toBe('purged');
});

it('leaves a recoverable cleanup claim when blob deletion fails', function (): void {
    $ownerId = createRaceDocumentOwner();
    $document = beginAndStoreRaceDocument($ownerId);
    DB::table('documents')->where('id', $document->id)->update(['created_at' => now()->subHours(25)]);

    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->once()->andReturnTrue();
    $disk->shouldReceive('delete')->once()->andReturnFalse();
    $filesystems = Mockery::mock(FilesystemManager::class);
    $filesystems->shouldReceive('disk')->once()->with('private')->andReturn($disk);
    app()->instance(FilesystemManager::class, $filesystems);

    expect(app(DeleteExpiredUploads::class)->handle())->toBe(0)
        ->and(DB::table('documents')->where('id', $document->id)->value('status'))->toBe('cleanup_claimed')
        ->and(DB::table('documents')->where('id', $document->id)->value('cleanup_claim_token'))->toBeUuid();
});
