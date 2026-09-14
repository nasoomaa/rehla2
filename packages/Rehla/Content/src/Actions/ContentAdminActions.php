<?php

declare(strict_types=1);

namespace Rehla\Content\Actions;

use Rehla\Content\Contracts\ContentAdminCommands;
use Rehla\Content\Data\PageInputData;

final readonly class ContentAdminActions implements ContentAdminCommands
{
    public function __construct(
        private CreatePage $createPage,
        private UpdatePage $updatePage,
        private PublishPage $publishPage,
    ) {}

    public function create(PageInputData $data, string $actorId, string $correlationId): string
    {
        return $this->createPage->handle($data, $actorId, $correlationId);
    }

    public function update(string $pageId, PageInputData $data, string $actorId, string $correlationId): void
    {
        $this->updatePage->handle($pageId, $data, $actorId, $correlationId);
    }

    public function publish(string $pageId, string $actorId, string $correlationId): void
    {
        $this->publishPage->handle($pageId, $actorId, $correlationId);
    }
}
