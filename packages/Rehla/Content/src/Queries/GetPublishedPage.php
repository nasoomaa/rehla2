<?php

declare(strict_types=1);

namespace Rehla\Content\Queries;

use Illuminate\Support\Facades\DB;
use Rehla\Content\Contracts\PublishedContentReader;
use Rehla\Content\Data\PageData;
use Rehla\Content\Enums\PageStatus;
use Rehla\Content\Exceptions\ContentPageNotFound;

final readonly class GetPublishedPage implements PublishedContentReader
{
    public function getBySlug(string $slug, string $locale): PageData
    {
        $page = DB::table('content_pages')
            ->where('slug', $slug)
            ->where('status', PageStatus::Published->value)
            ->first();
        if ($page === null) {
            throw new ContentPageNotFound;
        }
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $metaTitle = trim((string) $page->{'meta_title_'.$resolvedLocale});
        $metaDescription = trim((string) $page->{'meta_description_'.$resolvedLocale});

        return new PageData(
            (string) $page->id,
            (string) $page->slug,
            (string) $page->{'title_'.$resolvedLocale},
            (string) $page->{'body_'.$resolvedLocale},
            $metaTitle !== '' ? $metaTitle : (string) $page->meta_title_en,
            $metaDescription !== '' ? $metaDescription : (string) $page->meta_description_en,
            $resolvedLocale,
            $resolvedLocale === 'ar' ? 'rtl' : 'ltr',
        );
    }
}
