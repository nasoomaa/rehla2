<?php

declare(strict_types=1);

namespace Rehla\Forms\Data;

final readonly class ValidatedSubmission
{
    /**
     * @param  array<string, mixed>  $answers
     * @param  list<array{field_key: string, document_id: string, purpose: string, mime: list<string>}>  $documentReferences
     */
    public function __construct(public array $answers, public array $documentReferences) {}
}
