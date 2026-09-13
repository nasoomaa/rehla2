<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Rehla\Catalog\Actions\CreateService;
use Rehla\Catalog\Actions\PublishFulfillmentPolicy;
use Rehla\Catalog\Actions\SaveFulfillmentPolicyDraft;
use Rehla\Catalog\Contracts\CatalogAuthorizer;
use Rehla\Catalog\Contracts\PublishedFulfillmentPolicyReader;
use Rehla\Catalog\Data\FulfillmentPolicyData;
use Rehla\Core\Identifiers\OpaqueId;
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

function catalogPolicy(): FulfillmentPolicyData
{
    return FulfillmentPolicyData::draft([
        'transitions' => [
            ['from' => 'received', 'to' => 'under_review', 'actor' => 'staff', 'requires_reason' => false, 'requires_note' => false],
            ['from' => 'under_review', 'to' => 'action_required', 'actor' => 'staff', 'requires_reason' => true, 'requires_note' => true],
            ['from' => 'action_required', 'to' => 'action_received', 'actor' => 'customer', 'requires_reason' => false, 'requires_note' => true],
            ['from' => 'action_received', 'to' => 'completed', 'actor' => 'staff', 'requires_reason' => false, 'requires_note' => false],
        ],
        'completion_requires_document' => true,
        'guidance' => [
            'staff' => ['en' => 'Follow the service checklist.', 'ar' => 'اتبع قائمة تحقق الخدمة.'],
            'customer' => ['en' => 'Respond to requested actions.', 'ar' => 'استجب للإجراءات المطلوبة.'],
        ],
    ]);
}

it('publishes sequential immutable fulfillment policy versions with canonical checksums', function (): void {
    $actorId = catalogActor();
    $service = app(CreateService::class)->handle(catalogServiceData($actorId, 'policy-service'), $actorId, OpaqueId::generate()->value());

    app(SaveFulfillmentPolicyDraft::class)->handle($service->id, catalogPolicy(), $actorId, OpaqueId::generate()->value());
    $first = app(PublishFulfillmentPolicy::class)->handle($service->id, $actorId, OpaqueId::generate()->value());
    app(SaveFulfillmentPolicyDraft::class)->handle($service->id, catalogPolicy(), $actorId, OpaqueId::generate()->value());
    $second = app(PublishFulfillmentPolicy::class)->handle($service->id, $actorId, OpaqueId::generate()->value());

    expect($first->version)->toBe(1)
        ->and($second->version)->toBe(2)
        ->and($second->checksum)->toBe($first->checksum)
        ->and(app(PublishedFulfillmentPolicyReader::class)->forService($service->id)->id)->toBe($second->id)
        ->and(fn () => DB::table('fulfillment_policy_versions')->where('id', $first->id)->update(['version' => 99]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('fulfillment_policy_versions')->where('id', $first->id)->delete())
        ->toThrow(QueryException::class);
});
