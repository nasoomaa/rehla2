<?php

declare(strict_types=1);

namespace Rehla\Content\Contracts;

use Rehla\Content\Data\PageData;

interface PublishedContentReader
{
    public function getBySlug(string $slug, string $locale): PageData;
}
