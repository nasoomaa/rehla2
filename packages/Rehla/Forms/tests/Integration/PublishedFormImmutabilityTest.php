<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Forms\Actions\CreateFormDraft;
use Rehla\Forms\Actions\PublishFormVersion;
use Rehla\Forms\Actions\UpdateFormDraft;
use Rehla\Forms\Contracts\FormsAuthorizer;
use Rehla\Forms\Data\FormFieldData;
use Rehla\Forms\Enums\FieldType;
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

function immutableFormsService(): string
{
    $id = OpaqueId::generate()->value();
    DB::table('services')->insert([
        'id' => $id, 'slug' => 'immutable-form-service-'.$id,
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

function immutableFormField(): FormFieldData
{
    return new FormFieldData(
        'answer', FieldType::ShortText, ['en' => 'Answer', 'ar' => 'الإجابة'], 1, true,
        ['en' => 'Enter the answer', 'ar' => 'أدخل الإجابة'], [], [],
    );
}

it('blocks direct updates and deletes of every published form version', function (): void {
    $actorId = OpaqueId::generate()->value();
    $draftId = app(CreateFormDraft::class)->handle(immutableFormsService(), null, $actorId, OpaqueId::generate()->value());
    app(UpdateFormDraft::class)->handle($draftId, [immutableFormField()], $actorId, OpaqueId::generate()->value());
    $published = app(PublishFormVersion::class)->handle($draftId, $actorId, OpaqueId::generate()->value());

    expect($published->checksum)->toBe('f486c658d8435c5c3511b122c624b8c561580d2e24571cbcc44bf257b1057760')
        ->and(fn () => DB::table('form_versions')->where('id', $published->id)->update(['checksum' => str_repeat('0', 64)]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('form_versions')->where('id', $published->id)->delete())
        ->toThrow(QueryException::class);
});
