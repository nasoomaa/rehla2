<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Forms\Actions\CreateFormDraft;
use Rehla\Forms\Actions\PublishFormVersion;
use Rehla\Forms\Actions\UpdateFormDraft;
use Rehla\Forms\Contracts\FormsAuthorizer;
use Rehla\Forms\Contracts\FormSubmissionValidator;
use Rehla\Forms\Contracts\PublishedFormReader;
use Rehla\Forms\Data\FormFieldData;
use Rehla\Forms\Enums\FieldType;
use Rehla\Forms\Exceptions\FormValidationFailed;
use Rehla\Forms\Exceptions\FormVersionOutdated;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE form_versions, form_drafts, services, audit_entries RESTART IDENTITY CASCADE');
    app()->instance(FormsAuthorizer::class, new class implements FormsAuthorizer
    {
        public function assertCanDraft(string $actorId): void {}

        public function assertCanPublish(string $actorId): void {}
    });
});

function publishingService(): string
{
    $id = OpaqueId::generate()->value();
    DB::table('services')->insert([
        'id' => $id, 'slug' => 'publishing-service-'.$id,
        'name_en' => 'Form service', 'name_ar' => 'خدمة نموذج',
        'short_description_en' => 'Short', 'short_description_ar' => 'مختصر',
        'detailed_description_en' => 'Detailed', 'detailed_description_ar' => 'مفصل',
        'expected_duration_en' => 'One day', 'expected_duration_ar' => 'يوم واحد',
        'notes_en' => '', 'notes_ar' => '', 'current_price_minor' => 100,
        'currency' => 'SDG', 'price_version' => 1, 'status' => 'draft', 'sort_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

function publishingField(FieldType $type, string $key): FormFieldData
{
    return new FormFieldData(
        $key, $type, ['en' => 'Answer', 'ar' => 'الإجابة'], 1, true,
        ['en' => 'Enter the answer', 'ar' => 'أدخل الإجابة'], [], [],
    );
}

it('keeps drafts private and publishes sequential current versions without changing history', function (): void {
    $actorId = OpaqueId::generate()->value();
    $serviceId = publishingService();
    $draftId = app(CreateFormDraft::class)->handle($serviceId, null, $actorId, OpaqueId::generate()->value());
    app(UpdateFormDraft::class)->handle($draftId, [publishingField(FieldType::ShortText, 'name')], $actorId, OpaqueId::generate()->value());

    expect(fn () => app(PublishedFormReader::class)->forService($serviceId))->toThrow(RuntimeException::class);
    $first = app(PublishFormVersion::class)->handle($draftId, $actorId, OpaqueId::generate()->value());
    app(UpdateFormDraft::class)->handle($draftId, [publishingField(FieldType::Email, 'email')], $actorId, OpaqueId::generate()->value());
    $second = app(PublishFormVersion::class)->handle($draftId, $actorId, OpaqueId::generate()->value());

    expect($first->version)->toBe(1)
        ->and($second->version)->toBe(2)
        ->and($second->checksum)->not->toBe($first->checksum)
        ->and(app(PublishedFormReader::class)->forService($serviceId)->id)->toBe($second->id)
        ->and(DB::table('form_versions')->where('id', $first->id)->value('checksum'))->toBe($first->checksum)
        ->and(DB::table('audit_entries')->where('action', 'form.version_published')->count())->toBe(2);

    expect(fn () => app(FormSubmissionValidator::class)->validate($first->id, ['name' => 'Fatima']))
        ->toThrow(FormVersionOutdated::class);
});

it('checks authorization before creating a draft', function (): void {
    app()->instance(FormsAuthorizer::class, new class implements FormsAuthorizer
    {
        public function assertCanDraft(string $actorId): void
        {
            throw new RuntimeException('denied');
        }

        public function assertCanPublish(string $actorId): void {}
    });

    expect(fn () => app(CreateFormDraft::class)->handle(publishingService(), null, OpaqueId::generate()->value(), OpaqueId::generate()->value()))
        ->toThrow(RuntimeException::class)
        ->and(DB::table('form_drafts')->count())->toBe(0);
});

it('maps a second active draft for one service to a form validation failure', function (): void {
    $actorId = OpaqueId::generate()->value();
    $serviceId = publishingService();
    app(CreateFormDraft::class)->handle($serviceId, null, $actorId, OpaqueId::generate()->value());

    expect(fn () => app(CreateFormDraft::class)->handle($serviceId, null, $actorId, OpaqueId::generate()->value()))
        ->toThrow(FormValidationFailed::class);
});
