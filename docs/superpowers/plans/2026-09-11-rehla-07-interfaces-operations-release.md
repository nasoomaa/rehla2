# Rehla Interfaces, Operations and Release Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** بناء Web وREST API v1 ولوحة Filament فوق عقود المجالات، ثم إثبات التشغيل والأمان والاستعادة ورحلة الإصدار الأول الكاملة.

**Architecture:** الواجهات الثلاث طبقات عرض مستقلة تستعمل Actions وQueries نفسها. تعرض API عقد OpenAPI ثابتًا، وتستعمل Admin read models وإجراءات صريحة، ويشغل التطبيق artifact واحدًا مع web وqueue وscheduler processes.

**Tech Stack:** Blade، Livewire، Tailwind CSS، Sanctum، OpenAPI3.1، Filament5، Laravel HTTP testing، Playwright، PostgreSQL18.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

## Global Constraints

- أكمل خطط 01–06 أولًا.
- Web/API/Admin ممنوعة من DB writes وEloquent Models التابعة لحزم أخرى.
- كل route حساس يثبت 401 و403 و404؛ لا يكشف404 وجود مورد لحساب آخر.
- mutating API routes تستخدم Form Requests، وتعيد الأخطاء بعقد `application/problem+json` ورمز ثابت.
- واجهات EN وAR/RTL ولوحة المفاتيح جزء من القبول وليست تنسيقًا اختياريًا.

---

### Task 1: Public Web, Authentication and Account Shell

**Files:**
- Create: `packages/Rehla/Web/routes/web.php`
- Create: `packages/Rehla/Web/src/Http/Controllers/{HomeController,ServiceController,LocaleController}.php`
- Create: `packages/Rehla/Web/src/Livewire/Auth/{Register,Login}.php`
- Create: `packages/Rehla/Web/src/Livewire/Account/{Profile,TravelerIndex,TravelerForm,WalletOverview,TopUpIndex,OrderIndex,NotificationIndex}.php`
- Create: `packages/Rehla/Web/resources/views/{layouts,public,livewire}/**/*.blade.php`
- Create: `packages/Rehla/Web/resources/lang/{en,ar}/messages.php`
- Test: `packages/Rehla/Web/tests/Feature/PublicWebTest.php`
- Test: `packages/Rehla/Web/tests/Feature/AccountIsolationTest.php`

**Interfaces:**
- Consumes: Catalog/Content queries، `RegisterCustomer`, Identity queries،Traveler/Wallet/TopUp/Order/Notification queries،`InquiryLinkBuilder`.
- Produces: public service pages وauthenticated account routes بلا business writes مباشرة.

- [ ] **Step 1: اكتب route tests العامة والحساب**

```php
it('shows service facts and keeps WhatsApp separate from ordering', function (): void {
    $service = publishedService();
    get("/services/{$service->slug}")
        ->assertOk()
        ->assertSee($service->name_en)
        ->assertSee(formatSdg($service->price_minor))
        ->assertSee('Order Now')
        ->assertSee('wa.me');
    expect(orderCount())->toBe(0)->and(totalDebits())->toBe(0)->and(executionCount())->toBe(0);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Web/tests/Feature/PublicWebTest.php`

Expected: FAIL قبل routes/views.

- [ ] **Step 3: نفذ الصفحات العامة والمصادقة**

أنشئ `/`, `/services`, `/services/{slug}`, `/locale/{locale}`, `/register`, `/login`, `/logout`. تعرض details الصور والاسم والوصف والسعر والمتطلبات والمدة والملاحظات وزري Order Now وWhatsApp. تسجيل العميل يستدعي `RegisterCustomer`; الدخول يستخدم session regeneration والخروج session invalidation/CSRF token regeneration.

- [ ] **Step 4: نفذ account shell والقراءات**

أنشئ `/account/profile`, `/account/travelers`, `/account/wallet`, `/account/top-ups`, `/account/orders`, `/account/notifications`. كل component يستدعي Query مملوكة للحزمة، ويستخدم pagination، ولا يستورد `Models`.

- [ ] **Step 5: أثبت العزل**

اختبر guest redirect، وCSRF، وحسابين، و404 لمعرف الآخر، وعدم ظهور internal notes أوstorage keys أوpassport كامل في قوائم غير لازمة.

Run: `php artisan test packages/Rehla/Web/tests/Feature`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Web
git commit -m "feat(web): add public catalog and customer account shell"
```

### Task 2: Web Top-up, Purchase and Order Tracking Journeys

**Files:**
- Create: `packages/Rehla/Web/src/Livewire/Account/{TopUpCreate,OrderCheckout,OrderShow,CustomerActionResponse}.php`
- Create: `packages/Rehla/Web/resources/views/livewire/account/{top-up-create,order-checkout,order-show,customer-action-response}.blade.php`
- Test: `packages/Rehla/Web/tests/Feature/TopUpJourneyTest.php`
- Test: `packages/Rehla/Web/tests/Feature/PurchaseJourneyTest.php`
- Test: `packages/Rehla/Web/tests/Feature/CustomerActionJourneyTest.php`

**Interfaces:**
- Consumes: BeginUpload/StoreUpload،SubmitTopUp،Create/UpdateTraveler،GetPublishedForm،SubmitOrder،GetOwnedOrder،RespondToCustomerAction.
- Produces: رحلة بلا drafts؛ Livewire state مؤقت فقط حتى submit.

- [ ] **Step 1: اكتب اختبار رحلة الشحن**

```php
it('submits a top-up with a private clean receipt', function (): void {
    Livewire::actingAs($customer)->test(TopUpCreate::class)
        ->set('amount', '5000.00')
        ->set('bankAccountId', $bank->id)
        ->set('reference', 'TRX-1001')
        ->set('receipt', UploadedFile::fake()->image('receipt.jpg'))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect('/account/top-ups');

    expect(latestTopUp()->status)->toBe(TopUpStatus::UnderReview);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Web/tests/Feature/TopUpJourneyTest.php`

Expected: FAIL قبل components.

- [ ] **Step 3: نفذ top-up form**

يعرض الحد الأدنى والبنوك النشطة وبيانات البنك، يرفع receipt إلى lifecycle الخاص، وينتظر clean status قبل `SubmitTopUp`. يعرض حالة under review/approved/rejected وسبب الرفض للمالك.

- [ ] **Step 4: اكتب ونفذ checkout بلا draft**

يختار المسافر، ويحمل published form وquote، ويرسم الأنواع11، ويرفع المستندات، ويحفظ `accepted_price_minor`, `accepted_price_version`, `form_version_id` في state. يولد UUID idempotency key عند فتح submit state ويحافظ عليه لكل retry لنفس النقرة. يستدعي SubmitOrder مرة واحدة عند final submit. لا يكتب Order عند mount أوfield update.

Run: `php artisan test packages/Rehla/Web/tests/Feature/PurchaseJourneyTest.php`

Expected: PASS لحالات insufficient balance وprice changed وform changed وsuccess وdouble click.

- [ ] **Step 5: نفذ tracking والرد**

تعرض Order snapshot والسعر والتاريخ وExecution status وlast update وaction request. لا تعرض internal notes. يرسل `CustomerActionResponse` message/document ويحدث الحالة بعد نجاح Action.

Run: `php artisan test packages/Rehla/Web/tests/Feature/CustomerActionJourneyTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Web docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(web): add top-up purchase and fulfillment journeys"
```

### Task 3: API Foundation, Authentication and Problem Details

**Files:**
- Create: `packages/Rehla/Api/routes/api_v1.php`
- Create: `packages/Rehla/Api/openapi/rehla-v1.yaml`
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/Auth/{RegisterController,LoginController,LogoutController}.php`
- Create: `packages/Rehla/Api/src/Http/Middleware/{RequireJson,ResolveApiLocale}.php`
- Create: `packages/Rehla/Api/src/Errors/ProblemDetailsFactory.php`
- Create: `packages/Rehla/Api/src/Http/Resources/V1/UserResource.php`
- Test: `packages/Rehla/Api/tests/Contract/OpenApiContractTest.php`
- Test: `packages/Rehla/Api/tests/Feature/AuthApiTest.php`
- Test: `packages/Rehla/Api/tests/Feature/ProblemDetailsTest.php`

**Interfaces:**
- Produces: `/api/v1` JSON API،Sanctum bearer tokens،problem details ثابتة.

- [ ] **Step 1: اكتب اختبار error envelope**

```php
it('returns stable problem details independent of locale', function (): void {
    postJson('/api/v1/order-submissions', [])->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://rehla.example/problems/unauthenticated')
        ->assertJsonPath('code', 'UNAUTHENTICATED')
        ->assertJsonStructure(['type', 'title', 'status', 'code', 'trace_id']);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Api/tests/Feature/ProblemDetailsTest.php`

Expected: FAIL قبل API provider/routes.

- [ ] **Step 3: نفذ auth وmiddleware**

`POST /auth/register` يستدعي RegisterCustomer ثم يصدر token باسم الجهاز وبـabilities customer فقط. `POST /auth/login` يتحقق من password/status ويعمل rate limit 5/minute لكل email+IP. `POST /auth/logout` يلغي token الحالي. لا تعيد token في logs أوerrors.

- [ ] **Step 4: نفذ ProblemDetails mapping**

اربط رموز Core بـHTTP: unauthenticated401، forbidden403، not found404، price/form/idempotency conflict409، validation422، rate limit429. `title/detail` مترجمان وفق `Accept-Language` و`code` ثابت غير مترجم. أخف stack traces في production.

- [ ] **Step 5: اكتب OpenAPI الأساس واختبره**

عرّف OpenAPI3.1، bearer auth،ProblemDetails وValidationProblem،pagination وlanguage header. اجعل contract test يحلل YAML ويطابق routes المسجلة ويمنع route بلاoperationId/responses/security.

Run: `php artisan test packages/Rehla/Api/tests/Contract packages/Rehla/Api/tests/Feature/AuthApiTest.php packages/Rehla/Api/tests/Feature/ProblemDetailsTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Api
git commit -m "feat(api): establish v1 auth and problem details contract"
```

### Task 4: API Resources, Uploads and Mutations

**Files:**
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/{MeController,ServiceController,TravelerController,WalletController,BankAccountController,TopUpController,UploadController,DocumentController,OrderSubmissionController,OrderController,ExecutionActionController,NotificationController}.php`
- Create: `packages/Rehla/Api/src/Http/Requests/V1/{UpdateMeRequest,StoreTravelerRequest,UpdateTravelerRequest,StoreTopUpRequest,StoreUploadRequest,SubmitOrderRequest,RespondToActionRequest}.php`
- Create: `packages/Rehla/Api/src/Http/Resources/V1/{ServiceResource,TravelerResource,WalletResource,WalletEntryResource,BankAccountResource,TopUpResource,OrderResource,NotificationResource}.php`
- Modify: `packages/Rehla/Api/openapi/rehla-v1.yaml`
- Test: `packages/Rehla/Api/tests/Feature/ApiResourceAuthorizationTest.php`
- Test: `packages/Rehla/Api/tests/Feature/ApiMutationTest.php`
- Test: `packages/Rehla/Api/tests/Contract/OpenApiExamplesTest.php`

**Interfaces:**
- Consumes: كل Actions/Queries العامة التي استعملتها Web؛ لاModels داخلية.
- Produces: المسارات المحددة أدناه.

- [ ] **Step 1: أضف route matrix واختبار authorization مولدًا منها**

```text
POST auth/register                     public 5/min
POST auth/login                        public 5/min
POST auth/logout                       auth
GET|PATCH me                           auth owner
GET services                           public
GET services/{service}                 public
GET services/{service}/application-form public
GET|POST travelers                     auth owner
GET|PATCH travelers/{traveler}         auth owner
GET wallet                             auth owner
GET wallet/entries                     auth owner
GET bank-accounts                      auth
GET|POST top-ups                       auth owner; POST 10/hour
GET top-ups/{topUp}                    auth owner
POST uploads                           auth owner 20/hour
GET documents/{document}/content       auth owner/policy
POST order-submissions                 auth owner 10/min + Idempotency-Key
GET orders                             auth owner
GET orders/{order}                     auth owner
POST executions/{execution}/actions/{actionRequest}/responses auth owner
GET notifications                      auth owner
POST notifications/{notification}/read auth owner
```

لكل owner route شغل dataset: guest→401، authenticated بلا ملكية→404، المالك→success، actor غير مخول إداريًا→403 حيث يوجد role gate.

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Api/tests/Feature/ApiResourceAuthorizationTest.php`

Expected: FAIL للمسارات غير المنفذة.

- [ ] **Step 3: نفذ Resources والقراءات**

استعمل cursor أوpage pagination بعقد ثابت `{data,links,meta}`. أخف normalized passport وstorage key وinternal notes وpassword hashes. يرسل DocumentController streamed response خاصًا بعد query التفويض.

- [ ] **Step 4: نفذ mutations وIdempotency-Key**

Form Requests تحول payload إلى DTO. `OrderSubmissionController` يرفض header غائبًا بـ422، ويمرر المفتاح إلى SubmitOrder؛ الإنشاء الأول201، replay المطابق200 مع `Idempotency-Replayed: true`، التعارض409. Upload لا يتجاوز حدود النوع والحجم، وTopUp لا يقبل document غيرclean.

- [ ] **Step 5: أكمل OpenAPI واختبر الأمثلة**

لكل route عرف request/response schemas و401/403/404/409/422/429 المناسب. يشغل `OpenApiExamplesTest` كل مثال request صالحًا ضد route ويقارن response بالschema.

Run: `php artisan test packages/Rehla/Api/tests`

Expected: PASS لكل route والعقد.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Api docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(api): expose complete versioned Rehla API"
```

### Task 5: Filament Operations Panel

**Files:**
- Create: `packages/Rehla/Admin/src/Providers/RehlaAdminPanelProvider.php`
- Create: `packages/Rehla/Admin/src/Resources/**`
- Create: `packages/Rehla/Admin/src/Pages/Overview.php`
- Create: `packages/Rehla/Admin/src/Actions/{ApproveTopUpAction,RejectTopUpAction,TransitionExecutionAction,RequestCustomerActionAction,PublishServiceAction,PublishFormAction}.php`
- Create: `packages/Rehla/Admin/src/ReadModels/**`
- Create: `packages/Rehla/Admin/src/Policies/**`
- Test: `packages/Rehla/Admin/tests/Feature/AdminCapabilityMatrixTest.php`
- Test: `packages/Rehla/Admin/tests/Feature/AdminActionsTest.php`
- Test: `packages/Rehla/Admin/tests/Architecture/NoDirectBusinessWritesTest.php`

**Interfaces:**
- Consumes: domain Queries/ReadModels وActions فقط.
- Produces: 14قسمًا بصلاحيات مستقلة وحقول حساسة محدودة.

- [ ] **Step 1: اكتب capability matrix كـdataset**

```php
dataset('admin sections', [
    ['overview', 'reporting.view', 'view'],
    ['services', 'services.manage', 'mutate-via-action'],
    ['application-forms', 'forms.manage', 'mutate-via-action'],
    ['customers', 'customers.view', 'masked-sensitive'],
    ['travelers', 'travelers.view', 'masked-passport'],
    ['wallets', 'wallets.view', 'read-only'],
    ['bank-accounts', 'bank_accounts.manage', 'mutate-via-action'],
    ['top-up-requests', 'topups.review', 'approve-reject-action'],
    ['orders', 'orders.view', 'read-only'],
    ['service-executions', 'executions.manage', 'transition-action'],
    ['content', 'content.manage', 'mutate-via-action'],
    ['notifications', 'notifications.manage', 'replay-action'],
    ['roles-permissions', 'roles.manage', 'mfa-required'],
    ['audit-log', 'audit.view', 'read-only'],
]);
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Admin/tests/Feature/AdminCapabilityMatrixTest.php`

Expected: FAIL قبل panel/resources.

- [ ] **Step 3: أنشئ panel وread-only projections**

استخدم guard `admin` وpath `/admin`. كل Resource يرتبط ReadModel لا ينفذ `save/update/delete/create`، أوPage تعتمد Query DTO. يمنع static guard `DB::`, `Model::query`, `->save`, `->update`, `->delete` داخل Admin باستثناء migrations غير الموجودة أصلًا.

- [ ] **Step 4: اربط mutations بالـActions**

Approve/Reject TopUp تستدعيان domain actions وتطلبان MFA حديثة. service/form/content actions تستدعي الحزم المالكة. Execution transition/request action تستدعي Fulfillment. لا تعدل Filament form record مباشرة.

- [ ] **Step 5: اختبر حساسية الحقول**

اخف receipt/passport/customer PII عمن لا يملك القدرة الموافقة، واعرضها عبر download action مؤقت ومدقق. Wallet وOrders وAudit read-only دائمًا. اختبر كل صف matrix بموظف يملك القدرة وآخر لا يملكها.

Run: `php artisan test packages/Rehla/Admin/tests`

Expected: PASS لكل 14قسمًا والإجراءات.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Admin docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(admin): add capability-scoped operations panel"
```

### Task 6: Localization, RTL, Accessibility and Browser Journeys

**Files:**
- Modify: `resources/css/app.css`, `resources/js/app.js`
- Create: `tests/EndToEnd/customer-journey.spec.ts`
- Create: `tests/EndToEnd/admin-journey.spec.ts`
- Create: `tests/EndToEnd/accessibility.spec.ts`
- Create: `playwright.config.ts`
- Modify: `package.json`

**Interfaces:**
- Consumes: واجهات Web/Admin المكتملة وfixtures آمنة.
- Produces: رحلة R63 قابلة للتكرار بالإنجليزية والعربية/RTL ولوحة المفاتيح.

- [ ] **Step 1: اكتب browser journey الأحمر للعميل**

```ts
test('customer completes Rehla phase one', async ({ page }) => {
  await registerCustomer(page);
  await addTraveler(page, { passport: 'P1234567' });
  await submitTopUp(page, { amount: '5000.00', reference: 'E2E-001' });
  await approveTopUpAsStaff(page, 'E2E-001');
  await buyService(page, { service: 'UAE Visa', traveler: 'Ahmed Ali' });
  await expect(page.getByText('Order Received')).toBeVisible();
});
```

- [ ] **Step 2: شغل RED**

Run: `npm run test:e2e -- customer-journey.spec.ts`

Expected: FAIL عند أول واجهة أوselector غير مكتمل.

- [ ] **Step 3: أكمل اللغة والاتجاه**

كل صفحة تضع `lang` و`dir`، وتستخدم CSS logical properties، وتعرض الأرقام المالية بوضوح مع SDG دون تغيير قيمة minor. لا تخلط النص العربي والإنجليزي في المفتاح نفسه. locale fallback إنجليزي.

- [ ] **Step 4: نفذ رحلة الإدارة والـaction required**

تكمل admin journey إنشاء/نشر خدمة ونموذج، تفعيل بنك، اعتماد TopUp، فتح Execution، طلب وثيقة، استجابة العميل، نقل الحالة إلىcompleted، والتحقق من Audit.

- [ ] **Step 5: أثبت الوصول**

اختبر tab order، focus visible، labels،error association،dialog focus trap،contrast وaxe violations الجدية. نفذ الرحلتين في `en` ثم`ar` وتحقق من`dir=rtl`.

Run: `npm run test:e2e`

Expected: PASS بلا serious/critical accessibility violations.

- [ ] **Step 6: Commit**

```bash
git add resources tests/EndToEnd playwright.config.ts package.json package-lock.json docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "test(e2e): prove bilingual customer and admin journeys"
```

### Task 7: Health, Workers, Scheduler and Observability

**Files:**
- Create: `app/Http/Controllers/{LivenessController,ReadinessController}.php`
- Modify: `bootstrap/app.php`, `routes/console.php`
- Create: `routes/health.php`
- Create: `config/observability.php`
- Create: `docs/operations/processes.md`
- Create: `docs/operations/alerts.md`
- Test: `tests/Feature/HealthEndpointsTest.php`
- Test: `tests/Integration/SchedulerSingletonTest.php`

**Interfaces:**
- Produces: `/up` liveness بلا dependencies، و`/ready` يتحقق من PostgreSQL وstorage metadata؛ أوامر worker/scheduler موثقة.

- [ ] **Step 1: اكتب اختبارات الصحة**

```php
it('separates liveness from readiness', function (): void {
    get('/up')->assertOk()->assertJson(['status' => 'alive']);
    Storage::fake('private');
    get('/ready')->assertOk()->assertJsonPath('checks.database', 'ok');
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test tests/Feature/HealthEndpointsTest.php`

Expected: FAIL قبل endpoints.

- [ ] **Step 3: نفذ health وprocess contracts**

لا تفحص `/up` قاعدة البيانات. يفحص `/ready` `select 1` وقدرة disk الخاصة على metadata operation دون كتابة ملف عميل. وثق processes: web،`queue:work --timeout=90 --tries=1`،scheduler. اضبط `retry_after=120` ليكون أكبر منtimeout.

- [ ] **Step 4: أضف metrics وalerts**

سجل trace ID،HTTP latency/error rate،DB transaction retries،top-up review time،fulfillment time،queue depth،oldest outbox age،delivery failures،document scan failures وwallet reconciliation mismatch. عرف alerts بحدود: أي reconciliation mismatch؛ oldest outbox>5دقائق؛ dead letters>0؛ 5xx>2% خلال5دقائق.

- [ ] **Step 5: أثبت scheduler singleton وworker restart**

استخدم `onOneServer` وlock store موثوق لمهام cleanup/outbox، واختبر overlap. نفذ restart أثناء jobs صناعية وتحقق من lease recovery.

Run: `php artisan test tests/Feature/HealthEndpointsTest.php tests/Integration/SchedulerSingletonTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app bootstrap routes config/observability.php docs/operations tests/Feature/HealthEndpointsTest.php tests/Integration/SchedulerSingletonTest.php
git commit -m "ops: add health process and observability contracts"
```

### Task 8: Deployment, Migration and Restore Proof

**Files:**
- Create: `scripts/release/verify-artifact.sh`
- Create: `scripts/release/backup.sh`
- Create: `scripts/release/restore-rehearsal.sh`
- Create: `docs/operations/deployment.md`
- Create: `docs/operations/backup-restore.md`
- Create: `tests/Integration/UpgradeMigrationTest.php`

**Interfaces:**
- Produces: artifact immutable من commit واحد،سياسة expand/backfill/contract،backup/restore متناسق لـDB/private blobs.

- [ ] **Step 1: اكتب اختبار upgrade migration**

يحفظ fixture يمثل آخر schema منشورة، يشغل migrations الجديدة، ثم يتأكد أن Ledger/Audit/Orders/FormVersions وعدد الملفات وروابطها لم تتغير وأن التطبيق يقرأها.

Run: `php artisan test tests/Integration/UpgradeMigrationTest.php`

Expected: FAIL حتى يوجد fixture وسير الترقية.

- [ ] **Step 2: نفذ artifact verification**

يتحقق script من lockfiles،production install،config cache،route cache،view cache،Vite assets،migrations pending وصحة `/ready`. لا يبني dependencies على خادم الإنتاج.

- [ ] **Step 3: وثق ونفذ expand/backfill/contract**

كل تغيير غير متوافق يقسم إلى: إضافة schema متوافقة،نشر code مزدوج القراءة/الكتابة،backfill قابل للاستئناف بمؤشر،تحقق counts/checksums،ثم إزالة قديمة في إصدار لاحق. يمنع down migration مدمرًا بعد production data.

- [ ] **Step 4: نفذ backup وrestore rehearsal**

`backup.sh` يلتقط PostgreSQL snapshot/WAL position وmanifest للـprivate blobs مع timestamp واحد. `restore-rehearsal.sh` يعيدهما إلى بيئة معزولة،يشغل integrity queries وsample authorized downloads وwallet reconciliation،ويفشل إذا تجاوز RPO15دقيقة أوRTO4ساعات.

- [ ] **Step 5: شغل proof**

Run: `bash scripts/release/verify-artifact.sh && bash scripts/release/restore-rehearsal.sh`

Expected: artifact سليم وrestore report ناجح بزمن وcounts/checksums.

- [ ] **Step 6: Commit**

```bash
git add scripts/release docs/operations tests/Integration/UpgradeMigrationTest.php
git commit -m "ops: prove deploy upgrade backup and restore paths"
```

### Task 9: Security, Performance and Final R01–R65 Release Gate

**Files:**
- Create: `tests/EndToEnd/phase-one-acceptance.spec.ts`
- Create: `tests/Security/AuthorizationMatrixTest.php`
- Create: `tests/Security/PrivateDocumentExposureTest.php`
- Create: `tests/Performance/QueryBudgetTest.php`
- Create: `scripts/verify-acceptance-register.php`
- Create: `docs/releases/phase-1-readiness.md`
- Modify: `composer.json`, `package.json`, `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: كل متطلبات R01–R65 ونتائج الاختبارات والخدمات التشغيلية.
- Produces: قرار إطلاق قابل للتدقيق؛ لا exit0 إذا بقي صف داخل النطاق بلادليل.

- [ ] **Step 1: اكتب verifier السجل**

```php
foreach ($rows as $row) {
    if ($row['status'] === 'verified' && ($row['evidence'] === '' || $row['test_file'] === '' || $row['test_name'] === '')) {
        throw new RuntimeException("{$row['acceptance_id']} is verified without evidence");
    }
    if ($row['status'] === 'deferred' && $row['deferred_reason'] === '') {
        throw new RuntimeException("{$row['acceptance_id']} is deferred without reason");
    }
    if (in_array($row['status'], ['planned', 'red', 'green'], true)) {
        throw new RuntimeException("{$row['acceptance_id']} is not release-ready");
    }
}
```

- [ ] **Step 2: شغل RED على السجل غير المغلق**

Run: `php scripts/verify-acceptance-register.php`

Expected: FAIL ويطبع IDs غير verified/deferred.

- [ ] **Step 3: أكمل مصفوفة الأمان**

اختبر customer/staff-limited/staff-finance/staff-operations/admin لكل route وAdmin section. افحص IDOR وmass assignment وCSRF/session fixation وSanctum revocation وrate limits وupload content validation وlog redaction وsecurity headers. شغل `composer audit` و`npm audit --audit-level=high` وراجع تراخيص dependencies الإنتاجية.

- [ ] **Step 4: ثبت ميزانيات الأداء**

على fixture يضم100خدمة و1000Order و10000ledger entry: service list≤20queries وp95<500ms محليًا؛order detail≤15queries؛admin overview≤20queries وp95<1s؛API list يستخدم pagination ولايعيد أكثر من100عنصر. تفشل الاختبارات عند N+1 أوتجاوز query budget.

- [ ] **Step 5: شغل رحلة القبول الكاملة**

يشمل `phase-one-acceptance.spec.ts`: discovery→register→traveler→top-up→admin approval→checkout→debit/order/execution→status tracking→customer action response→completion، ثم family scenario بثلاثة Orders،price/form version change،duplicate transfer،duplicate approval،double submission وconcurrent purchase.

Run: `npm run test:e2e -- phase-one-acceptance.spec.ts`

Expected: PASS لكل R52–R59 وR63.

- [ ] **Step 6: أغلق سجل القبول بالدليل**

حدث كل صف داخل النطاق إلى`verified` مع `evidence` بصيغة `command :: test result :: artifact path`. أبق عناصر R60 وامتدادات R64 المؤجلة `deferred` بسبب واضح. شغل verifier حتى exit0.

- [ ] **Step 7: شغل بوابة الإصدار من بيئة جديدة**

```bash
composer install --no-interaction --prefer-dist
npm ci
php artisan migrate:fresh --env=testing
composer verify
npm run build
npm run test:e2e
composer audit
npm audit --audit-level=high
php scripts/verify-acceptance-register.php
git diff --check
```

Expected: كل الأوامر exit0، ولاصف داخل النطاق بلا دليل،ولا severe security finding غير محسوم.

- [ ] **Step 8: اكتب readiness record وCommit**

يسجل `phase-1-readiness.md` commit SHA،إصدارات PHP/Laravel/PostgreSQL،أوامر ونتائج التحقق،restore timing،المخاطر المقبولة،وهوية صاحب قرار الإطلاق. لا يصف النظام بالمكتمل قبل هذا السجل.

```bash
git add tests scripts/verify-acceptance-register.php docs/releases composer.json package.json .github/workflows/ci.yml docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "release: prove Rehla phase one acceptance"
```
