<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rehla\Core\Errors\ProblemCode;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Documents\Actions\BeginUpload;
use Rehla\Documents\Actions\ScanDocument;
use Rehla\Documents\Actions\StoreUpload;
use Rehla\Documents\Contracts\DocumentDownloadAuthorizer;
use Rehla\Documents\Contracts\DocumentScanner;
use Rehla\Documents\Contracts\OwnedDocuments;
use Rehla\Documents\Data\BeginUploadData;
use Rehla\Documents\Data\DocumentReference;
use Rehla\Documents\Data\ScanResult;
use Rehla\Documents\Data\StoreUploadData;
use Rehla\Documents\Enums\DocumentPurpose;
use Rehla\Documents\Enums\DocumentStatus;
use Rehla\Documents\Exceptions\DocumentAccessDenied;
use Rehla\Documents\Exceptions\DocumentNotClean;
use Rehla\Documents\Exceptions\InvalidDocumentUpload;
use Rehla\Identity\Data\ActorData;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE documents, upload_sessions, users, roles, audit_entries RESTART IDENTITY CASCADE');
    Storage::fake('private');
});

function createDocumentOwner(string $email = 'document-owner@example.test'): string
{
    $id = OpaqueId::generate()->value();
    DB::table('users')->insert([
        'id' => $id,
        'name' => 'Document Owner',
        'email' => $email,
        'password' => 'not-used-in-document-tests',
        'status' => 'active',
        'preferred_locale' => 'en',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function validDocumentPdf(): string
{
    return "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
}

function beginAndStoreDocument(
    string $ownerId,
    DocumentPurpose $purpose = DocumentPurpose::PassportScan,
    string $name = 'passport.pdf',
    string $mime = 'application/pdf',
    ?string $contents = null,
): DocumentReference {
    $uploadId = app(BeginUpload::class)->handle(new BeginUploadData($ownerId, $purpose));

    return app(StoreUpload::class)->handle(new StoreUploadData(
        uploadSessionId: $uploadId,
        ownerId: $ownerId,
        originalName: $name,
        declaredMime: $mime,
        contents: $contents ?? validDocumentPdf(),
    ));
}

function bindCleanDocumentScanner(): void
{
    $scanner = Mockery::mock(DocumentScanner::class);
    $scanner->shouldReceive('scan')->andReturn(ScanResult::clean());
    app()->instance(DocumentScanner::class, $scanner);
}

it('stores privately and attaches only a clean document owned by the account', function (): void {
    $ownerA = createDocumentOwner('owner-a@example.test');
    $ownerB = createDocumentOwner('owner-b@example.test');
    $document = beginAndStoreDocument($ownerA);
    $row = DB::table('documents')->where('id', $document->id)->first();
    if ($row === null) {
        throw new RuntimeException('Stored document row was not found.');
    }

    expect($document->status)->toBe(DocumentStatus::PendingScan)
        ->and($row->disk)->toBe('private')
        ->and($row->storage_key)->not->toContain('passport')
        ->and($row->storage_key)->not->toContain($ownerA)
        ->and(Storage::disk('private')->exists($row->storage_key))->toBeTrue();

    expect(fn () => DB::transaction(
        fn () => app(OwnedDocuments::class)->assertCleanOwned(
            [$document->id],
            $ownerA,
            [DocumentPurpose::PassportScan],
        ),
    ))->toThrow(DocumentNotClean::class);

    bindCleanDocumentScanner();
    $clean = app(ScanDocument::class)->handle($document->id);
    expect($clean->status)->toBe(DocumentStatus::Clean);

    expect(fn () => DB::transaction(
        fn () => app(OwnedDocuments::class)->assertCleanOwned(
            [$document->id],
            $ownerB,
            [DocumentPurpose::PassportScan],
        ),
    ))->toThrow(DocumentAccessDenied::class);

    $attached = DB::transaction(
        fn () => app(OwnedDocuments::class)->assertCleanOwned(
            [$document->id],
            $ownerA,
            [DocumentPurpose::PassportScan],
        ),
    );

    expect($attached)->toHaveCount(1)
        ->and($attached[0]->status)->toBe(DocumentStatus::Attached)
        ->and(DB::table('documents')->where('id', $document->id)->value('status'))->toBe('attached')
        ->and((array) $attached[0])->not->toHaveKey('storageKey');
});

it('rejects mismatched magic bytes corrupt PDFs and polyglots', function (
    string $name,
    string $mime,
    string $contents,
    string $rejectionCode,
): void {
    $ownerId = createDocumentOwner();
    $document = beginAndStoreDocument($ownerId, name: $name, mime: $mime, contents: $contents);
    $scanner = Mockery::mock(DocumentScanner::class);
    $scanner->shouldNotReceive('scan');
    app()->instance(DocumentScanner::class, $scanner);

    $result = app(ScanDocument::class)->handle($document->id);

    expect($result->status)->toBe(DocumentStatus::Rejected)
        ->and(DB::table('documents')->where('id', $document->id)->value('rejection_code'))->toBe($rejectionCode)
        ->and(DB::table('documents')->where('id', $document->id)->value('storage_key'))->toStartWith('quarantine/');
})->with([
    'mime mismatch' => ['image.png', 'image/png', validDocumentPdf(), 'mime_mismatch'],
    'corrupt pdf' => ['broken.pdf', 'application/pdf', "%PDF-1.4\nno eof", 'corrupt_file'],
    'polyglot' => ['polyglot.pdf', 'application/pdf', validDocumentPdf()."PK\x03\x04hidden", 'polyglot'],
]);

it('rejects an upload larger than its purpose quota without persisting a blob', function (): void {
    $ownerId = createDocumentOwner();
    $uploadId = app(BeginUpload::class)->handle(new BeginUploadData($ownerId, DocumentPurpose::ApplicantPhoto));

    try {
        app(StoreUpload::class)->handle(new StoreUploadData(
            uploadSessionId: $uploadId,
            ownerId: $ownerId,
            originalName: 'large.jpg',
            declaredMime: 'image/jpeg',
            contents: str_repeat('x', 10_485_761),
        ));
        $exception = null;
    } catch (InvalidDocumentUpload $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(InvalidDocumentUpload::class)
        ->and($exception?->problemCode)->toBe(ProblemCode::DocumentFileTooLarge)
        ->and(DB::table('documents')->count())->toBe(0)
        ->and(Storage::disk('private')->allFiles())->toBe([]);
});

it('strips image metadata before marking an image clean', function (): void {
    $ownerId = createDocumentOwner();
    $image = imagecreatetruecolor(2, 2);
    ob_start();
    imagejpeg($image);
    $jpeg = ob_get_clean();
    imagedestroy($image);
    if (! is_string($jpeg)) {
        throw new RuntimeException('Could not create JPEG fixture.');
    }
    $withMetadata = substr($jpeg, 0, 2)."\xFF\xE1\x00\x12Exif\0\0GPS-SECRET".substr($jpeg, 2);
    $document = beginAndStoreDocument(
        $ownerId,
        DocumentPurpose::ApplicantPhoto,
        'photo.jpg',
        'image/jpeg',
        $withMetadata,
    );
    bindCleanDocumentScanner();

    app(ScanDocument::class)->handle($document->id);
    $key = DB::table('documents')->where('id', $document->id)->value('storage_key');
    $sanitized = Storage::disk('private')->get($key);
    if (! is_string($sanitized)) {
        throw new RuntimeException('Sanitized image was not stored.');
    }

    expect($sanitized)->not->toContain('GPS-SECRET')
        ->and(getimagesizefromstring($sanitized))->not->toBeFalse();
});

it('keeps scanner outages quarantined and records malware rejection safely', function (): void {
    $ownerId = createDocumentOwner();
    $unavailable = beginAndStoreDocument($ownerId);
    $scanner = Mockery::mock(DocumentScanner::class);
    $scanner->shouldReceive('scan')->once()->andThrow(new RuntimeException('scanner unavailable'));
    app()->instance(DocumentScanner::class, $scanner);

    expect(fn () => app(ScanDocument::class)->handle($unavailable->id))
        ->toThrow(RuntimeException::class, 'scanner unavailable')
        ->and(DB::table('documents')->where('id', $unavailable->id)->value('status'))->toBe('quarantined');

    $malware = beginAndStoreDocument($ownerId, name: 'second.pdf');
    $infectedScanner = Mockery::mock(DocumentScanner::class);
    $infectedScanner->shouldReceive('scan')->once()->andReturn(ScanResult::rejected('malware'));
    app()->instance(DocumentScanner::class, $infectedScanner);
    $result = app(ScanDocument::class)->handle($malware->id);

    expect($result->status)->toBe(DocumentStatus::Rejected)
        ->and(DB::table('documents')->where('id', $malware->id)->value('storage_key'))->toStartWith('quarantine/')
        ->and(DB::table('audit_entries')->where('subject_id', $malware->id)->value('action'))->toBe('document.scan_rejected');
});

it('streams an owned clean document with private security headers and no path disclosure', function (): void {
    $ownerId = createDocumentOwner();
    $otherId = createDocumentOwner('other-download@example.test');
    $document = beginAndStoreDocument($ownerId);
    bindCleanDocumentScanner();
    app(ScanDocument::class)->handle($document->id);

    expect(fn () => app(DocumentDownloadAuthorizer::class)->authorize(
        $document->id,
        new ActorData($otherId, 'customer'),
    ))->toThrow(DocumentAccessDenied::class);

    $response = app(DocumentDownloadAuthorizer::class)->authorize(
        $document->id,
        new ActorData($ownerId, 'customer'),
    );
    ob_start();
    $response->sendContent();
    $contents = ob_get_clean();

    expect($response->headers->get('content-disposition'))->toStartWith('attachment;')
        ->and($response->headers->get('x-content-type-options'))->toBe('nosniff')
        ->and($response->headers->get('cache-control'))->toContain('no-store')
        ->and($response->headers->get('content-security-policy'))->toBe("default-src 'none'")
        ->and($contents)->toBe(validDocumentPdf())
        ->and(json_encode($response, JSON_THROW_ON_ERROR))->not->toContain('storage_key');
});

it('requires the sensitive document ability for staff downloads', function (): void {
    $ownerId = createDocumentOwner();
    $staffId = createDocumentOwner('document-staff@example.test');
    DB::table('staff_profiles')->insert([
        'user_id' => $staffId,
        'department' => 'operations',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $document = beginAndStoreDocument($ownerId);
    bindCleanDocumentScanner();
    app(ScanDocument::class)->handle($document->id);
    $actor = new ActorData($staffId, 'staff');

    expect(fn () => app(DocumentDownloadAuthorizer::class)->authorize($document->id, $actor))
        ->toThrow(DocumentAccessDenied::class);

    $roleId = OpaqueId::generate()->value();
    DB::table('roles')->insert([
        'id' => $roleId,
        'name' => 'document-reviewer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $abilityId = DB::table('abilities')->where('name', 'documents.view_sensitive')->value('id');
    DB::table('role_ability')->insert(['role_id' => $roleId, 'ability_id' => $abilityId]);
    DB::table('user_role')->insert(['user_id' => $staffId, 'role_id' => $roleId]);

    expect(app(DocumentDownloadAuthorizer::class)->authorize($document->id, $actor)->getStatusCode())->toBe(200);
});
