<?php

declare(strict_types=1);

namespace Rehla\Forms\Contracts;

use Rehla\Forms\Data\FormFieldData;
use Rehla\Forms\Data\PublishedFormData;

interface FormAdminCommands
{
    public function createDraft(string $serviceId, ?string $baseVersionId, string $actorId, string $correlationId): string;

    /** @param list<FormFieldData> $fields */
    public function updateDraft(string $draftId, array $fields, string $actorId, string $correlationId): void;

    public function publish(string $draftId, string $actorId, string $correlationId): PublishedFormData;
}
