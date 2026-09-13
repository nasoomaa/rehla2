<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Rehla\Catalog\Actions\ChangeServicePrice;
use Rehla\Catalog\Actions\CreateService;
use Rehla\Catalog\Actions\DeactivateService;
use Rehla\Catalog\Actions\PublishService;
use Rehla\Catalog\Contracts\CatalogAuthorizer;
use Rehla\Catalog\Contracts\ServiceQuoteReader;
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

it('tracks price history atomically and makes a disabled service unavailable', function (): void {
    $actorId = catalogActor();
    $service = app(CreateService::class)->handle(catalogServiceData($actorId, 'priced-service'), $actorId, OpaqueId::generate()->value());
    app(PublishService::class)->handle($service->id, $actorId, OpaqueId::generate()->value());

    $quote = app(ChangeServicePrice::class)->handle($service->id, 300000, $actorId, OpaqueId::generate()->value());

    expect($quote->priceMinor)->toBe(300000)
        ->and($quote->quoteVersion)->toBe(2)
        ->and(DB::table('service_price_history')->where('service_id', $service->id)->orderBy('version')->pluck('price_minor')->all())
        ->toBe([250000, 300000])
        ->and(DB::table('audit_entries')->where('subject_id', $service->id)->where('action', 'service.price_changed')->count())->toBe(1);

    app(DeactivateService::class)->handle($service->id, $actorId, OpaqueId::generate()->value());

    expect(app(ServiceQuoteReader::class)->currentQuote($service->id)->available)->toBeFalse();
});

it('protects price history from update and delete at the database level', function (): void {
    $actorId = catalogActor();
    $service = app(CreateService::class)->handle(catalogServiceData($actorId, 'immutable-price'), $actorId, OpaqueId::generate()->value());

    expect(fn () => DB::table('service_price_history')->where('service_id', $service->id)->update(['price_minor' => 1]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('service_price_history')->where('service_id', $service->id)->delete())
        ->toThrow(QueryException::class);
});
