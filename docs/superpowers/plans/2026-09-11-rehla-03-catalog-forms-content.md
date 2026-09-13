# Rehla Catalog, Forms and Content Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** بناء كتالوج الخدمات وأسعارها ومتطلباتها وإصدارات نماذجها الثابتة والمحتوى العام ثنائي اللغة.

**Architecture:** يملك Catalog تعريف الخدمة والسعر الحالي وتاريخه، وتملك Forms مسودة schema وإصدارات النشر، ويملك Content الصفحات العامة. تستعمل الصور العامة عقد Documents، وتسجل الكتابات الحساسة عبر Audit.

**Tech Stack:** Laravel Eloquent داخل الحزمة المالكة، PostgreSQL JSONB وtriggers، Laravel validation، Pest.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

**Prerequisites:** إغلاق بوابة الخطة 02 وتوفر Identity وAudit وDocuments وعقود الصلاحيات والملفات.

## Global Constraints

- أكمل خطتي Foundation وIdentity/Platform Services أولًا.
- لا تحذف خدمة أوصورة أوإصدار نموذج استعمله Order.
- السعر الحالي claim يعاد التحقق منه وقت SubmitOrder.
- FormVersion المنشور immutable في التطبيق وقاعدة البيانات.

---

### Task 1: Service Catalog and Price History

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ Catalog يملك جداول الكتالوج، وتصحيح Documents يوسع قيود جدول `documents` المملوك له فقط. لا كتابة مباشرة بين الحزمتين.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R07, R41, R49`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/Catalog/src/database/migrations/*_create_catalog_tables.php`
- Create: `packages/Rehla/Catalog/src/database/migrations/*_protect_catalog_history.php`
- Create: `packages/Rehla/Catalog/src/Enums/ServiceStatus.php`
- Create: `packages/Rehla/Catalog/src/Data/{ServiceData,ServiceRequirementData,ServiceMediaData,ServiceQuote,ServiceSnapshot,FulfillmentPolicyData}.php`
- Create: `packages/Rehla/Catalog/src/Actions/{CreateService,UpdateServiceContent,ChangeServicePrice,PublishService,DeactivateService,ReorderServices,SaveFulfillmentPolicyDraft,PublishFulfillmentPolicy}.php`
- Create: `packages/Rehla/Catalog/src/Queries/{ListPublishedServices,GetServiceDetails,GetCurrentServiceQuote,GetPublishedFulfillmentPolicy}.php`
- Create: `packages/Rehla/Catalog/src/Contracts/{ServiceCatalog,ServiceQuoteReader,PublishedFulfillmentPolicyReader,CatalogAuthorizer}.php`
- Create: `packages/Rehla/Catalog/src/Exceptions/{ServiceNotFound,ServiceNotPublishable,FulfillmentPolicyMissing,FulfillmentPolicyInvalid,FulfillmentPolicyImmutable}.php`
- Modify: `packages/Rehla/Catalog/{composer.json,README.md}`
- Modify: `packages/Rehla/Catalog/src/Providers/CatalogServiceProvider.php`
- Modify: `scripts/create-rehla-packages.php`
- Modify: `packages/Rehla/Documents/src/Enums/DocumentPurpose.php`
- Modify: `packages/Rehla/Documents/src/Actions/ScanDocument.php`
- Modify: `packages/Rehla/Documents/src/Providers/DocumentsServiceProvider.php`
- Create: `packages/Rehla/Documents/src/Contracts/PublicDocuments.php`
- Create: `packages/Rehla/Documents/src/Actions/AttachPublicDocuments.php`
- Create: `packages/Rehla/Documents/src/database/migrations/*_enable_public_document_purposes.php`
- Modify: `packages/Rehla/Documents/README.md`
- Modify: `config/filesystems.php`
- Modify: `docs/architecture/rehla-package-contract-map.json`
- Modify: `docs/requirements/rehla-phase-1-acceptance.csv`
- Modify: `packages/Rehla/Core/src/Errors/ProblemCode.php`
- Modify: `packages/Rehla/Core/tests/Unit/ProblemCodeTest.php`
- Test: `packages/Rehla/Documents/tests/Feature/PublicDocumentLifecycleTest.php`
- Test: `packages/Rehla/Catalog/tests/Feature/ServiceLifecycleTest.php`
- Test: `packages/Rehla/Catalog/tests/Integration/PriceHistoryTest.php`
- Test: `packages/Rehla/Catalog/tests/Integration/FulfillmentPolicyImmutabilityTest.php`

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
- Consumes: `CatalogAuthorizer::assertCanManage(string $actorId): void`; العقد مملوك لـCatalog ويفشل مغلقًا حتى يربط Admin adapter في خطته، فتختبر Actions بـfake صريح ولا تستورد Catalog حزمة Identity.
- Consumes: `PublicDocuments::assertCleanPublic(array $documentIds, string $ownerId, array $requiredPurposes): array<DocumentReference>`؛ يربط المستندات بعد فحصها ونقلها من private staging إلى public disk، داخل معاملة Catalog ومن دون كشف storage key.
- Produces: `ServiceCatalog::listPublished(int $page, int $perPage): array<ServiceSnapshot>` و`ServiceCatalog::getPublishedBySlug(string $slug): ServiceSnapshot` و`ServiceCatalog::getById(string $serviceId): ServiceSnapshot`.
- Produces: `ServiceQuoteReader::currentQuote(string $serviceId): ServiceQuote` بواسطة `GetCurrentServiceQuote`.
- Produces `ServiceQuote(serviceId, priceMinor, currency, quoteVersion, available)` و`ServiceSnapshot(id, slug, name, descriptions, expectedDuration, notes, requirements, media)` كقيم immutable.
- Produces: `PublishedFulfillmentPolicyReader::forService(string $serviceId): FulfillmentPolicyData`؛ السياسة وإصداراتها مملوكة لـCatalog ولا تعتمد Purchasing على Fulfillment لقراءتها.
- Mutations: كل Action إدارية تستقبل `actorId` و`correlationId` opaque وتستدعي `CatalogAuthorizer` قبل الكتابة؛ تغييرات السعر والحالة والسياسة تضيف Audit في المعاملة نفسها.

- [x] **Step 1: اكتب اختبارات lifecycle والسعر**

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

- [x] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Catalog/tests`

Expected: FAIL قبل وجود الجداول.

- [x] **Step 3: نفذ schema والـActions**

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

يفرض check أن `price_minor > 0` و`currency='SDG'`. `ChangeServicePrice` يقفل service، يزيد `price_version`، يحدث السعر، يضيف history وAudit في معاملة واحدة. ينشر `PublishFulfillmentPolicy` نسخة immutable وفق `specs/contracts/service-fulfillment-sop.md`، ويمنع PostgreSQL UPDATE/DELETE للنسخة المنشورة.

تضيف تصحيحات Documents الغرضين `service_media` و`bank_logo` بحد 5 MiB وصور JPEG/PNG فقط. يبقى الرفع على private staging؛ بعد نجاح decoder وClamAV يكتب العامل النسخة المنظفة على public disk ثم يعتمد مفتاحها وقرصها بسياج scan، ويحذف المرشح العام إن فقد lease. لا يصبح أي ملف مرفوض أوغير مفحوص متاحًا علنًا. يربط `PublicDocuments` الملفات النظيفة العامة ويمنع المالك الآخر والغرض الخاطئ.

- [x] **Step 4: أثبت النشر والتعطيل والترتيب**

اختبر منع نشر خدمة بلااسمين أووصف أوrequirement أوسعر أوصورة clean/public. اختبر أن التعطيل يخفي الخدمة من listing ويترك details التاريخية متاحة للعقود الداخلية. اختبر version/checksum وحماية policy المنشورة، وأن العقد يعيد معرف النسخة immutable الذي تلتقطه Purchasing لاحقًا. إثبات بقاء Orders/Executions القديمة على النسخة الملتقطة مؤجل صراحة إلى الخطة 05 حيث تنشأ جداولها.

Run: `php artisan test packages/Rehla/Catalog/tests`

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add packages/Rehla/Catalog docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(catalog): add service lifecycle prices and requirements"
```

### Task 2: Draft and Published Form Versions

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Forms. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R08, R09, R27, R42`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


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

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Catalog, Content, Forms. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R07, R08, R09, R27, R41, R42, R49 (supporting evidence)`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


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

## Plan Completion Gate

تغلق الخطة lifecycle الخدمة وإصدارات السعر والسياسة والنموذج والمحتوى ثنائي اللغة، وتنتج `ServiceCatalog`, `PublishedFulfillmentPolicyReader`, `PublishedFormReader`, و`FormSubmissionValidator`. تفتح بوابة `purchase-readiness` ولا تدعي اكتمال checkout قبل إغلاقها في الخطة 05.
