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
- Create: `packages/Rehla/Reporting/database/migrations/*_create_reporting_views.php`
- Create: `packages/Rehla/Reporting/README.md`
- Test: `packages/Rehla/Reporting/tests/Integration/ProductMetricsTest.php`
- Test: `packages/Rehla/Reporting/tests/Architecture/ReadOnlyReportingTest.php`

**Interfaces:**
- Produces: `GetProductMetrics::handle(MetricFilter): ProductMetrics`.
- `MetricFilter` يحمل `fromUtc`, `toUtc`, `displayTimezone='Africa/Khartoum'`.

- [ ] **Step 1: اكتب fixture معروفًا واختبارات المؤشرات**

```php
it('calculates the twelve phase-one metrics from one fixed fixture', function (): void {
    seedReportingFixture();
    $metrics = app(GetProductMetrics::class)->handle(period('2026-09-01', '2026-10-01'));

    expect($metrics->registeredUsers)->toBe(4)
        ->and($metrics->savedTravelers)->toBe(6)
        ->and($metrics->orderVolumeMinor)->toBe(10_000_00)
        ->and($metrics->topUpCompletionRate)->toBe(75.0)
        ->and($metrics->topUpApprovalRatio)->toBe(2.0)
        ->and($metrics->completedOrderPercentage)->toBe(50.0);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Reporting/tests`

Expected: FAIL قبل queries/views.

- [ ] **Step 3: عرف المؤشرات الاثني عشر بدقة**

```text
registered_users = users created in period
saved_travelers = travelers created in period
order_volume_minor = sum orders.amount_paid_minor created in period
top_up_completion_rate = decided top-ups / submitted top-ups * 100
average_review_seconds = avg(decided_at - submitted_at) for decided top-ups
approval_rejection_ratio = approved count / rejected count; null if denominator zero
orders_by_service = count orders grouped by service snapshot name and service_id
average_fulfillment_seconds = avg(completed_at - execution.created_at) for completed
customer_action_volume = count customer_action_requests created in period
completed_order_percentage = completed executions / created executions * 100
traveler_reuse_rate = travelers used by >1 order / travelers used by any order * 100
repeat_customer_rate = accounts with >1 order / accounts with >=1 order * 100
```

تستخدم intervals نصف المفتوحة `[fromUtc,toUtc)`، وتعاد النسبة برقم عشري بدقة منزلتين أوnull عند غياب المقام. `order_volume_minor` قيمة مالية، بينما عدد الطلبات يظهر في `orders_by_service`.

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
