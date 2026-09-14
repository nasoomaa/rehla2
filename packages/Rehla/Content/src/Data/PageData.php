<?php

declare(strict_types=1);

namespace Rehla\Content\Data;

final readonly class PageData
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public string $body,
        public string $metaTitle,
        public string $metaDescription,
        public string $locale,
        public string $direction,
    ) {}
}
