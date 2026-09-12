# Rehla Catalog, Forms and Content Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** بناء كتالوج الخدمات وأسعارها ومتطلباتها وإصدارات نماذجها الثابتة والمحتوى العام ثنائي اللغة.

**Architecture:** يملك Catalog تعريف الخدمة والسعر الحالي وتاريخه، وتملك Forms مسودة schema وإصدارات النشر، ويملك Content الصفحات العامة. تستعمل الصور العامة عقد Documents، وتسجل الكتابات الحساسة عبر Audit.

**Tech Stack:** Laravel Eloquent داخل الحزمة المالكة، PostgreSQL JSONB وtriggers، Laravel validation، Pest.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

## Global Constraints

- أكمل خطتي Foundation وIdentity/Platform Services أولًا.
- لا تحذف خدمة أوصورة أوإصدار نموذج استعمله Order.
- السعر الحالي claim يعاد التحقق منه وقت SubmitOrder.
- FormVersion المنشور immutable في التطبيق وقاعدة البيانات.

---

### Task 1: Service Catalog and Price History

**Files:**
- Create: `packages/Rehla/Catalog/src/database/migrations/*_create_catalog_tables.php`
- Create: `packages/Rehla/Catalog/src/Enums/ServiceStatus.php`
- Create: `packages/Rehla/Catalog/src/Data/{ServiceData,ServiceQuote,ServiceSnapshot,FulfillmentPolicyData}.php`
- Create: `packages/Rehla/Catalog/src/Actions/{CreateService,UpdateServiceContent,ChangeServicePrice,PublishService,DeactivateService,ReorderServices,SaveFulfillmentPolicyDraft,PublishFulfillmentPolicy}.php`
- Create: `packages/Rehla/Catalog/src/Queries/{ListPublishedServices,GetServiceDetails,GetCurrentServiceQuote,GetPublishedFulfillmentPolicy}.php`
- Create: `packages/Rehla/Catalog/src/Contracts/{ServiceCatalog,PublishedFulfillmentPolicyReader}.php`
- Test: `packages/Rehla/Catalog/tests/Feature/ServiceLifecycleTest.php`
- Test: `packages/Rehla/Catalog/tests/Integration/PriceHistoryTest.php`

**Mandatory Package Contract — Catalog:**
- Create/verify: `packages/Rehla/Catalog/composer.json` and `packages/Rehla/Catalog/README.md`.
- Create/verify: `packages/Rehla/Catalog/src/Providers/CatalogServiceProvider.php`.
- Create/verify: `packages/Rehla/Catalog/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Catalog/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Catalog/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-catalog`; `CatalogServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-catalog')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Catalog/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `ServiceCatalog::currentQuote(string $serviceId): ServiceQuote`.
- Produces `ServiceQuote(serviceId, priceMinor, currency, quoteVersion, available)` و`ServiceSnapshot(name, descriptions, expectedDuration, notes, requirements)`.
- Produces: `PublishedFulfillmentPolicyReader::forService(string $serviceId): FulfillmentPolicyData`؛ السياسة وإصداراتها مملوكة لـCatalog ولا تعتمد Purchasing على Fulfillment لقراءتها.

- [ ] **Step 1: اكتب اختبارات lifecycle والسعر**

```php
it('keeps price history and blocks ordering a disabled service', function (): void {
    $service = createService(priceMinor: 2_500_00);
    publishService($service->id);
    changeServicePrice($service->id, 3_000_00, actorId: staffId());

    expect(priceHistory($service->id))->toHaveCount(2)
        ->and(currentQuote($service->id)->priceMinor)->toBe(3_000_00);

    deactivateService($service->id);
    expect(currentQuote($service->id)->available)->toBeFalse();
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Catalog/tests`

Expected: FAIL قبل وجود الجداول.

- [ ] **Step 3: نفذ schema والـActions**

أنشئ:

```text
services: id, slug unique, name_en, name_ar, short_description_en/ar,
          detailed_description_en/ar, expected_duration_en/ar, notes_en/ar,
          current_price_minor bigint, currency char(3), price_version bigint,
          status, sort_order, published_at, timestamps
service_price_history: id, service_id, price_minor, currency, version,
                       changed_by, effective_at
service_requirements: id, service_id, text_en, text_ar, sort_order
service_media: id, service_id, document_id, alt_en, alt_ar, sort_order
fulfillment_policy_drafts: id, service_id unique, policy jsonb, updated_by, timestamps
fulfillment_policy_versions: id, service_id, version, policy jsonb, checksum,
                             published_by, published_at, created_at
```

يفرض check أن `price_minor >= 0` و`currency='SDG'`. `ChangeServicePrice` يقفل service، يزيد `price_version`، يحدث السعر، يضيف history وAudit في معاملة واحدة. ينشر `PublishFulfillmentPolicy` نسخة immutable وفق `specs/contracts/service-fulfillment-sop.md`، ويمنع PostgreSQL UPDATE/DELETE للنسخة المنشورة.

- [ ] **Step 4: أثبت النشر والتعطيل والترتيب**

اختبر منع نشر خدمة بلااسمين أووصف أوrequirement أوسعر أوصورة clean/public. اختبر أن التعطيل يخفي الخدمة من listing ويترك details التاريخية متاحة للعقود الداخلية. اختبر version/checksum وحماية policy المنشورة وبقاء Orders/Executions القديمة على النسخة الملتقطة.

Run: `php artisan test packages/Rehla/Catalog/tests`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Catalog docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(catalog): add service lifecycle prices and requirements"
```

### Task 2: Draft and Published Form Versions

**Files:**
- Create: `packages/Rehla/Forms/src/database/migrations/*_create_form_tables.php`
- Create: `packages/Rehla/Forms/src/database/migrations/*_protect_published_form_versions.php`
- Create: `packages/Rehla/Forms/src/Enums/{FieldType,FormVersionStatus}.php`
- Create: `packages/Rehla/Forms/src/Data/{FormFieldData,PublishedFormData,ValidatedSubmission}.php`
- Create: `packages/Rehla/Forms/src/Actions/{CreateFormDraft,UpdateFormDraft,PublishFormVersion}.php`
- Create: `packages/Rehla/Forms/src/Queries/GetPublishedForm.php`
- Create: `packages/Rehla/Forms/src/Contracts/FormSubmissionValidator.php`
- Test: `packages/Rehla/Forms/tests/Feature/FormPublishingTest.php`
- Test: `packages/Rehla/Forms/tests/Integration/PublishedFormImmutabilityTest.php`
- Test: `packages/Rehla/Forms/tests/Unit/FieldValidationTest.php`

**Mandatory Package Contract — Forms:**
- Create/verify: `packages/Rehla/Forms/composer.json` and `packages/Rehla/Forms/README.md`.
- Create/verify: `packages/Rehla/Forms/src/Providers/FormsServiceProvider.php`.
- Create/verify: `packages/Rehla/Forms/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Forms/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Forms/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-forms`; `FormsServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-forms')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Forms/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `GetPublishedForm::handle(string $serviceId): PublishedFormData`.
- Produces: `FormSubmissionValidator::validate(string $formVersionId, array $answers): ValidatedSubmission`؛ يعيد answers مطبعة ومراجع `document_id` opaque وتصنيفاتها المطلوبة دون استدعاء Documents.

- [ ] **Step 1: اكتب data set لكل الأنواع الأحد عشر**

```php
dataset('form field types', [
    'short_text', 'long_text', 'email', 'phone', 'number', 'date',
    'select', 'radio', 'checkbox', 'file', 'image',
]);

it('round-trips and validates every field type', function (string $type): void {
    $version = publishFormWith(field(type: $type, required: true));
    expect(validateValidExample($version, $type))->toBeInstanceOf(ValidatedSubmission::class);
    expect(fn () => validateInvalidExample($version, $type))->toThrow(FormValidationFailed::class);
})->with('form field types');
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Forms/tests`

Expected: FAIL لكل الأنواع قبل التنفيذ.

- [ ] **Step 3: أنشئ form schema ثابتًا**

أنشئ `form_drafts(id, service_id unique, schema jsonb, updated_by, timestamps)` و`form_versions(id, service_id, version, schema jsonb, checksum char(64), status, published_by, published_at, created_at)` مع unique `(service_id,version)` وإصدار published واحد حالي لكل خدمة عبر partial unique index.

يمثل كل field بهذه المفاتيح المحددة:

```json
{
  "key": "passport_scan",
  "type": "file",
  "label": {"en": "Passport scan", "ar": "صورة الجواز"},
  "order": 10,
  "required": true,
  "helper": {"en": "Upload a clear copy", "ar": "ارفع نسخة واضحة"},
  "options": [],
  "validation": {"document_purpose": "passport", "max_files": 1}
}
```

`select` و`radio` يحتاجان options غير فارغة وفريدة. يتحقق Forms في file/image من cardinality وصيغة opaque `document_id` فقط ويستخرج purpose/mime المطلوبة في `ValidatedSubmission`؛ لا يعتمد Forms على Documents ولا يفحص الملكية أو`clean`. تستدعي Purchasing لاحقًا `OwnedDocuments::assertCleanOwned`. بقية الأنواع تستخدم validators صريحة لا نصوص قواعد قابلة للتنفيذ من admin.

- [ ] **Step 4: أضف حماية PostgreSQL للمنشور**

أضف trigger يمنع UPDATE وDELETE عندما `OLD.status='published'`. النشر ينسخ draft إلىصف جديد، يرتب المفاتيح قبل SHA-256، ولا يعيد استعمال صف سابق.

Run: `php artisan test packages/Rehla/Forms/tests/Integration/PublishedFormImmutabilityTest.php`

Expected: UPDATE/DELETE المباشران يفشلان، والنشر الجديد لا يغير checksum القديم.

- [ ] **Step 5: أثبت validation الكامل**

اختبر label ثنائي اللغة، order فريد، required/optional، helper، options، min/max/regex allowlist، email/phone/date/number، image purpose وfile purpose، ومفاتيح answers غير المعرفة.

Run: `php artisan test packages/Rehla/Forms/tests`

Expected: PASS لكل R08 وR09 وR27 وR42.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Forms docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(forms): add immutable service form versions"
```

### Task 3: Localized Public Content

**Files:**
- Create: `packages/Rehla/Content/src/database/migrations/*_create_content_pages.php`
- Create: `packages/Rehla/Content/src/Enums/PageStatus.php`
- Create: `packages/Rehla/Content/src/Data/PageData.php`
- Create: `packages/Rehla/Content/src/Actions/{CreatePage,UpdatePage,PublishPage}.php`
- Create: `packages/Rehla/Content/src/Queries/GetPublishedPage.php`
- Test: `packages/Rehla/Content/tests/Feature/ContentPublishingTest.php`

**Mandatory Package Contract — Content:**
- Create/verify: `packages/Rehla/Content/composer.json` and `packages/Rehla/Content/README.md`.
- Create/verify: `packages/Rehla/Content/src/Providers/ContentServiceProvider.php`.
- Create/verify: `packages/Rehla/Content/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Content/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Content/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-content`; `ContentServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-content')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Content/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `GetPublishedPage::handle(string $slug, string $locale): PageData` مع fallback إلى الإنجليزية.

- [ ] **Step 1: اكتب اختبار النشر والترجمة**

```php
it('returns Arabic content and falls back to English when a field is empty', function (): void {
    publishPage(slug: 'home', titleEn: 'Travel services', titleAr: 'خدمات السفر', bodyEn: 'Welcome', bodyAr: '');

    $page = app(GetPublishedPage::class)->handle('home', 'ar');
    expect($page->title)->toBe('خدمات السفر')->and($page->body)->toBe('Welcome');
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Content/tests`

Expected: FAIL قبل schema.

- [ ] **Step 3: نفذ pages وAudit**

أنشئ `content_pages(id, slug unique, title_en, title_ar, body_en, body_ar, status, published_at, updated_by, timestamps)`. نظف HTML عبر allowlist تمنع script وevent attributes وjavascript URLs. تسجل create/update/publish عبر AuditWriter.

- [ ] **Step 4: اختبر الأمان وRTL contract**

اختبر إزالة `<script>` و`onclick`، ومنع موظف بلا`content.manage`، وإرجاع locale وdirection الصحيحين في DTO.

Run: `php artisan test packages/Rehla/Content/tests`

Expected: PASS.

- [ ] **Step 5: شغل بوابة الخطة وCommit**

Run: `composer verify && git diff --check`

Expected: PASS.

```bash
git add packages/Rehla/Catalog packages/Rehla/Forms packages/Rehla/Content docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(content): add localized public pages"
```
