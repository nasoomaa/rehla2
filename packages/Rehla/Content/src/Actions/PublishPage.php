<?php

declare(strict_types=1);

namespace Rehla\Content\Actions;

use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Content\Contracts\ContentAuthorizer;
use Rehla\Content\Enums\PageStatus;
use Rehla\Content\Exceptions\ContentPageNotFound;
use Rehla\Content\Exceptions\ContentValidationFailed;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;

final readonly class PublishPage
{
    public function __construct(private ContentAuthorizer $authorizer, private AuditWriter $audit, private Clock $clock) {}

    public function handle(string $pageId, string $actorId, string $correlationId): void
    {
        OpaqueId::fromString($pageId);
        OpaqueId::fromString($actorId);
        OpaqueId::fromString($correlationId);
        $this->authorizer->assertCanManage($actorId);

        DB::transaction(function () use ($pageId, $actorId, $correlationId): void {
            $page = DB::table('content_pages')->where('id', $pageId)->lockForUpdate()->first();
            if ($page === null) {
                throw new ContentPageNotFound;
            }
            $errors = [];
            foreach (['title_en', 'title_ar', 'body_en', 'body_ar'] as $field) {
                if (trim((string) $page->{$field}) === '') {
                    $errors[$field][] = 'content.translation_required';
                }
            }
            if ($errors !== []) {
                throw new ContentValidationFailed($errors);
            }
            $now = $this->clock->now();
            DB::table('content_pages')->where('id', $pageId)->update([
                'status' => PageStatus::Published->value,
                'published_at' => $now,
                'updated_by' => $actorId,
                'updated_at' => $now,
            ]);
            $this->audit->append(new AppendAuditData(
                'staff', $actorId, 'content.page_published', 'content_page', $pageId,
                [], ['status' => (string) $page->status], ['status' => PageStatus::Published->value], null, $correlationId,
            ));
        });
    }
}
