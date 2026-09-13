<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Rehla\Catalog\Actions\CreateService;
use Rehla\Catalog\Actions\DeactivateService;
use Rehla\Catalog\Actions\PublishService;
use Rehla\Catalog\Actions\ReorderServices;
use Rehla\Catalog\Contracts\CatalogAuthorizer;
use Rehla\Catalog\Contracts\ServiceCatalog;
use Rehla\Catalog\Data\ServiceData;
use Rehla\Catalog\Data\ServiceMediaData;
use Rehla\Catalog\Data\ServiceRequirementData;
use Rehla\Catalog\Exceptions\ServiceNotPublishable;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Documents\Enums\DocumentPurpose;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE service_media, service_requirements, service_price_history, fulfillment_policy_versions, fulfillment_policy_drafts, services, documents, users, audit_entries RESTART IDENTITY CASCADE');
    app()->instance(CatalogAuthorizer::class, new class implements CatalogAuthorizer
    {
        public function assertCanManage(string $actorId): void
        {
            OpaqueId::fromString($actorId);
        }
    });
});

function catalogActor(): string
{
    return OpaqueId::generate()->value();
}

function catalogMedia(string $ownerId): string
{
    DB::table('users')->insertOrIgnore([
        'id' => $ownerId,
        'name' => 'Catalog Staff',
        'email' => $ownerId.'@example.test',
        'password' => 'unused',
        'status' => 'active',
        'preferred_locale' => 'ar',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $uploadId = OpaqueId::generate()->value();
    DB::table('upload_sessions')->insert([
        'id' => $uploadId,
        'owner_id' => $ownerId,
        'purpose' => DocumentPurpose::ServiceMedia->value,
        'expires_at' => now()->addHour(),
        'claimed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $documentId = OpaqueId::generate()->value();
    DB::table('documents')->insert([
        'id' => $documentId,
        'upload_session_id' => $uploadId,
        'owner_id' => $ownerId,
        'purpose' => DocumentPurpose::ServiceMedia->value,
        'status' => 'clean',
        'original_name' => 'service.png',
        'declared_mime' => 'image/png',
        'detected_mime' => 'image/png',
        'size_bytes' => 128,
        'sha256' => str_repeat('a', 64),
        'disk' => 'public',
        'storage_key' => 'service-media/'.$documentId.'.png',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $documentId;
}

function catalogServiceData(string $ownerId, string $slug, int $priceMinor = 250000): ServiceData
{
    return new ServiceData(
        slug: $slug,
        name: ['en' => 'Travel service', 'ar' => 'خدمة سفر'],
        shortDescription: ['en' => 'A short description', 'ar' => 'وصف مختصر'],
        detailedDescription: ['en' => 'A complete service description', 'ar' => 'وصف كامل للخدمة'],
        expectedDuration: ['en' => 'Three business days', 'ar' => 'ثلاثة أيام عمل'],
        notes: ['en' => 'Bring originals', 'ar' => 'أحضر المستندات الأصلية'],
        priceMinor: $priceMinor,
        requirements: [new ServiceRequirementData(['en' => 'Valid passport', 'ar' => 'جواز سفر ساري'], 1)],
        media: [new ServiceMediaData(catalogMedia($ownerId), ['en' => 'Service cover', 'ar' => 'غلاف الخدمة'], 1)],
        sortOrder: 1,
    );
}

it('publishes complete services and lists only active services in display order', function (): void {
    $actorId = catalogActor();
    $first = app(CreateService::class)->handle(catalogServiceData($actorId, 'first-service'), $actorId, OpaqueId::generate()->value());
    $second = app(CreateService::class)->handle(catalogServiceData($actorId, 'second-service'), $actorId, OpaqueId::generate()->value());

    app(PublishService::class)->handle($first->id, $actorId, OpaqueId::generate()->value());
    app(PublishService::class)->handle($second->id, $actorId, OpaqueId::generate()->value());
    app(ReorderServices::class)->handle([$second->id, $first->id], $actorId, OpaqueId::generate()->value());

    expect(array_map(fn ($service): string => $service->id, app(ServiceCatalog::class)->listPublished(1, 20)))
        ->toBe([$second->id, $first->id]);

    app(DeactivateService::class)->handle($second->id, $actorId, OpaqueId::generate()->value());

    expect(array_map(fn ($service): string => $service->id, app(ServiceCatalog::class)->listPublished(1, 20)))
        ->toBe([$first->id])
        ->and(app(ServiceCatalog::class)->getById($second->id)->id)->toBe($second->id);
});

it('refuses to publish a service whose public media is no longer attachable', function (): void {
    $actorId = catalogActor();
    $service = app(CreateService::class)->handle(catalogServiceData($actorId, 'invalid-media'), $actorId, OpaqueId::generate()->value());
    DB::table('documents')->where('id', $service->media[0]->documentId)->update([
        'status' => 'rejected',
        'disk' => 'private',
        'rejection_code' => 'document.verification_failed',
    ]);

    expect(fn () => app(PublishService::class)->handle($service->id, $actorId, OpaqueId::generate()->value()))
        ->toThrow(ServiceNotPublishable::class);
});

it('denies management before any catalog or document write', function (): void {
    $actorId = catalogActor();
    $data = catalogServiceData($actorId, 'denied-service');
    app()->instance(CatalogAuthorizer::class, new class implements CatalogAuthorizer
    {
        public function assertCanManage(string $actorId): void
        {
            throw new RuntimeException('denied');
        }
    });

    expect(fn () => app(CreateService::class)->handle($data, $actorId, OpaqueId::generate()->value()))
        ->toThrow(RuntimeException::class)
        ->and(DB::table('services')->count())->toBe(0)
        ->and(DB::table('documents')->where('id', $data->media[0]->documentId)->value('status'))->toBe('clean');
});
