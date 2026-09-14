<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Rehla\Content\Actions\CreatePage;
use Rehla\Content\Actions\PublishPage;
use Rehla\Content\Actions\UpdatePage;
use Rehla\Content\Contracts\ContentAuthorizer;
use Rehla\Content\Contracts\PublishedContentReader;
use Rehla\Content\Data\PageInputData;
use Rehla\Content\Exceptions\ContentPageNotFound;
use Rehla\Content\Exceptions\ContentSlugExists;
use Rehla\Content\Exceptions\ContentValidationFailed;
use Rehla\Core\Identifiers\OpaqueId;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE content_pages, audit_entries RESTART IDENTITY CASCADE');
    app()->instance(ContentAuthorizer::class, new class implements ContentAuthorizer
    {
        public function assertCanManage(string $actorId): void
        {
            OpaqueId::fromString($actorId);
        }
    });
});

function contentPageInput(string $slug = 'about-us'): PageInputData
{
    return new PageInputData(
        slug: $slug,
        title: ['en' => 'About Rehla', 'ar' => 'عن رحلة'],
        body: ['en' => '<p>Trusted travel services</p>', 'ar' => '<p>خدمات سفر موثوقة</p>'],
        metaTitle: ['en' => 'About Rehla', 'ar' => 'عن رحلة'],
        metaDescription: ['en' => 'Learn about Rehla', 'ar' => 'تعرّف على رحلة'],
    );
}

it('keeps drafts private then returns published Arabic content with rtl direction', function (): void {
    $actorId = OpaqueId::generate()->value();
    $pageId = app(CreatePage::class)->handle(contentPageInput(), $actorId, OpaqueId::generate()->value());

    expect(fn () => app(PublishedContentReader::class)->getBySlug('about-us', 'ar'))
        ->toThrow(ContentPageNotFound::class);

    app(PublishPage::class)->handle($pageId, $actorId, OpaqueId::generate()->value());
    $page = app(PublishedContentReader::class)->getBySlug('about-us', 'ar');

    expect($page->id)->toBe($pageId)
        ->and($page->title)->toBe('عن رحلة')
        ->and($page->body)->toBe('<p>خدمات سفر موثوقة</p>')
        ->and($page->metaTitle)->toBe('عن رحلة')
        ->and($page->locale)->toBe('ar')
        ->and($page->direction)->toBe('rtl')
        ->and(DB::table('audit_entries')->where('action', 'content.page_published')->count())->toBe(1);
});

it('sanitizes stored markup and audits create update and publish', function (): void {
    $actorId = OpaqueId::generate()->value();
    $pageId = app(CreatePage::class)->handle(new PageInputData(
        slug: 'safe-page',
        title: ['en' => 'Safe page', 'ar' => 'صفحة آمنة'],
        body: [
            'en' => '<p onclick="steal()">Safe <a href="javascript:steal()">link</a><script>steal()</script></p>',
            'ar' => '<p><iframe src="https://evil.test"></iframe>آمن</p>',
        ],
        metaTitle: ['en' => '', 'ar' => ''],
        metaDescription: ['en' => '', 'ar' => ''],
    ), $actorId, OpaqueId::generate()->value());

    app(UpdatePage::class)->handle($pageId, contentPageInput('safe-page'), $actorId, OpaqueId::generate()->value());
    app(PublishPage::class)->handle($pageId, $actorId, OpaqueId::generate()->value());

    $stored = DB::table('content_pages')->where('id', $pageId)->first();
    if (! is_object($stored)) {
        throw new RuntimeException('Expected the content page to exist.');
    }
    expect((string) $stored->body_en)->not->toContain('<script', 'onclick=', 'javascript:')
        ->and((string) $stored->body_ar)->not->toContain('<iframe')
        ->and(DB::table('audit_entries')->where('subject_id', $pageId)->count())->toBe(3);
});

it('denies management before writing and maps duplicate slugs to a stable failure', function (): void {
    $actorId = OpaqueId::generate()->value();
    app(CreatePage::class)->handle(contentPageInput(), $actorId, OpaqueId::generate()->value());

    expect(fn () => app(CreatePage::class)->handle(contentPageInput(), $actorId, OpaqueId::generate()->value()))
        ->toThrow(ContentSlugExists::class);

    app()->instance(ContentAuthorizer::class, new class implements ContentAuthorizer
    {
        public function assertCanManage(string $actorId): void
        {
            throw new RuntimeException('denied');
        }
    });

    expect(fn () => app(CreatePage::class)->handle(contentPageInput('denied'), $actorId, OpaqueId::generate()->value()))
        ->toThrow(RuntimeException::class)
        ->and(DB::table('content_pages')->count())->toBe(1);
});

it('returns English content with ltr direction and falls back only for optional SEO fields', function (): void {
    $actorId = OpaqueId::generate()->value();
    $pageId = app(CreatePage::class)->handle(new PageInputData(
        slug: 'contact',
        title: ['en' => 'Contact us', 'ar' => 'اتصل بنا'],
        body: ['en' => '<p>Contact details</p>', 'ar' => '<p>بيانات الاتصال</p>'],
        metaTitle: ['en' => 'Contact Rehla', 'ar' => ''],
        metaDescription: ['en' => 'Ways to contact us', 'ar' => ''],
    ), $actorId, OpaqueId::generate()->value());
    app(PublishPage::class)->handle($pageId, $actorId, OpaqueId::generate()->value());

    $arabic = app(PublishedContentReader::class)->getBySlug('contact', 'ar');
    $english = app(PublishedContentReader::class)->getBySlug('contact', 'en');

    expect($arabic->body)->toBe('<p>بيانات الاتصال</p>')
        ->and($arabic->metaTitle)->toBe('Contact Rehla')
        ->and($arabic->metaDescription)->toBe('Ways to contact us')
        ->and($english->direction)->toBe('ltr');
});

it('refuses to publish persisted content when either language is incomplete', function (): void {
    $actorId = OpaqueId::generate()->value();
    $pageId = app(CreatePage::class)->handle(contentPageInput('incomplete'), $actorId, OpaqueId::generate()->value());
    DB::table('content_pages')->where('id', $pageId)->update(['body_ar' => '']);

    expect(fn () => app(PublishPage::class)->handle($pageId, $actorId, OpaqueId::generate()->value()))
        ->toThrow(ContentValidationFailed::class)
        ->and(DB::table('content_pages')->where('id', $pageId)->value('status'))->toBe('draft');
});
