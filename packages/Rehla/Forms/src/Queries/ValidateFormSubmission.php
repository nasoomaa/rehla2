<?php

declare(strict_types=1);

namespace Rehla\Forms\Queries;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Forms\Contracts\FormSubmissionValidator;
use Rehla\Forms\Data\FormFieldData;
use Rehla\Forms\Data\ValidatedSubmission;
use Rehla\Forms\Enums\FieldType;
use Rehla\Forms\Exceptions\FormNotFound;
use Rehla\Forms\Exceptions\FormValidationFailed;
use Rehla\Forms\Exceptions\FormVersionOutdated;

final readonly class ValidateFormSubmission implements FormSubmissionValidator
{
    public function __construct(private GetPublishedForm $forms) {}

    public function validate(string $formVersionId, array $answers): ValidatedSubmission
    {
        OpaqueId::fromString($formVersionId);
        $row = DB::table('form_versions')->where('id', $formVersionId)->first();
        if ($row === null) {
            throw new FormNotFound;
        }
        $published = $this->forms->hydrate($row);
        $currentId = DB::table('form_drafts')->where('service_id', $published->serviceId)->value('current_version_id');
        if (! is_string($currentId)) {
            throw new FormNotFound;
        }
        if (! hash_equals($currentId, $formVersionId)) {
            throw new FormVersionOutdated($currentId);
        }

        $declared = array_fill_keys(array_map(static fn (FormFieldData $field): string => $field->key, $published->fields), true);
        $errors = [];
        foreach (array_keys($answers) as $key) {
            if (! is_string($key) || ! isset($declared[$key])) {
                $errors[is_string($key) ? $key : 'schema'][] = 'form.undeclared_field_rejected';
            }
        }

        $normalized = [];
        $documents = [];
        foreach ($published->fields as $field) {
            $present = array_key_exists($field->key, $answers) && $answers[$field->key] !== null && $answers[$field->key] !== '';
            if (! $present) {
                if ($field->required) {
                    $errors[$field->key][] = 'form.required';
                }

                continue;
            }
            $value = $answers[$field->key];
            $error = $this->validateValue($field, $value);
            if ($error !== null) {
                $errors[$field->key][] = $error;

                continue;
            }
            $normalized[$field->key] = $this->normalize($field, $value);
            if ($field->type->isDocument()) {
                foreach ($this->documentIds($field, $value) as $documentId) {
                    $documents[] = [
                        'field_key' => $field->key,
                        'document_id' => $documentId,
                        'purpose' => (string) $field->validation['document_purpose'],
                        'mime' => array_values($field->validation['mime']),
                    ];
                }
            }
        }
        if ($errors !== []) {
            throw new FormValidationFailed($errors);
        }

        return new ValidatedSubmission($normalized, $documents);
    }

    private function validateValue(FormFieldData $field, mixed $value): ?string
    {
        return match ($field->type) {
            FieldType::ShortText, FieldType::LongText => $this->validateText($field, $value),
            FieldType::Email => $this->validateEmail($field, $value),
            FieldType::Phone => ! is_string($value) || preg_match('/^\+249[0-9]{9}$/', $value) !== 1 ? 'form.invalid_phone_format' : null,
            FieldType::Number => $this->validateNumber($field, $value),
            FieldType::Date => $this->validateDate($field, $value),
            FieldType::Dropdown, FieldType::Radio => $this->validateChoice($field, $value),
            FieldType::Checkbox => $this->validateCheckbox($field, $value),
            FieldType::FileUpload, FieldType::ImageUpload => $this->validateDocuments($field, $value),
        };
    }

    private function validateText(FormFieldData $field, mixed $value): ?string
    {
        if (! is_string($value)) {
            return 'form.text_expected';
        }
        $length = mb_strlen(trim($value));
        if (isset($field->validation['min']) && $length < $field->validation['min']) {
            return 'form.text_too_short';
        }
        if (isset($field->validation['max']) && $length > $field->validation['max']) {
            return 'form.text_too_long';
        }
        if (isset($field->validation['regex']) && preg_match((string) $field->validation['regex'], $value) !== 1) {
            return 'form.pattern_mismatch';
        }

        return null;
    }

    private function validateNumber(FormFieldData $field, mixed $value): ?string
    {
        if (! is_int($value) && ! is_float($value)) {
            return 'form.numeric_expected';
        }
        if ((isset($field->validation['min']) && $value < $field->validation['min'])
            || (isset($field->validation['max']) && $value > $field->validation['max'])) {
            return 'form.number_out_of_bounds';
        }

        return null;
    }

    private function validateEmail(FormFieldData $field, mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return 'form.invalid_email_format';
        }
        if (isset($field->validation['max']) && mb_strlen($value) > $field->validation['max']) {
            return 'form.text_too_long';
        }

        return null;
    }

    private function validateDate(FormFieldData $field, mixed $value): ?string
    {
        if (! is_string($value)) {
            return 'form.invalid_date_format';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return 'form.invalid_date_format';
        }
        $today = new DateTimeImmutable('today');
        if (($field->validation['before_today'] ?? false) && $date >= $today) {
            return 'form.date_out_of_bounds';
        }
        if (($field->validation['after_today'] ?? false) && $date <= $today) {
            return 'form.date_out_of_bounds';
        }

        return null;
    }

    private function validateChoice(FormFieldData $field, mixed $value): ?string
    {
        $allowed = array_column($field->options, 'value');

        return ! is_string($value) || ! in_array($value, $allowed, true) ? 'form.invalid_option_selected' : null;
    }

    private function validateCheckbox(FormFieldData $field, mixed $value): ?string
    {
        if ($field->options === []) {
            if (! is_bool($value) || ($field->required && ! $value)) {
                return 'form.mandatory_agreement_required';
            }

            return null;
        }
        if (! is_array($value) || ! array_is_list($value) || ($field->required && $value === [])) {
            return 'form.invalid_option_selected';
        }
        $allowed = array_column($field->options, 'value');

        return count($value) !== count(array_unique($value))
            || array_filter($value, static fn (mixed $item): bool => ! is_string($item) || ! in_array($item, $allowed, true)) !== []
            ? 'form.invalid_option_selected'
            : null;
    }

    private function validateDocuments(FormFieldData $field, mixed $value): ?string
    {
        try {
            $ids = $this->documentIds($field, $value);
            foreach ($ids as $id) {
                OpaqueId::fromString($id);
            }
        } catch (InvalidArgumentException) {
            return 'form.invalid_document_reference';
        }

        return null;
    }

    /** @return list<string> */
    private function documentIds(FormFieldData $field, mixed $value): array
    {
        $maximum = (int) $field->validation['max_files'];
        if ($maximum === 1 && is_string($value)) {
            return [$value];
        }
        if (! is_array($value) || ! array_is_list($value) || $value === [] || count($value) > $maximum
            || array_filter($value, static fn (mixed $id): bool => ! is_string($id)) !== []) {
            throw new InvalidArgumentException;
        }

        return $value;
    }

    private function normalize(FormFieldData $field, mixed $value): mixed
    {
        if (is_string($value) && in_array($field->type, [FieldType::ShortText, FieldType::LongText], true)) {
            return trim($value);
        }
        if (is_string($value) && $field->type === FieldType::Email) {
            return mb_strtolower(trim($value));
        }

        return $value;
    }
}
