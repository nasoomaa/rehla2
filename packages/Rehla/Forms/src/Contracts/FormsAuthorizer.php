<?php

declare(strict_types=1);

namespace Rehla\Forms\Contracts;

interface FormsAuthorizer
{
    public function assertCanDraft(string $actorId): void;

    public function assertCanPublish(string $actorId): void;
}
