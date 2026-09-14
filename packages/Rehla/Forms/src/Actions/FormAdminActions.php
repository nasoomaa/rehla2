<?php

declare(strict_types=1);

namespace Rehla\Forms\Actions;

use Rehla\Forms\Contracts\FormAdminCommands;
use Rehla\Forms\Data\PublishedFormData;

final readonly class FormAdminActions implements FormAdminCommands
{
    public function __construct(
        private CreateFormDraft $create,
        private UpdateFormDraft $update,
        private PublishFormVersion $publish,
    ) {}

    public function createDraft(string $serviceId, ?string $baseVersionId, string $actorId, string $correlationId): string
    {
        return $this->create->handle($serviceId, $baseVersionId, $actorId, $correlationId);
    }

    public function updateDraft(string $draftId, array $fields, string $actorId, string $correlationId): void
    {
        $this->update->handle($draftId, $fields, $actorId, $correlationId);
    }

    public function publish(string $draftId, string $actorId, string $correlationId): PublishedFormData
    {
        return $this->publish->handle($draftId, $actorId, $correlationId);
    }
}
