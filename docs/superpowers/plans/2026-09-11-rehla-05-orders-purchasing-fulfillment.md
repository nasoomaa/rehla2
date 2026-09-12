# Rehla Orders, Purchasing and Fulfillment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** بناء سجل شراء ثابت، وتنفيذ خدمة مستقل، ومعاملة SubmitOrder ذرية وآمنة من التكرار والتزامن.

**Architecture:** يملك Orders اللقطات والسجل التجاري، وتملك Fulfillment الحالة التشغيلية، وتملك Purchasing تنسيق العملية والمعاملة والـidempotency. يعرف Purchasing عقد `ExecutionCreator` وتنفذه Fulfillment لمنع دورة Composer.

**Tech Stack:** PostgreSQL transactions/row locks/unique constraints/triggers، Laravel container contracts، Pest concurrency and failure injection tests.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

**Prerequisites:** إغلاق بوابة الخطة 04 وتوفر Catalog policy/form versions وTraveler/Documents/Wallet/Notifications وعقودها.

## Global Constraints

- طلب واحد = خدمة واحدة + مسافر واحد + debit واحد + execution واحد.
- لا Order drafts ولاbalance holds.
- Order ولقطاته immutable بعد الإنشاء.
- حالات TopUp وOrder وExecution منفصلة.
- Purchasing هو مالك معاملة الشراء الوحيدة.

---

### Task 1: Immutable Paid Order and Snapshots

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Orders. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R13, R33, R35, R51, R54, R55`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/Orders/src/database/migrations/*_create_orders_tables.php`
- Create: `packages/Rehla/Orders/src/database/migrations/*_protect_orders.php`
- Create: `packages/Rehla/Orders/src/Data/{CreatePaidOrderData,PaidOrderData,OrderSummary}.php`
- Create: `packages/Rehla/Orders/src/Contracts/OrderWriter.php`
- Create: `packages/Rehla/Orders/src/Actions/CreatePaidOrder.php`
- Create: `packages/Rehla/Orders/src/Queries/{GetOwnedOrder,ListOwnedOrders}.php`
- Test: `packages/Rehla/Orders/tests/Integration/OrderImmutabilityTest.php`
- Test: `packages/Rehla/Orders/tests/Feature/OrderOwnershipTest.php`

**Mandatory Package Contract — Orders:**
- Create/verify: `packages/Rehla/Orders/composer.json` and `packages/Rehla/Orders/README.md`.
- Create/verify: `packages/Rehla/Orders/src/Providers/OrdersServiceProvider.php`.
- Create/verify: `packages/Rehla/Orders/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Orders/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Orders/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-orders`; `OrdersServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-orders')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Orders/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `OrderWriter::createPaid(CreatePaidOrderData): PaidOrderData`.
- `CreatePaidOrderData` يحمل account/service/traveler IDs، price/paid/currency، debitEntryId، service/traveler/form snapshots.

- [ ] **Step 1: اكتب اختبار اللقطات والحماية**

```php
it('keeps purchase snapshots immutable', function (): void {
    $order = app(OrderWriter::class)->createPaid(paidOrderData(
        priceMinor: 2_500_00, travelerName: 'Ahmed Ali', formVersion: 3
    ));

    expect(fn () => DB::table('orders')->where('id', $order->id)->update(['price_minor' => 3_000_00]))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('order_traveler_snapshots')->where('order_id', $order->id)->delete())
        ->toThrow(QueryException::class);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Orders/tests`

Expected: FAIL قبل schema.

- [ ] **Step 3: أنشئ السجل واللقطات**

```text
orders: id, account_id, service_id, traveler_id, price_minor, amount_paid_minor,
        currency, debit_ledger_entry_id unique, financial_status='paid', created_at
order_service_snapshots: order_id unique, name_en/ar, descriptions jsonb,
                         requirements jsonb, expected_duration jsonb, notes jsonb
order_traveler_snapshots: order_id unique, full_name, date_of_birth, gender,
                          passport_number, passport_issued_at, passport_expires_at
order_form_snapshots: order_id unique, form_version_id, form_version,
                      form_checksum, schema jsonb, answers jsonb
```

أضف checks: currency SDG، السعر والمدفوع متساويان وموجبان، financial_status paid. لا تخزن storage paths؛ answers تشير إلى document UUIDs.

- [ ] **Step 4: أضف triggers واختبارات الملكية**

يمنع trigger UPDATE/DELETE على الجداول الأربعة. `GetOwnedOrder` يقيد `account_id` ويعيد404 نفسه للمفقود وغير المملوك.

Run: `php artisan test packages/Rehla/Orders/tests`

Expected: PASS لSQL المباشر ولعزل حسابين.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Orders docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(orders): add immutable paid order snapshots"
```

### Task 2: Purchasing Contracts and Idempotency Store

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Purchasing. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R13, R14, R22, R29, R30, R31, R32, R33, R34, R35, R36, R37, R45, R51, R54, R55, R58, R59 (supporting evidence)`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/Purchasing/src/database/migrations/*_create_purchase_attempts.php`
- Create: `packages/Rehla/Purchasing/src/Data/{SubmitOrderData,SubmitOrderResult,CreateExecutionData,ExecutionData}.php`
- Create: `packages/Rehla/Purchasing/src/Contracts/ExecutionCreator.php`
- Create: `packages/Rehla/Purchasing/src/Enums/PurchaseAttemptStatus.php`
- Create: `packages/Rehla/Purchasing/src/Support/CanonicalPurchaseFingerprint.php`
- Create: `packages/Rehla/Purchasing/src/Models/PurchaseAttempt.php`
- Test: `packages/Rehla/Purchasing/tests/Unit/CanonicalPurchaseFingerprintTest.php`
- Test: `packages/Rehla/Purchasing/tests/Integration/PurchaseAttemptTest.php`

**Mandatory Package Contract — Purchasing:**
- Create/verify: `packages/Rehla/Purchasing/composer.json` and `packages/Rehla/Purchasing/README.md`.
- Create/verify: `packages/Rehla/Purchasing/src/Providers/PurchasingServiceProvider.php`.
- Create/verify: `packages/Rehla/Purchasing/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Purchasing/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Purchasing/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-purchasing`; `PurchasingServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-purchasing')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Purchasing/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `ExecutionCreator::create(CreateExecutionData): ExecutionData`.
- Produces: canonical SHA-256 fingerprint لنفس الحمولة بغض النظر عن ترتيب مفاتيح JSON.

- [ ] **Step 1: اكتب اختبار canonical fingerprint**

```php
it('produces one fingerprint for semantically identical payloads', function (): void {
    $a = ['service_id' => 's1', 'answers' => ['b' => 2, 'a' => 1], 'documents' => ['d2', 'd1']];
    $b = ['documents' => ['d2', 'd1'], 'answers' => ['a' => 1, 'b' => 2], 'service_id' => 's1'];

    expect(CanonicalPurchaseFingerprint::from($a))->toBe(CanonicalPurchaseFingerprint::from($b));
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Purchasing/tests`

Expected: FAIL قبل الأنواع والجدول.

- [ ] **Step 3: نفذ canonicalization وschema**

رتب مفاتيح object تكراريًا، وحافظ على ترتيب arrays الدلالية مثل document list وoptions، وشفّر JSON بلامسافات ولاNaN ثم SHA-256.

أنشئ `purchase_attempts(id, account_id, idempotency_key, request_fingerprint, status, order_id nullable, response_status nullable, response_body jsonb nullable, created_at, completed_at)` وunique `(account_id,idempotency_key)`. حد المفتاح 128byte، والنتيجة المحفوظة 32KiB كحد أقصى.

- [ ] **Step 4: اختبر scoping والتعارض**

أثبت أن حسابين يمكنهما استخدام المفتاح نفسه، وأن الحساب نفسه لا يملك سجلين بالمفتاح نفسه، وأن fingerprint المختلف ينتج public code `idempotency.key_reused`.

Run: `php artisan test packages/Rehla/Purchasing/tests`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Purchasing
git commit -m "feat(purchasing): define purchase contracts and idempotency"
```

### Task 3: Fulfillment State Machine and Execution Creation

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Fulfillment. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R34, R35, R36, R45, R51`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/Fulfillment/src/database/migrations/*_create_fulfillment_tables.php`
- Create: `packages/Rehla/Fulfillment/src/database/migrations/*_protect_execution_history.php`
- Create: `packages/Rehla/Fulfillment/src/Enums/ExecutionStatus.php`
- Create: `packages/Rehla/Fulfillment/src/Data/{ExecutionDetails,TransitionExecutionData}.php`
- Create: `packages/Rehla/Fulfillment/src/Actions/{CreateExecution,TransitionExecution,AddInternalNote,RequestCustomerAction}.php`
- Create: `packages/Rehla/Fulfillment/src/Infrastructure/PurchasingExecutionCreator.php`
- Create: `packages/Rehla/Fulfillment/src/Queries/{GetOwnedExecution,GetExecutionForOperations}.php`
- Modify: `packages/Rehla/Fulfillment/src/Providers/FulfillmentServiceProvider.php`
- Test: `packages/Rehla/Fulfillment/tests/Unit/ExecutionStateMachineTest.php`
- Test: `packages/Rehla/Fulfillment/tests/Integration/CreateExecutionTest.php`

**Mandatory Package Contract — Fulfillment:**
- Create/verify: `packages/Rehla/Fulfillment/composer.json` and `packages/Rehla/Fulfillment/README.md`.
- Create/verify: `packages/Rehla/Fulfillment/src/Providers/FulfillmentServiceProvider.php`.
- Create/verify: `packages/Rehla/Fulfillment/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Fulfillment/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Fulfillment/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-fulfillment`; `FulfillmentServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-fulfillment')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Fulfillment/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Implements: `Rehla\Purchasing\Contracts\ExecutionCreator`.
- Produces transitions المحددة أدناه وhistory append-only.

مجموعة الانتقالات القانونية الوحيدة، وتطابق SOP وdomain ومتجه الاختبار حرفيًا:

```text
received -> under_review
received -> processing
under_review -> processing
processing -> under_review
under_review -> action_required
processing -> action_required
action_required -> action_received
action_received -> under_review
action_received -> processing
under_review -> completed
processing -> completed
action_received -> completed
received -> cancelled
under_review -> cancelled
processing -> cancelled
action_required -> cancelled
action_received -> cancelled
```

- [ ] **Step 1: اكتب جدول transitions كاختبار parameterized**

```php
dataset('allowed execution transitions', [
    ['received', 'under_review'],
    ['received', 'processing'],
    ['received', 'cancelled'],
    ['under_review', 'processing'],
    ['processing', 'under_review'],
    ['under_review', 'action_required'],
    ['under_review', 'cancelled'],
    ['processing', 'action_required'],
    ['processing', 'completed'],
    ['processing', 'cancelled'],
    ['action_required', 'action_received'],
    ['action_required', 'cancelled'],
    ['action_received', 'under_review'],
    ['action_received', 'processing'],
    ['action_received', 'completed'],
    ['action_received', 'cancelled'],
    ['under_review', 'completed'],
]);

it('allows only the declared transition graph', function (string $from, string $to): void {
    expect(ExecutionStateMachine::allows(ExecutionStatus::from($from), ExecutionStatus::from($to)))->toBeTrue();
})->with('allowed execution transitions');
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Fulfillment/tests`

Expected: FAIL قبل state machine.

- [ ] **Step 3: أنشئ execution schema**

```text
service_executions: id, order_id unique, account_id, traveler_id, service_id,
                    form_version_id, fulfillment_policy_version_id,
                    status, last_status_at, created_at, updated_at
execution_status_history: id, execution_id, from_status nullable, to_status,
                          actor_type, actor_id, reason nullable, created_at
execution_internal_notes: id, execution_id, staff_id, body, created_at
customer_action_requests: id, execution_id, requested_by, description_en/ar,
                          required_document_purpose nullable, status, due_at nullable, created_at, resolved_at
execution_documents: execution_id, document_id, purpose, attached_at
```

يمنع trigger UPDATE/DELETE على status history وinternal notes. لا يظهر internal note في عقود العميل.

- [ ] **Step 4: نفذ `ExecutionCreator` والربط**

```php
$this->app->bind(
    \Rehla\Purchasing\Contracts\ExecutionCreator::class,
    \Rehla\Fulfillment\Infrastructure\PurchasingExecutionCreator::class,
);
```

يحول `PurchasingExecutionCreator` بيانات منفذ Purchasing إلى Action داخلية. يضيف الإنشاء `received` ونسخة policy المنشورة الملتقطة من Catalog وhistory في معاملة المستدعي بلا commit. يسجل Transition Audit والإشعار الذري وOutbox للقنوات الخارجية. لا تضف `Purchasing -> Fulfillment` إلى Composer؛ Fulfillment هي التي تعتمد على Purchasing لتطبيق المنفذ. إلغاء Execution لا يستدعي Wallet ولاينشئ refund.

- [ ] **Step 5: اختبر الصلاحيات والتاريخ**

اختبر منع الانتقال غير المسموح في policy الملتقطة، ومنع موظف بلا`executions.transition`، ومنع transition بعد completed/cancelled، وحماية history عبر SQL، وبقاء execution القديم على policy القديمة بعد نشر نسخة أحدث. يقبل `CompleteExecution` قيمة `issued_document_id` اختيارية: يلزم Documents بإثبات clean و`issued_document` عندما تكون `captured_policy.requires_issued_document = true`، ويسمح بنتيجة نجاح واحدة بلا مستند عندما تكون false.

Run: `php artisan test packages/Rehla/Fulfillment/tests`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Fulfillment docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(fulfillment): add execution lifecycle and history"
```

### Task 4: Customer Action Requests and Responses

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Fulfillment. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R37`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/Fulfillment/src/Data/RespondToCustomerActionData.php`
- Create: `packages/Rehla/Fulfillment/src/Actions/RespondToCustomerAction.php`
- Create: `packages/Rehla/Fulfillment/src/Models/CustomerActionResponse.php`
- Create: `packages/Rehla/Fulfillment/src/database/migrations/*_create_customer_action_responses.php`
- Test: `packages/Rehla/Fulfillment/tests/Feature/CustomerActionResponseTest.php`

**Interfaces:**
- Consumes: `OwnedDocuments` عندما يطلب الإجراء وثيقة.
- Produces: response مرتبطة بطلب مفتوح ثم transition إلى`action_received`.

- [ ] **Step 1: اكتب اختبار الملكية والاستجابة**

```php
it('lets only the owning account answer an open action request', function (): void {
    $request = openCustomerAction(executionOwnedBy($ownerA), purpose: 'additional_document');
    expect(fn () => respondToAction($ownerB, $request, document: cleanDocument($ownerB)))
        ->toThrow(ActionRequestNotFound::class);

    respondToAction($ownerA, $request, document: cleanDocument($ownerA));
    expect(execution($request)->status)->toBe(ExecutionStatus::RequestedActionReceived);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Fulfillment/tests/Feature/CustomerActionResponseTest.php`

Expected: FAIL قبل action/schema.

- [ ] **Step 3: نفذ الاستجابة الذرية**

أنشئ `customer_action_responses(id, action_request_id unique, account_id, message nullable, document_id nullable, submitted_at)`. يقفل action request، يتحقق أنهopen وowned، ويتطلب message أوclean document حسب الطلب، يغلق الطلب، ينقل Execution ويضيف history/Audit/Outbox داخل معاملة واحدة.

- [ ] **Step 4: اختبر replay والملفات**

أثبت أن الرد الثاني يعيد النتيجة الحالية دون response آخر، وأن pending/rejected document ممنوع، وأن المستند المرفق ينتقل إلىattached.

Run: `php artisan test packages/Rehla/Fulfillment/tests/Feature/CustomerActionResponseTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Fulfillment docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(fulfillment): add customer action responses"
```

### Task 5: Atomic and Idempotent SubmitOrder

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Fulfillment, Orders, Purchasing. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R14, R22, R29, R30, R31, R32, R51, R55, R58, R59`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/Purchasing/src/Actions/SubmitOrder.php`
- Create: `packages/Rehla/Purchasing/src/Exceptions/{PriceChanged,FormVersionChanged,FulfillmentPolicyMissing,IdempotencyKeyReused,OperationInProgress}.php`
- Modify: `packages/Rehla/Purchasing/src/Providers/PurchasingServiceProvider.php`
- Test: `packages/Rehla/Purchasing/tests/Integration/SubmitOrderTest.php`
- Test: `packages/Rehla/Purchasing/tests/Integration/SubmitOrderConcurrencyTest.php`
- Test: `packages/Rehla/Purchasing/tests/Integration/SubmitOrderRollbackTest.php`

**Interfaces:**
- Consumes: locked `ServiceCatalog::currentQuote`, Catalog-owned `PublishedFulfillmentPolicyReader`, `GetPublishedForm::handle`, `FormSubmissionValidator::validate`, `GetOwnedTravelerSnapshot::handle`, `OwnedDocuments::assertCleanOwned`, `WalletDebitor::debit`, `OrderWriter::createPaid`, Purchasing-owned `ExecutionCreator::create`, `AuditWriter::append`, `CreateInAppNotification`, `OutboxWriter::append`.
- Produces: `SubmitOrder::handle(SubmitOrderData): SubmitOrderResult`.

- [ ] **Step 1: اكتب happy-path atomic contract**

```php
it('creates one debit order execution audit and outbox record', function (): void {
    $result = submitValidOrder(idempotencyKey: 'purchase-001');

    expect(debitsFor($result->orderId))->toHaveCount(1)
        ->and(order($result->orderId))->not->toBeNull()
        ->and(executionForOrder($result->orderId))->not->toBeNull()
        ->and(auditEntriesFor($result->orderId))->toHaveCount(1)
        ->and(outboxFor($result->orderId, 'order.submitted'))->toHaveCount(1);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Purchasing/tests/Integration/SubmitOrderTest.php`

Expected: FAIL قبل SubmitOrder.

- [ ] **Step 3: نفذ المعاملة بالترتيب المحدد**

```php
return DB::transaction(function () use ($data, $fingerprint): SubmitOrderResult {
    $this->attempts->insertOrIgnore($data->accountId, $data->idempotencyKey, $fingerprint);
    $attempt = $this->attempts->lockForUpdate($data->accountId, $data->idempotencyKey);
    if (! hash_equals($attempt->requestFingerprint, $fingerprint)) throw new IdempotencyKeyReused();
    if ($attempt->isCompleted()) return $attempt->result();

    $quote = $this->catalog->currentQuote($data->serviceId);
    $form = $this->forms->handle($data->serviceId);
    $policy = $this->fulfillmentPolicies->handle($data->serviceId);
    $traveler = $this->travelers->handle($data->accountId, $data->travelerId);
    $submission = $this->validator->validate($form->id, $data->answers);
    $documents = $this->documents->assertCleanOwned(
        $submission->documentIds,
        $data->accountId,
        $submission->requiredDocumentClassifications,
    );
    $orderId = $this->orders->nextId();
    $debit = $this->wallet->debit(DebitWalletData::forPurchase($orderId, $data, $quote));
    $order = $this->orders->createPaid(CreatePaidOrderData::from($orderId, $data, $quote, $form, $policy, $traveler, $submission, $documents, $debit));
    $execution = $this->executions->create(CreateExecutionData::from($order, $form, $policy, $submission));
    $this->audit->append(AppendAuditData::orderSubmitted($data, $order, $execution));
    $this->outbox->append(OutboxMessageData::orderSubmitted($order, $execution));
    return $this->attempts->complete($attempt, SubmitOrderResult::from($order, $execution));
}, attempts: 3);
```

اكتسب الأقفال بالترتيب: purchase attempt، service/current price، published form، published fulfillment policy، traveler، documents مرتبة، wallet. تشمل fingerprint السعر وإصداره والنموذج والإجابات المرتبة ومعرفات المستندات. قبل debit تحقق من `available=true`, `accepted_price_minor===quote.priceMinor`, `accepted_price_version===quote.version`, و`form_version_id===published.id` ووجود policy منشورة. لا تنفذ external IO داخل closure.

- [ ] **Step 4: اختبر claims ورسائل الأخطاء**

اختبر service disabled، price changed، form changed، traveler غير مملوك، answer ناقص، document غيرclean/غيرمملوك/بتصنيف مخالف، ورصيد غير كاف. أخطاء المستندات تعيد `document.invalid_attachment` من Documents/Purchasing ولا تعيد Forms خطأ ملكية أوscan. توقع الرموز الثابتة وغياب debit/order/execution.

Run: `php artisan test packages/Rehla/Purchasing/tests/Integration/SubmitOrderTest.php`

Expected: PASS.

- [ ] **Step 5: اختبر idempotency والتزامن**

أرسل نفس المفتاح والحمولة مرتين وتوقع Order نفسه؛ نفس المفتاح بحمولة مختلفة يعطي409 domain result؛ مفتاحان متزامنان على رصيد يكفي واحدًا يعطي نجاحًا واحدًا و`INSUFFICIENT_BALANCE` للآخر بلا رصيد سالب.

Run: `php artisan test packages/Rehla/Purchasing/tests/Integration/SubmitOrderConcurrencyTest.php`

Expected: PASS باتصالين PostgreSQL.

- [ ] **Step 6: اختبر rollback عند كل حد**

حقن exception بعد debit، وبعد Order، وبعد Execution، وبعد Audit وقبل Outbox. في كل حالة توقع صفر debit/order/execution/audit/outbox/attempt مكتمل. ثم أعد الطلب وتوقع نجاحه.

Run: `php artisan test packages/Rehla/Purchasing/tests/Integration/SubmitOrderRollbackTest.php`

Expected: PASS.

- [ ] **Step 7: بوابة الخطة وCommit**

Run: `composer verify && git diff --check`

Expected: PASS للـsnapshots والتزامن والفشل والاعتمادات.

```bash
git add packages/Rehla/Orders packages/Rehla/Purchasing packages/Rehla/Fulfillment docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(purchasing): submit orders atomically and idempotently"
```

## Plan Completion Gate

تغلق الخطة `purchase-readiness` بإثبات PostgreSQL ذري للسعر والنموذج والسياسة والمستندات والخصم وOrder وExecution وAudit والإشعار وOutbox، مع idempotency والتزامن وfailure injection. تنتج Orders snapshots وFulfillment read sources التي تستهلكها Reporting والخطط 07–09.
