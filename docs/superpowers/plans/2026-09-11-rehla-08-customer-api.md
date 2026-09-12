# Rehla Customer REST API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** بناء REST API v1 للعملاء بعقد OpenAPI كامل ودقيق للعمليات الثماني والعشرين.

**Architecture:** حزمة Api طبقة نقل Sanctum تستدعي Actions وQueries نفسها، وتفصل Form Requests وResources وProblem Details عن منطق المجال.

**Tech Stack:** Laravel HTTP، Sanctum، OpenAPI 3.1، Pest contract tests.

**Spec:** `specs/contracts/customer-rest-api-v1.md`

**Coverage:** `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`

**Prerequisites:** إغلاق بوابة الخطة 07 وإثبات عقود المجال؛ Web لا يعتمد على API ولا يستعمله كناقل داخلي.

## Global Constraints

- tokens للعملاء فقط ولا تحمل staff abilities؛ كل عملية تعلن auth وrate limit وcontent type.
- كل مورد مملوك يثبت 401 و404 غير الكاشف، و403 للفاعل الذي يعرف المورد بلاقدرة، و409/422 حيث ينطبقان.
- الأخطاء `application/problem+json` برموز محايدة لغويًا و`trace_id` جديد و`correlation_id` منطقي.
- OpenAPI والـroutes والاختبارات لها مجموعة method/path/operationId متساوية تمامًا: 28/28.
- يفشل `PresentationBoundaryTest` عند Models أو`DB::` أوbuilder mutations أوstaff ability على customer token.

---
### Task 1: API Foundation, Customer Authentication and Problem Details

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Identity/Core transport contracts` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R10, R47, R50` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Api/src/routes/api_v1.php`
- Create: `packages/Rehla/Api/src/openapi/rehla-v1.yaml`
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/Auth/{RegisterController,LoginController,LogoutController}.php`
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/MeController.php`
- Create: `packages/Rehla/Api/src/Http/Requests/V1/UpdateMeRequest.php`
- Create: `packages/Rehla/Api/src/Http/Resources/V1/UserResource.php`
- Create: `packages/Rehla/Api/src/Http/Middleware/{RequireJson,ResolveApiLocale,AttachTraceContext}.php`
- Create: `packages/Rehla/Api/src/Errors/ProblemDetailsFactory.php`
- Create: `packages/Rehla/Api/tests/{Feature/AuthApiTest.php,Feature/ProblemDetailsTest.php,Architecture/PresentationBoundaryTest.php}`

**Mandatory Package Contract — Api:**
- Create/verify: `packages/Rehla/Api/composer.json` and `packages/Rehla/Api/README.md`.
- Create/verify: `packages/Rehla/Api/src/Providers/ApiServiceProvider.php`.
- Create/verify: `packages/Rehla/Api/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Api/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Api/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-api`; `ApiServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-api')`.
- يبدأ ملفا اللغة متطابقين ولو فارغين، ويمنع الحارس النص الظاهر الصريح واستيراد Models أو`DB::` أوكتابة العلاقات.

**Interfaces:**
- Routes: `POST /api/v1/auth/register`, `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/me`, `PATCH /api/v1/me`.
- Consumes: Identity registration/auth/profile contracts and Core problem codes; produces customer-only Sanctum tokens and RFC 9457-compatible Problem Details.

- [ ] **Step 1: Write RED proof**

Run auth/problem tests; expect absent provider/routes.

- [ ] **Step 2: Implement the smallest contract**

Implement JSON/content negotiation, login 5/min per email+IP, device token issuance/revocation, profile DTOs, locale-neutral code mapping and trace/correlation rules. Customer tokens reject every staff ability.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove 401/403/404/409/422/429 envelopes, token secrecy/revocation, inactive account, mass assignment, Accept-Language title/detail only, and architecture guard.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Api && git commit -m "feat(api): establish auth profile and problem details"
```


### Task 2: Public Service Catalog Contract

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Catalog/Forms public queries` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R07, R08, R23` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/ServiceController.php`
- Create: `packages/Rehla/Api/src/Http/Resources/V1/{ServiceResource,ApplicationFormResource}.php`
- Modify: `packages/Rehla/Api/src/openapi/rehla-v1.yaml`
- Test: `packages/Rehla/Api/tests/Feature/PublicServiceApiTest.php`

**Interfaces:**
- Routes: `GET /api/v1/services`, `GET /api/v1/services/{service_slug}`, `GET /api/v1/services/{service_slug}/application-form`.
- Consumes: published Catalog and Forms queries; produces paginated public snapshots with no draft/internal fields.

- [ ] **Step 1: Write RED proof**

Run public service API test; expect three missing operations.

- [ ] **Step 2: Implement the smallest contract**

Return active service list/details and current published form, localized data fields, integer minor price, immutable version identifiers, and stable cursor/page contract.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove disabled/draft 404, pagination ≤100, no N+1 budget, schema/resource equality, and content type/rate limits.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Api && git commit -m "feat(api): expose public service catalog"
```


### Task 3: Travelers, Wallet and Bank Accounts

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Travelers/Wallet/TopUps queries and commands` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R11, R12, R15, R17` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/{TravelerController,WalletController,BankAccountController}.php`
- Create: `packages/Rehla/Api/src/Http/Requests/V1/{StoreTravelerRequest,UpdateTravelerRequest}.php`
- Create: `packages/Rehla/Api/src/Http/Resources/V1/{TravelerResource,WalletResource,WalletEntryResource,BankAccountResource}.php`
- Modify: `packages/Rehla/Api/src/openapi/rehla-v1.yaml`
- Test: `packages/Rehla/Api/tests/Feature/{TravelerApiTest,WalletApiTest,BankAccountApiTest}.php`

**Interfaces:**
- Routes: `GET /api/v1/travelers`, `POST /api/v1/travelers`, `GET /api/v1/travelers/{traveler_id}`, `PATCH /api/v1/travelers/{traveler_id}`, `GET /api/v1/wallet`, `GET /api/v1/wallet/entries`, `GET /api/v1/bank-accounts`.
- Consumes: traveler DTO commands/queries, WalletReader, active bank-account query.

- [ ] **Step 1: Write RED proof**

Run the three feature files; expect seven missing operations.

- [ ] **Step 2: Implement the smallest contract**

Use Form Requests to DTOs, cursor/page pagination, integer SDG, masked fields and owner-scoped queries. Bank account output exposes approved transfer instructions only.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove guest 401, other-account 404, duplicate passport 409/422 contract, wallet no mutation, disabled bank omission, pagination and resource allowlists.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Api && git commit -m "feat(api): expose travelers wallet and banks"
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
- **Acceptance IDs:** `R16, R18, R19, R20, R21, R44, R56, R57` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/TopUpController.php`
- Create: `packages/Rehla/Api/src/Http/Requests/V1/{StoreTopUpRequest,ReplaceTopUpReceiptRequest}.php`
- Create: `packages/Rehla/Api/src/Http/Resources/V1/TopUpResource.php`
- Modify: `packages/Rehla/Api/src/openapi/rehla-v1.yaml`
- Test: `packages/Rehla/Api/tests/Feature/TopUpApiTest.php`

**Interfaces:**
- Routes: `GET /api/v1/top-ups`, `POST /api/v1/top-ups`, `GET /api/v1/top-ups/{top_up_id}`, `PUT /api/v1/top-ups/{top_up_id}/receipt`.
- Consumes: TopUp reader/submit/replace commands; replacement preserves request, bank and normalized reference.

- [ ] **Step 1: Write RED proof**

Run TopUpApiTest; expect four missing operations.

- [ ] **Step 2: Implement the smallest contract**

Require clean bank_receipt document, configured minimum, active bank and 10/hour submission. Replace only rejected receipt while request remains reviewable; never create a second transfer reference.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove owner/other-account, duplicate reference, invalid/terminal replacement, idempotent outcomes, 409/422/429 schemas and no wallet credit before approval.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Api && git commit -m "feat(api): expose top-up and receipt replacement"
```


### Task 5: Upload Lifecycle and Private Document Delivery

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Documents contracts` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R06, R48` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/{UploadController,DocumentController}.php`
- Create: `packages/Rehla/Api/src/Http/Requests/V1/StoreUploadRequest.php`
- Create: `packages/Rehla/Api/src/Http/Resources/V1/UploadResource.php`
- Modify: `packages/Rehla/Api/src/openapi/rehla-v1.yaml`
- Test: `packages/Rehla/Api/tests/Feature/{UploadApiTest,PrivateDocumentApiTest}.php`

**Interfaces:**
- Routes: `POST /api/v1/uploads`, `GET /api/v1/uploads/{document_id}`, `GET /api/v1/documents/{document_id}/content`.
- Consumes: Documents upload session, scan-state reader and download authorizer; returns metadata orauthorized stream, never storage path.

- [ ] **Step 1: Write RED proof**

Run upload/document tests; expect three missing operations.

- [ ] **Step 2: Implement the smallest contract**

Enforce transport limit above service limit, MIME/magic/decoder/malware pipeline, 20/hour, ownership and safe delivery headers. Return 413/422 codes and pollable status.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove mismatch/polyglot, pending/quarantined/rejected, account isolation, range/redirect policy, safe filename, nosniff, expired delivery and cleanup claim race.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Api && git commit -m "feat(api): expose secure upload and document delivery"
```


### Task 6: Order Submission, Orders and Execution Actions

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Purchasing/Orders/Fulfillment contracts` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R13, R14, R22, R25, R26, R28, R29, R30, R31, R32, R33, R34, R35, R36, R37, R38, R51, R54, R55, R58, R59` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/{OrderSubmissionController,OrderController,ExecutionActionController}.php`
- Create: `packages/Rehla/Api/src/Http/Requests/V1/{SubmitOrderRequest,RespondToActionRequest}.php`
- Create: `packages/Rehla/Api/src/Http/Resources/V1/OrderResource.php`
- Modify: `packages/Rehla/Api/src/openapi/rehla-v1.yaml`
- Test: `packages/Rehla/Api/tests/Feature/{OrderSubmissionApiTest,OrderApiTest,ExecutionActionApiTest}.php`

**Interfaces:**
- Routes: `POST /api/v1/order-submissions`, `GET /api/v1/orders`, `GET /api/v1/orders/{order_reference}`, `POST /api/v1/executions/{execution_id}/actions/{action_request_id}/responses`.
- Consumes: `SubmitOrder`, OrderReader, ExecutionReader and customer-action command.

- [ ] **Step 1: Write RED proof**

Run the three feature files; expect four missing operations.

- [ ] **Step 2: Implement the smallest contract**

Require `Idempotency-Key`; map DTOs only; return 201 first, 200 plus `Idempotency-Replayed: true` for same fingerprint, 409 for reuse mismatch. Expose paid and execution statuses separately and hide internal notes.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove price/form/policy conflicts, balance/files/ownership, concurrency and rollback, pagination, other-account 404, action pending/response conflicts, and exact immutable snapshots.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Api && git commit -m "feat(api): expose purchase orders and execution actions"
```


### Task 7: Customer Notifications

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `Notifications contracts` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R39` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/NotificationController.php`
- Create: `packages/Rehla/Api/src/Http/Resources/V1/NotificationResource.php`
- Modify: `packages/Rehla/Api/src/openapi/rehla-v1.yaml`
- Test: `packages/Rehla/Api/tests/Feature/NotificationApiTest.php`

**Interfaces:**
- Routes: `GET /api/v1/notifications`, `POST /api/v1/notifications/{notification_id}/read`.
- Consumes: NotificationReader and owner-scoped mark-read command; produces sanitized paginated notifications.

- [ ] **Step 1: Write RED proof**

Run NotificationApiTest; expect two missing operations.

- [ ] **Step 2: Implement the smallest contract**

Return stable notification type and localized payload keys with no private raw body, paginate ≤100, and make mark-read idempotent.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove guest 401, other-account 404, owner success, unread counter, replay-safe read and resource allowlist.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Api && git commit -m "feat(api): expose customer notifications"
```


### Task 8: OpenAPI Equality and Transport Release Gate

**Task Completeness Contract:**
- **Files:** القائمة exhaustive لهذه المهمة؛ يحدث أي ملف إضافي الخطة وسجل القبول أولًا.
- **Contracts:** Interfaces أدناه هي الحدود الوحيدة؛ لا mutable Models ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** حزمة العرض بلا جداول أعمال؛ `all 28 API operations` يستدعي Queries/Actions المالكة فقط ولا ينشئ migration.
- **Authorization:** deny-by-default مع guest/owner/other-account/staff outcomes حسب المسار.
- **Localization:** النص المرئي من مفاتيح EN/AR متكافئة، ورموز API محايدة لغويًا.
- **Error codes:** lower dot notation عبر Core/Problem Details ولا تستخدم exception message كهوية عامة.
- **Transaction boundary:** العرض لا يفتح معاملة أعمال؛ Action المالكة تحدد atomicity ويثبت الاختبار عدم الكتابة المباشرة.
- **External I/O:** لا network I/O داخل transaction؛ الاستفسار read-only والتسليم الخارجي عبر Outbox.
- **Privacy:** DTO allowlist و404 غير كاشف، بلا storage keys أوpassport كامل أوsecrets أوinternal notes.
- **RED:** شغل الاختبار المركز أولًا وأثبت سبب الفشل المتوقع.
- **GREEN:** نفذ أقل route/component/controller يحقق العقد.
- **Expanded verification:** شغل اختبارات الحزمة وArchitecture والمجالات المستهلكة ثم formatter وأعد المتأثر.
- **Acceptance IDs:** `R50, R64, R65` مع command وtest name وresult وartifact قبل `verified`.
- **Recovery:** عطل route/component عند الطوارئ ولا تعدل أوتحذف سجلات أعمال ناجحة.
- **Commit:** نفذ الرسالة المحددة بعد `git diff --check` وبلا خلط مهمة أخرى.

**Files:**
- Modify: `packages/Rehla/Api/src/openapi/rehla-v1.yaml`
- Create: `packages/Rehla/Api/tests/Contract/{OpenApiRouteEqualityTest,OpenApiExamplesTest,OperationPolicyMatrixTest}.php`
- Create: `packages/Rehla/Api/tests/Architecture/VisibleTextAndModelBoundaryTest.php`
- Test: `packages/Rehla/Api/tests/Feature/CompleteApiMatrixTest.php`

**Interfaces:**
- Consumes: Tasks 1–7 route registry and REST contract.
- Produces: exact set equality for 28 method/path/operationId rows, policy/rate/content/error matrix and executable examples.

- [ ] **Step 1: Write RED proof**

Run contract suite with one operation intentionally absent from fixture; expect exact missing method/path/operationId.

- [ ] **Step 2: Implement the smallest contract**

Generate policy datasets from the canonical matrix, validate OpenAPI 3.1 schemas/examples/security/responses, and reject undocumented routes, staff abilities, Models, DB mutations and locale-dependent codes.

- [ ] **Step 3: Verify focused and expanded behavior**

Run all Api tests plus API/package/architecture documentation guards. Assert 28/28 unique operations; each has controller, request for mutation, resource, Action/Query, auth, ownership, rate, errors and operationId.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Api && git commit -m "test(api): prove complete OpenAPI and policy equality"
```

## Final API Gate

تثبت البوابة Tasks 1–8 ومساواة العمليات الـ28 التالية بين REST spec وroutes وOpenAPI والاختبارات. يلزم customer-only Sanctum abilities وrate/content policies وProblem Details والملكية وidempotency والuploads والتنزيل واستبدال الإيصال. تنتج الخطة عقد API قابلاً للإصدار، وتبقى Admin مستهلكة لعقود المجالات لا لهذا الناقل.
