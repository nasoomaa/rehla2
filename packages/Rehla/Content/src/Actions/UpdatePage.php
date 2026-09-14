<?php

declare(strict_types=1);

namespace Rehla\Content\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Content\Contracts\ContentAuthorizer;
use Rehla\Content\Data\PageInputData;
use Rehla\Content\Enums\PageStatus;
use Rehla\Content\Exceptions\ContentPageNotFound;
use Rehla\Content\Exceptions\ContentSlugExists;
use Rehla\Content\Exceptions\ContentValidationFailed;
use Rehla\Content\Support\HtmlSanitizer;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;

final readonly class UpdatePage
{
    public function __construct(
        private ContentAuthorizer $authorizer,
        private HtmlSanitizer $sanitizer,
        private AuditWriter $audit,
        private Clock $clock,
    ) {}

    public function handle(string $pageId, PageInputData $data, string $actorId, string $correlationId): void
    {
        OpaqueId::fromString($pageId);
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        $this->authorizer->assertCanManage($actorId);
        $body = ['en' => $this->sanitizer->sanitize($data->body['en']), 'ar' => $this->sanitizer->sanitize($data->body['ar'])];
        $errors = [];
        foreach ($body as $locale => $value) {
            if (trim(strip_tags($value)) === '') {
                $errors['body_'.$locale][] = 'content.body_required';
            }
        }
        if ($errors !== []) {
            throw new ContentValidationFailed($errors);
        }

        try {
            DB::transaction(function () use ($pageId, $data, $body, $actorId, $correlationId): void {
                $page = DB::table('content_pages')->where('id', $pageId)->lockForUpdate()->first();
                if ($page === null) {
                    throw new ContentPageNotFound;
                }
                DB::table('content_pages')->where('id', $pageId)->update([
                    'slug' => $data->slug,
                    'title_en' => trim($data->title['en']), 'title_ar' => trim($data->title['ar']),
                    'body_en' => $body['en'], 'body_ar' => $body['ar'],
                    'meta_title_en' => trim($data->metaTitle['en']), 'meta_title_ar' => trim($data->metaTitle['ar']),
                    'meta_description_en' => trim($data->metaDescription['en']), 'meta_description_ar' => trim($data->metaDescription['ar']),
                    'status' => PageStatus::Draft->value,
                    'published_at' => null,
                    'updated_by' => $actorId,
                    'updated_at' => $this->clock->now(),
                ]);
                $this->audit->append(new AppendAuditData(
                    'staff', $actorId, 'content.page_updated', 'content_page', $pageId,
                    [], ['slug' => (string) $page->slug, 'status' => (string) $page->status],
                    ['slug' => $data->slug, 'status' => PageStatus::Draft->value], null, $correlationId,
                ));
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505'
                && str_contains($exception->getMessage(), 'content_pages_slug_unique')) {
                throw new ContentSlugExists;
            }
            throw $exception;
        }
    }
}
