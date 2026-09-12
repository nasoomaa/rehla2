# Rehla Customer Web Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** بناء تجربة Web كاملة للعميل من اكتشاف الخدمة حتى متابعة التنفيذ والمستندات والإشعارات بالإنجليزية والعربية.

**Architecture:** حزمة Web طبقة عرض session-based تستدعي Actions وQueries وDTOs العامة للحزم مباشرة ولا تستورد Models أوتستدعي `/api/v1` داخليًا.

**Tech Stack:** Laravel Sessions، Blade، Livewire، Tailwind CSS، Pest، Playwright.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

**Coverage:** `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`

**Prerequisites:** إغلاق بوابة الخطة 06؛ توفر جميع عقود المجال وReporting وIntegrations اللازمة للقراءة والاستفسار.

## Global Constraints

- كل route مملوك يثبت guest وowner وother-account outcomes مع 404 غير كاشف.
- CSRF وتجديد session إلزاميان، ولا تكتب Components أوControllers إلى جداول الأعمال.
- كل نص من translation key ثنائي، وتشمل البوابة AR/RTL ولوحة المفاتيح والاستجابة والوصول.
- الاستفسار عبر WhatsApp لا ينشئ Order أوDebit أوExecution.
- يفشل `PresentationBoundaryTest` عند استيراد `Models` أو`DB::` أوHTTP call إلى `/api/v1` أونص ظاهر صريح خارج allowlist.

---
### Task 1: Public Catalog, Content and Inquiry

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Catalog/Content/Integrations read contracts` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R23` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Web/src/routes/web.php`
- Create: `packages/Rehla/Web/src/Http/Controllers/{HomeController,ServiceController,ContentController,LocaleController,InquiryController}.php`
- Create: `packages/Rehla/Web/src/ViewModels/{ServiceCardView,ServiceDetailsView,ContentPageView}.php`
- Create: `packages/Rehla/Web/src/resources/views/{layouts,public}/**/*.blade.php`
- Create: `packages/Rehla/Web/tests/Feature/{PublicCatalogTest,ContentPageTest,InquiryLinkTest}.php`
- Create: `packages/Rehla/Web/tests/Architecture/PresentationBoundaryTest.php`

**Mandatory Package Contract — Web:**
- Create/verify: `packages/Rehla/Web/composer.json` and `packages/Rehla/Web/README.md`.
- Create/verify: `packages/Rehla/Web/src/Providers/WebServiceProvider.php`.
- Create/verify: `packages/Rehla/Web/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Web/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Web/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-web`; `WebServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-web')`.
- يبدأ ملفا اللغة متطابقين ولو فارغين، ويمنع الحارس النص الظاهر الصريح واستيراد Models أو`DB::` أوكتابة العلاقات.

**Interfaces:**
- Consumes: `ListPublishedServices`, `GetServiceDetails`, `PublishedContentReader`, `InquiryLinkBuilder`.
- Produces: `/`, `/services`, `/services/{slug}`, `/pages/{slug}`, `/locale/{locale}`, `/inquiries/{service}` with no business side effect.

- [ ] **Step 1: Write RED proof**

Run `php artisan test packages/Rehla/Web/tests/Feature/PublicCatalogTest.php`; expect missing routes/views.

- [ ] **Step 2: Implement the smallest contract**

Render bilingual service facts, price, requirements, media alt text, duration and notes from view models. Locale accepts `en|ar` only. Inquiry uses the configured builder and remains read-only.

- [ ] **Step 3: Verify focused and expanded behavior**

Run public/content/inquiry tests and `PresentationBoundaryTest`; assert disabled services hidden, no order/debit/execution from inquiry, localized 404, CSP-safe links, and zero direct DB/API transport.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Web && git commit -m "feat(web): add public catalog content and inquiry"
```


### Task 2: Customer Authentication and Account Shell

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Identity session contracts` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R10` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Web/src/Livewire/Auth/{Register,Login}.php`
- Create: `packages/Rehla/Web/src/Livewire/Account/{Shell,Profile}.php`
- Create: `packages/Rehla/Web/src/resources/views/livewire/{auth,account}/**/*.blade.php`
- Modify: `packages/Rehla/Web/src/resources/lang/{en,ar}/messages.php`
- Test: `packages/Rehla/Web/tests/Feature/{CustomerAuthTest,AccountIsolationTest}.php`

**Interfaces:**
- Consumes: `RegisterCustomer`, `GetCurrentUser`, profile update command.
- Produces: `/register`, `/login`, `/logout`, `/account`, `/account/profile` with customer session guard.

- [ ] **Step 1: Write RED proof**

Run `CustomerAuthTest`; expect missing Livewire components and routes.

- [ ] **Step 2: Implement the smallest contract**

Implement validation DTO mapping, password hashing through Identity, session regeneration after login, CSRF, logout invalidation/token regeneration, and localized account navigation.

- [ ] **Step 3: Verify focused and expanded behavior**

Run auth/isolation tests for inactive account, rate limit, session fixation, customer denied `/admin`, two accounts, and atomic registration closure from plan 04.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Web && git commit -m "feat(web): add customer auth and account shell"
```


### Task 3: Traveler and Wallet Views

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Travelers/Wallet contracts` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R11, R12, R15` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Web/src/Livewire/Account/{TravelerIndex,TravelerForm,WalletOverview}.php`
- Create: `packages/Rehla/Web/src/resources/views/livewire/account/{traveler-index,traveler-form,wallet-overview}.blade.php`
- Modify: `packages/Rehla/Web/src/resources/lang/{en,ar}/messages.php`
- Test: `packages/Rehla/Web/tests/Feature/{TravelerJourneyTest,WalletViewTest}.php`

**Interfaces:**
- Consumes: traveler create/update/list/get commands and `WalletReader`.
- Produces: `/account/travelers`, create/edit traveler routes, `/account/wallet`; no money mutation.

- [ ] **Step 1: Write RED proof**

Run traveler/wallet feature tests; expect missing components.

- [ ] **Step 2: Implement the smallest contract**

Map forms to DTOs, normalize passport in owner domain, paginate entries, format integer SDG, mask passport outside detail, and keep wallet read-only.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove guest redirects, owner success, other-account 404, duplicate passport safe message, validation EN/AR, pagination, and no float orledger mutation.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Web && git commit -m "feat(web): add traveler and wallet views"
```


### Task 4: Top-Up Submission and Receipt Replacement

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `TopUps/Documents contracts` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R16, R17, R18, R19, R20, R21, R44, R56, R57` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Web/src/Livewire/Account/{TopUpIndex,TopUpCreate,TopUpShow,TopUpReceiptReplacement}.php`
- Create: `packages/Rehla/Web/src/resources/views/livewire/account/{top-up-index,top-up-create,top-up-show,top-up-receipt-replacement}.blade.php`
- Modify: `packages/Rehla/Web/src/resources/lang/{en,ar}/messages.php`
- Test: `packages/Rehla/Web/tests/Feature/{TopUpJourneyTest,ReceiptReplacementTest}.php`

**Interfaces:**
- Consumes: bank-account/top-up queries, document upload session, `SubmitTopUp`, `ReplaceTopUpReceipt`.
- Produces: `/account/top-ups`, create/show, and receipt replacement for the same under-review request.

- [ ] **Step 1: Write RED proof**

Run top-up and replacement tests; expect missing workflow.

- [ ] **Step 2: Implement the smallest contract**

Show configured minimum and active banks; upload receipt through Documents; submit normalized reference; replace only rejected receipt while preserving top-up ID, bank and reference.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove clean-file requirement, size/type failures, duplicate reference conflict, owner/other-account behavior, terminal replacement denial, old receipt retention policy, and no premature wallet credit.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Web && git commit -m "feat(web): add top-up and receipt replacement journey"
```


### Task 5: Service Checkout and Atomic Submission

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Catalog/Forms/Travelers/Documents/Purchasing contracts` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R14, R22, R25, R26, R27, R28, R29, R30, R31, R32, R55, R58, R59` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Web/src/Livewire/Account/OrderCheckout.php`
- Create: `packages/Rehla/Web/src/resources/views/livewire/account/order-checkout.blade.php`
- Modify: `packages/Rehla/Web/src/resources/lang/{en,ar}/messages.php`
- Test: `packages/Rehla/Web/tests/Feature/PurchaseJourneyTest.php`
- Test: `packages/Rehla/Web/tests/Integration/PurchaseRetryPresentationTest.php`

**Interfaces:**
- Consumes: quote/form/policy readers, owned travelers/documents, `SubmitOrder`.
- Produces: `/account/services/{service}/checkout`; one confirmation creates one paid Order and one Execution atomically.

- [ ] **Step 1: Write RED proof**

Run purchase journey; expect missing checkout component.

- [ ] **Step 2: Implement the smallest contract**

Render captured quote/form, choose one owned traveler, upload required documents, generate stable idempotency key per logical submit, map conflicts to actionable confirmation without duplicating domain logic.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove price/form/policy change, validation, ownership, insufficient balance, double click, retry, concurrent submit, and that Web never opens a DB transaction orcalls `/api/v1`.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Web && git commit -m "feat(web): add atomic service checkout"
```


### Task 6: Order, Execution and Customer-Action Tracking

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Orders/Fulfillment contracts` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R13, R33, R34, R35, R36, R37, R38, R45, R51, R54` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Web/src/Livewire/Account/{OrderIndex,OrderShow,CustomerActionResponse}.php`
- Create: `packages/Rehla/Web/src/resources/views/livewire/account/{order-index,order-show,customer-action-response}.blade.php`
- Modify: `packages/Rehla/Web/src/resources/lang/{en,ar}/messages.php`
- Test: `packages/Rehla/Web/tests/Feature/{OrderTrackingTest,CustomerActionJourneyTest}.php`

**Interfaces:**
- Consumes: `OrderReader`, `ExecutionReader`, customer action response command.
- Produces: `/account/orders`, `/account/orders/{reference}`, action-response interaction with paid and operational status shown separately.

- [ ] **Step 1: Write RED proof**

Run tracking/action tests; expect missing pages.

- [ ] **Step 2: Implement the smallest contract**

Display immutable order snapshot, execution history, prominent action-required banner, bilingual instructions and clean-document response. Hide internal notes and staff identities.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove owner/other-account 404, terminal states, repeated response conflict, attachment ownership, automatic `action_received`, and separate paid/execution badges.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Web && git commit -m "feat(web): add order execution and action tracking"
```


### Task 7: Private Documents and In-App Notifications

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Documents/Notifications contracts` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R39, R48` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Web/src/Http/Controllers/DocumentDownloadController.php`
- Create: `packages/Rehla/Web/src/Livewire/Account/NotificationIndex.php`
- Create: `packages/Rehla/Web/src/resources/views/livewire/account/notification-index.blade.php`
- Modify: `packages/Rehla/Web/src/resources/lang/{en,ar}/messages.php`
- Test: `packages/Rehla/Web/tests/Feature/{PrivateDocumentDownloadTest,NotificationJourneyTest}.php`

**Interfaces:**
- Consumes: `DocumentDownloadAuthorizer`, authorized stream/short-lived delivery, `NotificationReader`, mark-read command.
- Produces: `/account/documents/{id}/content`, `/account/notifications`, mark-read action.

- [ ] **Step 1: Write RED proof**

Run document/notification tests; expect missing controller/component.

- [ ] **Step 2: Implement the smallest contract**

Authorize every download at request time, set safe headers, never expose storage key, paginate notifications, sanitize payload view model, and mark only owned notifications read.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove pending/rejected/other-account denial, safe filename/content type/nosniff, expired links, unread counts, idempotent mark-read, and no notification body PII leak.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Web && git commit -m "feat(web): add private documents and notifications"
```


### Task 8: Bilingual Accessible Browser Acceptance

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `complete Web journey` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R52, R53, R61, R63, R65` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `tests/EndToEnd/customer-web-journey.spec.ts`
- Create: `tests/EndToEnd/customer-web-accessibility.spec.ts`
- Create: `tests/EndToEnd/customer-web-responsive.spec.ts`
- Modify: `package.json`
- Test: `packages/Rehla/Web/tests/Architecture/VisibleTextLocalizationTest.php`

**Interfaces:**
- Consumes: Tasks 1–7 and deterministic fixtures.
- Produces: browser evidence for EN, AR/RTL, keyboard, responsive layouts, focus/error semantics, and the complete customer journey.

- [ ] **Step 1: Write RED proof**

Run the three browser specs and localization guard; expect missing complete journey evidence.

- [ ] **Step 2: Implement the smallest contract**

Add semantic landmarks, labels, focus management, error summaries, RTL layout rules and deterministic selectors. Cover discovery→register→traveler→top-up→approval fixture→checkout→tracking→action→completion→download.

- [ ] **Step 3: Verify focused and expanded behavior**

Run Web feature/architecture suites and browser specs at mobile/desktop in EN/AR; capture axe results, screenshots only as evidence, and assert no horizontal overflow orkeyboard trap.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Web tests/EndToEnd package.json && git commit -m "test(web): prove bilingual accessible customer journey"
```

## Final Web Gate

تغطي البوابة Tasks 1–8: التصفح والمحتوى والاستفسار والمصادقة والحساب والمسافرين والمحفظة والشحن واستبدال الإيصال والشراء ومتابعة Order/Execution وإجراءات العميل والمستندات والإشعارات. يلزم نجاح owner isolation وCSRF وPresentationBoundary وEN وAR/RTL ولوحة المفاتيح والاستجابة والوصول. تنتج النتيجة baseline لسلوك العميل تقارن به خطة API دون أن تستدعي Web ذلك الناقل.
