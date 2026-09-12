# Rehla Reporting and Integrations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** تقديم مؤشرات المنتج الاثني عشر وعامل Outbox موثوق وإشعارات داخل التطبيق وعقد WhatsApp للاستفسار قبل بناء الواجهات.

**Architecture:** Reporting يقرأ عبر queries/views بلا كتابة إلى جداول المصدر. يعالج Notifications الـOutbox بتسليم at-least-once وdeduplication، وتبقى Integrations adapters بلا منطق أعمال أو أثر مالي.

**Tech Stack:** PostgreSQL views، Laravel Queue، scheduler، Pest، Laravel Notification payloads.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

## Global Constraints

- أكمل خطط 01–05 أولًا.
- المنطقة الزمنية للتجميع `Africa/Khartoum` والتخزين UTC.
- Reporting لا يكتب source tables.
- delivery قد يتكرر؛ كل projector/adapter idempotent بمفتاح outbox.
- WhatsApp inquiry لا ينشئ أويعدل أي سجل أعمال.

---

### Task 1: Product Metrics Contract and Read Models

**Files:**
- Create: `packages/Rehla/Reporting/src/Enums/MetricName.php`
- Create: `packages/Rehla/Reporting/src/Data/{MetricFilter,MetricValue,ProductMetrics}.php`
- Create: `packages/Rehla/Reporting/src/Queries/GetProductMetrics.php`
- Create: `packages/Rehla/Reporting/src/database/migrations/*_create_reporting_views.php`
- Create: `packages/Rehla/Reporting/README.md`
- Test: `packages/Rehla/Reporting/tests/Integration/ProductMetricsTest.php`
- Test: `packages/Rehla/Reporting/tests/Architecture/ReadOnlyReportingTest.php`

**Mandatory Package Contract — Reporting:**
- Create/verify: `packages/Rehla/Reporting/composer.json` and `packages/Rehla/Reporting/README.md`.
- Create/verify: `packages/Rehla/Reporting/src/Providers/ReportingServiceProvider.php`.
- Create/verify: `packages/Rehla/Reporting/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Reporting/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Reporting/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-reporting`; `ReportingServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-reporting')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Reporting/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `GetProductMetrics::handle(MetricFilter): ProductMetrics`.
- `MetricFilter` يحمل `fromUtc`, `toUtc`, و`as_of: CarbonImmutable` الإلزامي، و`displayTimezone='Africa/Khartoum'`.

- [ ] **Step 1: اكتب fixture معروفًا واختبارات المؤشرات**

```php
it('calculates the twelve phase-one metrics from one fixed fixture', function (): void {
    seedReportingFixture();
    $metrics = app(GetProductMetrics::class)->handle(period('2026-09-01', '2026-10-01', asOf: '2026-10-01T00:00:00Z'));

    expect($metrics->registeredUsers)->toBe(4)
        ->and($metrics->savedTravelers)->toBe(6)
        ->and($metrics->orderCount)->toBe(3)
        ->and($metrics->orderGrossValueMinor)->toBe(10_000_00)
        ->and($metrics->topUpCompletionRate)->toBe(75.0)
        ->and($metrics->topUpApprovalRatio)->toBe(66.67)
        ->and($metrics->completedOrderPercentage)->toBe(50.0);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Reporting/tests`

Expected: FAIL قبل queries/views.

- [ ] **Step 3: عرف المؤشرات الاثني عشر بدقة**

```text
M01 registered_users = users created in cohort by as_of
M02 saved_travelers = travelers created in cohort by as_of
M03-A order_count = count paid orders created in cohort by as_of
M03-B order_gross_value_minor = sum Orders.price_paid_minor for those orders
M04 top_up_completion_rate = terminal submitted top-ups / all submitted top-ups
M05 average_review_seconds = sum(decision_at - submitted_at) / terminal top-ups
M06 transfer_approval_ratio = approved terminal top-ups / all terminal top-ups
M07 orders_by_service = order_count and order_gross_value_minor grouped by immutable service snapshot
M08 average_fulfillment_seconds = sum(completed_at - received_at) / completed executions
M09 customer_action_volume = transitions into action_required by as_of
M10 completed_order_percentage = completed executions / executions received in cohort
M11 traveler_reuse_rate = travelers used by >1 paid order / travelers used by any paid order
M12 repeat_customer_rate = accounts with >1 paid order / accounts with >=1 paid order
```

تستخدم كل query/filter `as_of: CarbonImmutable` مع intervals نصف المفتوحة `[fromUtc,toUtc)`. يعاد المقام الصفري `0.00%` للنسب أو`0` للمدة، ولا يعاد `null`. تستخدم الحسابات integer أوdecimal دقيقًا. يأتي `order_count` و`order_gross_value_minor` من Orders snapshots؛ لا تعتمد Reporting على Wallet ولا تقرأ ledger لحساب قيمة الطلب.

- [ ] **Step 4: نفذ views/query والقراءة فقط**

أنشئ views بأسماء `reporting_*` فقط وملكية Reporting موثقة. امنع وجود Models قابلة للحفظ في namespace Reporting، وافحص أن source لا يحتوي `insert`, `update`, `delete`, `save`, `create` على حزم المصدر.

Run: `php artisan test packages/Rehla/Reporting/tests`

Expected: PASS لكل R62.01–R62.12.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Reporting docs/requirements/rehla-phase-1-acceptance.csv docs/architecture/table-ownership.json
git commit -m "feat(reporting): add twelve phase-one product metrics"
```

### Task 2: Outbox Worker, External Delivery and Dead Letters

**Files:**
- Create: `packages/Rehla/Notifications/src/Jobs/DeliverOutboxMessage.php`
- Create: `packages/Rehla/Notifications/src/Console/{RunOutboxWorker,ReplayDeadLetter}.php`
- Create: `packages/Rehla/Notifications/src/Contracts/NotificationChannel.php`
- Create: `packages/Rehla/Notifications/src/Data/DeliveryResult.php`
- Modify: `routes/console.php`
- Test: `packages/Rehla/Notifications/tests/Integration/OutboxWorkerTest.php`

**Interfaces:**
- Consumes: claimed `OutboxEnvelope`.
- Produces: external delivery واحدة منطقيًا لكل `(recipient,outbox_message_id,channel)` عندما يدعم المزود deduplication، وdelivery result مسيج بـlock token ومدقق. إشعار in-app سبق إنشاؤه ذريًا مع العملية.

- [ ] **Step 1: اكتب اختبار الموت وإعادة التشغيل**

```php
it('fences a stale worker after another worker reclaims the message', function (): void {
    $message = pendingOutbox('order.submitted');
    $oldToken = claimAs('dead-worker', $message, now())->lockToken;
    travel(6)->minutes();
    $newToken = claimAs('replacement-worker', $message, now())->lockToken;

    expect(markDelivered('dead-worker', $oldToken, $message))->toBeFalse()
        ->and(markDelivered('replacement-worker', $newToken, $message))->toBeTrue();
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Notifications/tests/Integration/OutboxWorkerTest.php`

Expected: FAIL قبل worker وسياج lease.

- [ ] **Step 3: نفذ dispatch والتسليم**

يعمل command كل دقيقة، يطالب100 رسالة، ويولد `lock_token` و`lease_expires_at` ثم يدفع Job لكل واحدة. بعد نجاح القنوات المطلوبة ينفذ `MarkDelivered(id, worker_id, lock_token)`؛ وعند الفشل ينفذ `MarkFailed` بالسياج نفسه ويسجل error منظفًا وtrace ID ويحسب exponential backoff بحد60دقيقة. تحديث صفر صف يعني فقدان lease ويلزم تجاهل نتيجة العامل القديم.

- [ ] **Step 4: نفذ dead-letter replay المدقق**

ينقل الفشل في المحاولة الخامسة إلى dead-letter بحقل `dead_lettered_at`. يتطلب `rehla:outbox-replay {id} --reason=` سببًا غير فارغ وقدرة `notifications.replay`، ويمسح حقول claim/delivery/dead-letter ويكتب Audit، ولا يغير payload.

Run: `php artisan test packages/Rehla/Notifications/tests`

Expected: PASS للتكرار والفشل والاسترداد وإعادة التشغيل.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Notifications routes/console.php
git commit -m "feat(notifications): deliver and replay outbox messages"
```

### Task 3: Integration Adapters and WhatsApp Inquiry Contract

**Files:**
- Create: `packages/Rehla/Integrations/src/Contracts/InquiryLinkBuilder.php`
- Create: `packages/Rehla/Integrations/src/Data/InquiryLink.php`
- Create: `packages/Rehla/Integrations/src/WhatsApp/WhatsAppInquiryLinkBuilder.php`
- Create: `config/rehla-integrations.php`
- Test: `packages/Rehla/Integrations/tests/Unit/WhatsAppInquiryLinkTest.php`
- Test: `packages/Rehla/Integrations/tests/Architecture/NoBusinessWritesTest.php`

**Mandatory Package Contract — Integrations:**
- Create/verify: `packages/Rehla/Integrations/composer.json` and `packages/Rehla/Integrations/README.md`.
- Create/verify: `packages/Rehla/Integrations/src/Providers/IntegrationsServiceProvider.php`.
- Create/verify: `packages/Rehla/Integrations/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Integrations/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Integrations/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-integrations`; `IntegrationsServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-integrations')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Integrations/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `InquiryLinkBuilder::forService(string $serviceName, string $locale): InquiryLink`.
- `InquiryLink` يحمل HTTPS URL ورسالة عرض فقط.

- [ ] **Step 1: اكتب اختبار الرابط والأثر الصفري**

```php
it('builds an encoded WhatsApp inquiry without business writes', function (): void {
    $before = businessTableCounts();
    $link = app(InquiryLinkBuilder::class)->forService('UAE Visa', 'en');

    expect($link->url)->toStartWith('https://wa.me/')
        ->and(urldecode($link->url))->toContain('UAE Visa')
        ->and(businessTableCounts())->toBe($before);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Integrations/tests`

Expected: FAIL قبل العقد.

- [ ] **Step 3: نفذ builder آمنًا**

اقرأ الرقم من `REHLA_WHATSAPP_NUMBER`، واقبل digits فقط، وابن الرابط بـ`rawurlencode`. النص الإنجليزي `Hello Rehla, I would like to inquire about {service}.` والعربي `مرحبًا رحلة، أود الاستفسار عن خدمة {service}.` لا تحفظ الرسالة ولاتنفذ HTTP request.

- [ ] **Step 4: امنع الكتابة والتسرب**

يفحص اختبار architecture عدم استخدام DB/Eloquent داخل Integrations عدا provider delivery logs المملوكة لها، وعدم تسجيل الرقم أوsecrets في errors.

Run: `php artisan test packages/Rehla/Integrations/tests`

Expected: PASS.

- [ ] **Step 5: بوابة الخطة وCommit**

Run: `composer verify && git diff --check`

Expected: PASS للمؤشرات وOutbox والتكاملات.

```bash
git add packages/Rehla/Integrations config/rehla-integrations.php docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(integrations): add side-effect-free WhatsApp inquiries"
```
