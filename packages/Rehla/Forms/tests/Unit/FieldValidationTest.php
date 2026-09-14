<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Forms\Actions\CreateFormDraft;
use Rehla\Forms\Actions\PublishFormVersion;
use Rehla\Forms\Actions\UpdateFormDraft;
use Rehla\Forms\Contracts\FormsAuthorizer;
use Rehla\Forms\Contracts\FormSubmissionValidator;
use Rehla\Forms\Data\FormFieldData;
use Rehla\Forms\Data\ValidatedSubmission;
use Rehla\Forms\Enums\FieldType;
use Rehla\Forms\Exceptions\FormValidationFailed;
use Tests\TestCase;

uses(TestCase::class);

dataset('form field types', [
    'short_text' => [FieldType::ShortText, ['min' => 3, 'max' => 50], 'Fatima Hassan', 'AB'],
    'long_text' => [FieldType::LongText, ['max' => 50], 'Khartoum address', str_repeat('x', 51)],
    'email' => [FieldType::Email, [], 'applicant@example.com', 'not-an-email'],
    'phone' => [FieldType::Phone, ['country_code' => 'SD'], '+249912345678', '12345'],
    'number' => [FieldType::Number, ['min' => 1, 'max' => 10], 3, 'three'],
    'date' => [FieldType::Date, ['format' => 'YYYY-MM-DD'], '1995-10-25', '25/10/1995'],
    'dropdown' => [FieldType::Dropdown, [], 'married', 'other'],
    'radio' => [FieldType::Radio, [], 'single', 'unspecified'],
    'checkbox' => [FieldType::Checkbox, [], true, false],
    'file_upload' => [FieldType::FileUpload, ['document_purpose' => 'supporting_document', 'max_files' => 1, 'mime' => ['application/pdf']], 'validDocumentId', 'bad'],
    'image_upload' => [FieldType::ImageUpload, ['document_purpose' => 'applicant_photo', 'max_files' => 1, 'mime' => ['image/jpeg', 'image/png']], 'validDocumentId', 'bad'],
]);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE form_versions, form_drafts, services, audit_entries RESTART IDENTITY CASCADE');
    app()->instance(FormsAuthorizer::class, new class implements FormsAuthorizer
    {
        public function assertCanDraft(string $actorId): void
        {
            OpaqueId::fromString($actorId);
        }

        public function assertCanPublish(string $actorId): void
        {
            OpaqueId::fromString($actorId);
        }
    });
});

/** @return list<array{value: string, label: array{en: string, ar: string}}> */
function formChoiceOptions(): array
{
    return [
        ['value' => 'single', 'label' => ['en' => 'Single', 'ar' => 'أعزب']],
        ['value' => 'married', 'label' => ['en' => 'Married', 'ar' => 'متزوج']],
    ];
}

function formsService(): string
{
    $id = OpaqueId::generate()->value();
    DB::table('services')->insert([
        'id' => $id,
        'slug' => 'form-service-'.$id,
        'name_en' => 'Form service', 'name_ar' => 'خدمة نموذج',
        'short_description_en' => 'Short', 'short_description_ar' => 'مختصر',
        'detailed_description_en' => 'Detailed', 'detailed_description_ar' => 'مفصل',
        'expected_duration_en' => 'One day', 'expected_duration_ar' => 'يوم واحد',
        'notes_en' => '', 'notes_ar' => '',
        'current_price_minor' => 100, 'currency' => 'SDG', 'price_version' => 1,
        'status' => 'draft', 'sort_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

/**
 * @param  array<string, mixed>  $validation
 * @param  list<array{value: string, label: array{en: string, ar: string}}>  $options
 */
function formField(
    FieldType $type,
    array $validation = [],
    array $options = [],
    string $key = 'answer',
    bool $required = true,
    int $order = 1,
): FormFieldData {
    return new FormFieldData(
        key: $key,
        type: $type,
        label: ['en' => 'Answer', 'ar' => 'الإجابة'],
        order: $order,
        required: $required,
        helper: ['en' => 'Enter the answer', 'ar' => 'أدخل الإجابة'],
        options: $options,
        validation: $validation,
    );
}

function publishFormField(FormFieldData $field): string
{
    $actorId = OpaqueId::generate()->value();
    $draftId = app(CreateFormDraft::class)->handle(formsService(), null, $actorId, OpaqueId::generate()->value());
    app(UpdateFormDraft::class)->handle($draftId, [$field], $actorId, OpaqueId::generate()->value());

    return app(PublishFormVersion::class)->handle($draftId, $actorId, OpaqueId::generate()->value())->id;
}

it('round-trips and validates every supported field type', function (
    FieldType $type,
    array $validation,
    mixed $valid,
    mixed $invalid,
): void {
    $options = $type->isChoice() ? formChoiceOptions() : [];
    $versionId = publishFormField(formField($type, $validation, $options));
    if ($valid === 'validDocumentId') {
        $valid = OpaqueId::generate()->value();
    }

    expect(app(FormSubmissionValidator::class)->validate($versionId, ['answer' => $valid]))
        ->toBeInstanceOf(ValidatedSubmission::class)
        ->and(fn () => app(FormSubmissionValidator::class)->validate($versionId, ['answer' => $invalid]))
        ->toThrow(FormValidationFailed::class);
})->with('form field types');

it('rejects phantom fields and permits an omitted optional field', function (): void {
    $versionId = publishFormField(formField(FieldType::ShortText, required: false));

    expect(app(FormSubmissionValidator::class)->validate($versionId, [])->answers)->toBe([])
        ->and(fn () => app(FormSubmissionValidator::class)->validate($versionId, ['phantom' => 'value']))
        ->toThrow(FormValidationFailed::class);
});

it('rejects incomplete bilingual definitions duplicate options and unsafe validation rules', function (): void {
    expect(fn () => new FormFieldData('name', FieldType::ShortText, ['en' => 'Name', 'ar' => ''], 1, true, ['en' => '', 'ar' => ''], [], []))
        ->toThrow(FormValidationFailed::class)
        ->and(fn () => formField(FieldType::Dropdown, options: [formChoiceOptions()[0], formChoiceOptions()[0]]))
        ->toThrow(FormValidationFailed::class)
        ->and(fn () => formField(FieldType::ShortText, ['regex' => '/.*/e']))
        ->toThrow(FormValidationFailed::class);
});

it('returns document classifications without querying the documents package', function (): void {
    $field = formField(FieldType::FileUpload, [
        'document_purpose' => 'supporting_document',
        'max_files' => 2,
        'mime' => ['application/pdf'],
    ]);
    $versionId = publishFormField($field);
    $documentIds = [OpaqueId::generate()->value(), OpaqueId::generate()->value()];

    $validated = app(FormSubmissionValidator::class)->validate($versionId, ['answer' => $documentIds]);

    expect($validated->documentReferences)->toBe([
        ['field_key' => 'answer', 'document_id' => $documentIds[0], 'purpose' => 'supporting_document', 'mime' => ['application/pdf']],
        ['field_key' => 'answer', 'document_id' => $documentIds[1], 'purpose' => 'supporting_document', 'mime' => ['application/pdf']],
    ]);
});

it('rejects duplicate field keys and display orders', function (): void {
    $actorId = OpaqueId::generate()->value();
    $draftId = app(CreateFormDraft::class)->handle(formsService(), null, $actorId, OpaqueId::generate()->value());

    expect(fn () => app(UpdateFormDraft::class)->handle(
        $draftId,
        [formField(FieldType::ShortText, key: 'same'), formField(FieldType::Email, key: 'same', order: 2)],
        $actorId,
        OpaqueId::generate()->value(),
    ))->toThrow(FormValidationFailed::class);

    expect(fn () => app(UpdateFormDraft::class)->handle(
        $draftId,
        [formField(FieldType::ShortText, key: 'first'), formField(FieldType::Email, key: 'second')],
        $actorId,
        OpaqueId::generate()->value(),
    ))->toThrow(FormValidationFailed::class);
});
