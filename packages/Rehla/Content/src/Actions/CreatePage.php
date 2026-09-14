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
use Rehla\Content\Exceptions\ContentSlugExists;
use Rehla\Content\Exceptions\ContentValidationFailed;
use Rehla\Content\Support\HtmlSanitizer;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;

final readonly class CreatePage
{
    public function __construct(
        private ContentAuthorizer $authorizer,
        private HtmlSanitizer $sanitizer,
        private AuditWriter $audit,
        private Clock $clock,
    ) {}

    public function handle(PageInputData $data, string $actorId, string $correlationId): string
    {
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        $this->authorizer->assertCanManage($actorId);
        $body = $this->sanitizeBody($data);

        try {
            return DB::transaction(function () use ($data, $body, $actorId, $correlationId): string {
                $id = OpaqueId::generate()->value();
                $now = $this->clock->now();
                DB::table('content_pages')->insert($this->attributes($data, $body) + [
                    'id' => $id,
                    'status' => PageStatus::Draft->value,
                    'published_at' => null,
                    'updated_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->audit->append(new AppendAuditData(
                    'staff', $actorId, 'content.page_created', 'content_page', $id,
                    ['slug' => $data->slug], null, ['status' => PageStatus::Draft->value], null, $correlationId,
                ));

                return $id;
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505'
                && str_contains($exception->getMessage(), 'content_pages_slug_unique')) {
                throw new ContentSlugExists;
            }
            throw $exception;
        }
    }

    /** @return array{en: string, ar: string} */
    private function sanitizeBody(PageInputData $data): array
    {
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

        return $body;
    }

    /**
     * @param  array{en: string, ar: string}  $body
     * @return array<string, string>
     */
    private function attributes(PageInputData $data, array $body): array
    {
        return [
            'slug' => $data->slug,
            'title_en' => trim($data->title['en']), 'title_ar' => trim($data->title['ar']),
            'body_en' => $body['en'], 'body_ar' => $body['ar'],
            'meta_title_en' => trim($data->metaTitle['en']), 'meta_title_ar' => trim($data->metaTitle['ar']),
            'meta_description_en' => trim($data->metaDescription['en']), 'meta_description_ar' => trim($data->metaDescription['ar']),
        ];
    }
}
