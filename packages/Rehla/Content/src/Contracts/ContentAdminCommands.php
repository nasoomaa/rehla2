<?php

declare(strict_types=1);

namespace Rehla\Content\Contracts;

use Rehla\Content\Data\PageInputData;

interface ContentAdminCommands
{
    public function create(PageInputData $data, string $actorId, string $correlationId): string;

    public function update(string $pageId, PageInputData $data, string $actorId, string $correlationId): void;

    public function publish(string $pageId, string $actorId, string $correlationId): void;
}
