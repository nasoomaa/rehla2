<?php

declare(strict_types=1);

namespace Rehla\Forms\Contracts;

use Rehla\Forms\Data\ValidatedSubmission;

interface FormSubmissionValidator
{
    /** @param array<array-key, mixed> $answers */
    public function validate(string $formVersionId, array $answers): ValidatedSubmission;
}
