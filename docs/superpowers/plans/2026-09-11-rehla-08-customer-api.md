# Rehla Customer REST API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

**Coverage:** `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`

**Goal:** بناء REST API v1 للعملاء بعقد OpenAPI كامل ودقيق للعمليات الثماني والعشرين.

**Architecture:** حزمة Api طبقة نقل Sanctum تستدعي Actions وQueries نفسها، وتفصل Form Requests وResources وProblem Details عن منطق المجال.

**Tech Stack:** Laravel HTTP، Sanctum، OpenAPI 3.1، Pest contract tests.

**Prerequisites:** إغلاق بوابة الخطة 07 وإثبات عقود المجال؛ Web لا يعتمد على API ولا يستعمله كناقل داخلي.

## Global Constraints

- tokens للعملاء فقط ولا تحمل staff abilities؛ كل عملية تعلن ability وrate limit وcontent type.
- كل مورد مملوك يثبت 401 و403/404 غير الكاشف، وكل mutation يثبت 409 و422 حيث ينطبقان.
- الأخطاء `application/problem+json` برموز محايدة لغويًا و`trace_id` و`correlation_id`.
- OpenAPI والـroutes والاختبارات لها مجموعة operation IDs متساوية تمامًا.

---

### Task 1: API Foundation, Authentication and Problem Details

**Files:**
- Create: `packages/Rehla/Api/src/routes/api_v1.php`
- Create: `packages/Rehla/Api/src/openapi/rehla-v1.yaml`
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/Auth/{RegisterController,LoginController,LogoutController}.php`
- Create: `packages/Rehla/Api/src/Http/Middleware/{RequireJson,ResolveApiLocale}.php`
- Create: `packages/Rehla/Api/src/Errors/ProblemDetailsFactory.php`
- Create: `packages/Rehla/Api/src/Http/Resources/V1/UserResource.php`
- Test: `packages/Rehla/Api/tests/Contract/OpenApiContractTest.php`
- Test: `packages/Rehla/Api/tests/Feature/AuthApiTest.php`
- Test: `packages/Rehla/Api/tests/Feature/ProblemDetailsTest.php`

**Mandatory Package Contract — Api:**
- Create/verify: `packages/Rehla/Api/composer.json` and `packages/Rehla/Api/README.md`.
- Create/verify: `packages/Rehla/Api/src/Providers/ApiServiceProvider.php`.
- Create/verify: `packages/Rehla/Api/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Api/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Api/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-api`; `ApiServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-api')`.
- يبدأ ملفا `messages.php` متطابقين ولو كانا فارغين، وتضاف مفاتيح EN/AR في الالتزام نفسه. يمنع الحارس النص المرئي الصريح في PHP خارج الثوابت التقنية وfixtures المعلنة.
- يوثق README عقود Actions/Queries، التفويض، حدود المعاملة، error codes، owned tables، والاستعادة. لا يستورد العرض Models قابلة للتعديل ولا يكتب DB مباشرة.
- يبدأ التنفيذ باختبار RED، ثم اختبار الحزمة المركز، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة.

**Interfaces:**
- Produces: `/api/v1` JSON API،Sanctum bearer tokens،problem details ثابتة.

- [ ] **Step 1: اكتب اختبار error envelope**

```php
it('returns stable problem details independent of locale', function (): void {
    postJson('/api/v1/order-submissions', [])->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://rehla.example/problems/unauthenticated')
        ->assertJsonPath('code', 'auth.unauthenticated')
        ->assertJsonStructure(['type', 'title', 'status', 'code', 'trace_id', 'correlation_id']);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Api/tests/Feature/ProblemDetailsTest.php`

Expected: FAIL قبل API provider/routes.

- [ ] **Step 3: نفذ auth وmiddleware**

`POST /auth/register` يستدعي RegisterCustomer ثم يصدر token باسم الجهاز وبـabilities customer فقط. لا يمكن لـSanctum customer token حمل staff abilities أوالدخول إلى Admin. `POST /auth/login` يتحقق من password/status ويعمل rate limit 5/minute لكل email+IP. `POST /auth/logout` يلغي token الحالي. لا تعيد token في logs أوerrors.

- [ ] **Step 4: نفذ ProblemDetails mapping**

اربط رموز Core بـHTTP: unauthenticated401، forbidden403، not found404، price/form/idempotency conflict409، validation422، rate limit429. تطابق القيم المنشورة lower dot notation ويكون تعارض المفتاح `idempotency.key_reused`. `title/detail` مترجمان وفق `Accept-Language` و`code` ثابت غير مترجم. أخف stack traces في production. تقبل middleware قيمة `X-Correlation-ID` صالحة أوتنشئها، وتنشئ `trace_id` جديدًا لكل HTTP request أوjob attempt ولا تثق بقيمة trace من العميل.

- [ ] **Step 5: اكتب OpenAPI الأساس واختبره**

عرّف OpenAPI3.1، bearer auth،ProblemDetails وValidationProblem،pagination وlanguage header. اجعل contract test يحلل YAML ويطابق routes المسجلة ويمنع route بلاoperationId/responses/security.

Run: `php artisan test packages/Rehla/Api/tests/Contract packages/Rehla/Api/tests/Feature/AuthApiTest.php packages/Rehla/Api/tests/Feature/ProblemDetailsTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Api
git commit -m "feat(api): establish v1 auth and problem details contract"
```

### Task 2: API Resources, Uploads and Mutations

**Files:**
- Create: `packages/Rehla/Api/src/Http/Controllers/V1/{MeController,ServiceController,TravelerController,WalletController,BankAccountController,TopUpController,UploadController,DocumentController,OrderSubmissionController,OrderController,ExecutionActionController,NotificationController}.php`
- Create: `packages/Rehla/Api/src/Http/Requests/V1/{UpdateMeRequest,StoreTravelerRequest,UpdateTravelerRequest,StoreTopUpRequest,ReplaceTopUpReceiptRequest,StoreUploadRequest,SubmitOrderRequest,RespondToActionRequest}.php`
- Create: `packages/Rehla/Api/src/Http/Resources/V1/{ServiceResource,TravelerResource,WalletResource,WalletEntryResource,BankAccountResource,TopUpResource,OrderResource,NotificationResource}.php`
- Modify: `packages/Rehla/Api/src/openapi/rehla-v1.yaml`
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
GET services/{service_slug}            public
GET services/{service_slug}/application-form public
GET|POST travelers                     auth owner
GET|PATCH travelers/{traveler_id}      auth owner
GET wallet                             auth owner
GET wallet/entries                     auth owner
GET bank-accounts                      auth
GET|POST top-ups                       auth owner; POST 10/hour
GET top-ups/{top_up_id}                auth owner
PUT top-ups/{top_up_id}/receipt        auth owner; under_review only
POST uploads                           auth owner 20/hour
GET uploads/{document_id}              auth owner; scan status
GET documents/{document_id}/content    auth owner/policy
POST order-submissions                 auth owner 10/min + Idempotency-Key
GET orders                             auth owner
GET orders/{order_reference}           auth owner
POST executions/{execution_id}/actions/{action_request_id}/responses auth owner
GET notifications                      auth owner
POST notifications/{notification_id}/read auth owner
```

لكل owner route شغل dataset: guest→401، authenticated بلا ملكية→404، المالك→success، actor غير مخول إداريًا→403 حيث يوجد role gate.

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Api/tests/Feature/ApiResourceAuthorizationTest.php`

Expected: FAIL للمسارات غير المنفذة.

- [ ] **Step 3: نفذ Resources والقراءات**

استعمل cursor أوpage pagination بعقد ثابت `{data,links,meta}`. أخف normalized passport وstorage key وinternal notes وpassword hashes. يرسل DocumentController streamed response خاصًا بعد query التفويض.

- [ ] **Step 4: نفذ mutations وIdempotency-Key**

Form Requests تحول payload إلى DTO. `OrderSubmissionController` يرفض header غائبًا بـ422، ويمرر المفتاح إلى SubmitOrder؛ الإنشاء الأول201، replay المطابق200 مع `Idempotency-Replayed: true`، التعارض409. يعرض `UploadController@show` حالة الفحص مع ownership و404 لغير المالك. يستدعي `TopUpController@replaceReceipt` أمر `ReplaceTopUpReceipt` ليحافظ على الطلب نفسه والبنك والمرجع في `under_review`. Upload لا يتجاوز حدود النوع والحجم، وTopUp لا يقبل document غيرclean.

- [ ] **Step 5: أكمل OpenAPI واختبر الأمثلة**

لكل route عرف request/response schemas و401/403/404/409/422/429 المناسب. يشغل `OpenApiExamplesTest` كل مثال request صالحًا ضد route ويقارن response بالschema.

Run: `php artisan test packages/Rehla/Api/tests`

Expected: PASS لكل route والعقد.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Api docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(api): expose complete versioned Rehla API"
```

## Final API Gate

تثبت البوابة مساواة عمليات OpenAPI الثماني والعشرين مع routes، وSanctum customer-only abilities، وrate limits وcontent types وProblem Details والملكية وidempotency والرفع والتنزيل واستبدال إيصال الشحن.
