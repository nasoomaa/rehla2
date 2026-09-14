<?php

declare(strict_types=1);

namespace Rehla\Forms\Data;

use Rehla\Forms\Enums\FieldType;
use Rehla\Forms\Exceptions\FormValidationFailed;
use stdClass;
use ValueError;

final readonly class FormFieldData
{
    private const array SAFE_REGEX = [
        '/^[\p{L}\p{M} .\'-]+$/u',
        '/^[A-Z0-9]{6,12}$/',
    ];

    private const array DOCUMENT_PURPOSES = [
        'bank_receipt', 'passport_scan', 'identity_document',
        'applicant_photo', 'supporting_document', 'issued_document',
    ];

    private const array FILE_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];

    private const array IMAGE_MIMES = ['image/jpeg', 'image/png'];

    /**
     * @param  array{en: string, ar: string}  $label
     * @param  array{en: string, ar: string}  $helper
     * @param  list<array{value: string, label: array{en: string, ar: string}}>  $options
     * @param  array<string, mixed>  $validation
     */
    public function __construct(
        public string $key,
        public FieldType $type,
        public array $label,
        public int $order,
        public bool $required,
        public array $helper,
        public array $options,
        public array $validation,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1 || $order < 1) {
            throw new FormValidationFailed([$key => ['form.invalid_field_definition']]);
        }
        if (trim($label['en']) === '' || trim($label['ar']) === '') {
            throw new FormValidationFailed([$key => ['form.bilingual_label_required']]);
        }
        if ((trim($helper['en']) === '') !== (trim($helper['ar']) === '')) {
            throw new FormValidationFailed([$key => ['form.bilingual_helper_required']]);
        }

        $this->validateOptions();
        $this->validateRules();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'label' => $this->label,
            'order' => $this->order,
            'required' => $this->required,
            'helper' => $this->helper,
            'options' => $this->options,
            'validation' => $this->validation === [] ? new stdClass : $this->validation,
        ];
    }

    /** @param array<string, mixed> $field */
    public static function fromArray(array $field): self
    {
        $label = $field['label'] ?? null;
        $helper = $field['helper'] ?? null;
        if (! is_string($field['key'] ?? null)
            || ! is_string($field['type'] ?? null)
            || ! is_array($label) || ! is_string($label['en'] ?? null) || ! is_string($label['ar'] ?? null)
            || ! is_int($field['order'] ?? null) || ! is_bool($field['required'] ?? null)
            || ! is_array($helper) || ! is_string($helper['en'] ?? null) || ! is_string($helper['ar'] ?? null)) {
            throw new FormValidationFailed(['schema' => ['form.invalid_field_definition']]);
        }

        try {
            $type = FieldType::from($field['type']);
        } catch (ValueError) {
            throw new FormValidationFailed(['schema' => ['form.invalid_field_definition']]);
        }

        return new self(
            $field['key'], $type,
            ['en' => $label['en'], 'ar' => $label['ar']], $field['order'], $field['required'],
            ['en' => $helper['en'], 'ar' => $helper['ar']],
            self::parseOptions($field['options'] ?? null),
            self::parseValidation($field['validation'] ?? null),
        );
    }

    /** @return list<array{value: string, label: array{en: string, ar: string}}> */
    private static function parseOptions(mixed $raw): array
    {
        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new FormValidationFailed(['schema' => ['form.invalid_option_definition']]);
        }
        $options = [];
        foreach ($raw as $option) {
            if (! is_array($option) || ! is_string($option['value'] ?? null)
                || ! is_array($option['label'] ?? null)
                || ! is_string($option['label']['en'] ?? null)
                || ! is_string($option['label']['ar'] ?? null)) {
                throw new FormValidationFailed(['schema' => ['form.invalid_option_definition']]);
            }
            $options[] = [
                'value' => $option['value'],
                'label' => ['en' => $option['label']['en'], 'ar' => $option['label']['ar']],
            ];
        }

        return $options;
    }

    /** @return array<string, mixed> */
    private static function parseValidation(mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new FormValidationFailed(['schema' => ['form.invalid_validation_rule']]);
        }
        $validation = [];
        foreach ($raw as $key => $value) {
            if (! is_string($key)) {
                throw new FormValidationFailed(['schema' => ['form.invalid_validation_rule']]);
            }
            $validation[$key] = $value;
        }

        return $validation;
    }

    private function validateOptions(): void
    {
        if ($this->type->isChoice() && count($this->options) < 2) {
            throw new FormValidationFailed([$this->key => ['form.options_required']]);
        }
        if (! $this->type->isChoice() && $this->type !== FieldType::Checkbox && $this->options !== []) {
            throw new FormValidationFailed([$this->key => ['form.options_not_allowed']]);
        }
        if ($this->type === FieldType::Checkbox && $this->options !== [] && count($this->options) < 2) {
            throw new FormValidationFailed([$this->key => ['form.options_required']]);
        }

        $values = [];
        foreach ($this->options as $option) {
            if (trim($option['value']) === ''
                || trim($option['label']['en']) === ''
                || trim($option['label']['ar']) === '') {
                throw new FormValidationFailed([$this->key => ['form.invalid_option_definition']]);
            }
            $values[] = $option['value'];
        }
        if (count($values) !== count(array_unique($values))) {
            throw new FormValidationFailed([$this->key => ['form.duplicate_option']]);
        }
    }

    private function validateRules(): void
    {
        $allowed = match ($this->type) {
            FieldType::ShortText, FieldType::LongText => ['min', 'max', 'regex'],
            FieldType::Email => ['max'],
            FieldType::Phone => ['country_code'],
            FieldType::Number => ['min', 'max'],
            FieldType::Date => ['format', 'before_today', 'after_today'],
            FieldType::FileUpload, FieldType::ImageUpload => ['document_purpose', 'max_files', 'mime'],
            default => [],
        };
        if (array_diff(array_keys($this->validation), $allowed) !== []) {
            throw new FormValidationFailed([$this->key => ['form.validation_rule_not_allowed']]);
        }
        foreach (['min', 'max'] as $bound) {
            if (! isset($this->validation[$bound])) {
                continue;
            }
            $value = $this->validation[$bound];
            $validBound = $this->type === FieldType::Number
                ? is_int($value) || is_float($value)
                : is_int($value) && $value >= 0;
            if (! $validBound) {
                throw new FormValidationFailed([$this->key => ['form.invalid_validation_rule']]);
            }
        }
        if (isset($this->validation['min'], $this->validation['max'])
            && $this->validation['min'] > $this->validation['max']) {
            throw new FormValidationFailed([$this->key => ['form.invalid_validation_rule']]);
        }
        if (isset($this->validation['regex']) && ! in_array($this->validation['regex'], self::SAFE_REGEX, true)) {
            throw new FormValidationFailed([$this->key => ['form.validation_rule_not_allowed']]);
        }
        if ($this->type === FieldType::Phone && ($this->validation['country_code'] ?? 'SD') !== 'SD') {
            throw new FormValidationFailed([$this->key => ['form.invalid_validation_rule']]);
        }
        if ($this->type === FieldType::Date && ($this->validation['format'] ?? 'YYYY-MM-DD') !== 'YYYY-MM-DD') {
            throw new FormValidationFailed([$this->key => ['form.invalid_validation_rule']]);
        }
        if (($this->validation['before_today'] ?? false) && ($this->validation['after_today'] ?? false)) {
            throw new FormValidationFailed([$this->key => ['form.invalid_validation_rule']]);
        }
        foreach (['before_today', 'after_today'] as $dateRule) {
            if (isset($this->validation[$dateRule]) && ! is_bool($this->validation[$dateRule])) {
                throw new FormValidationFailed([$this->key => ['form.invalid_validation_rule']]);
            }
        }
        if ($this->type->isDocument()) {
            $purpose = $this->validation['document_purpose'] ?? null;
            $maxFiles = $this->validation['max_files'] ?? null;
            $mime = $this->validation['mime'] ?? null;
            $allowedMimes = $this->type === FieldType::ImageUpload ? self::IMAGE_MIMES : self::FILE_MIMES;
            if (! is_string($purpose) || ! in_array($purpose, self::DOCUMENT_PURPOSES, true)
                || ! is_int($maxFiles) || $maxFiles < 1 || $maxFiles > 10
                || ! is_array($mime) || $mime === []
                || count($mime) !== count(array_unique($mime))
                || array_filter($mime, static fn (mixed $value): bool => ! is_string($value) || ! in_array($value, $allowedMimes, true)) !== []) {
                throw new FormValidationFailed([$this->key => ['form.invalid_document_rule']]);
            }
        }
    }
}
