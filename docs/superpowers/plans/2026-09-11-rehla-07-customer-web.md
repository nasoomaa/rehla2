# Rehla Customer Web Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

**Coverage:** `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`

**Goal:** بناء تجربة Web كاملة للعميل من اكتشاف الخدمة حتى متابعة التنفيذ والمستندات والإشعارات بالإنجليزية والعربية.

**Architecture:** حزمة Web طبقة عرض session-based تستدعي Actions وQueries وDTOs العامة للحزم ولا تستورد Models أوتستدعي REST داخليًا.

**Tech Stack:** Laravel Sessions، Blade، Livewire، Tailwind CSS، Pest، Playwright.

**Prerequisites:** إغلاق بوابة الخطة 06؛ توفر جميع عقود المجال وReporting وIntegrations اللازمة للقراءة والاستفسار.

## Global Constraints

- كل route مملوك يثبت guest وowner وother-account outcomes مع 404 غير كاشف.
- CSRF وتجديد session إلزاميان، ولا تكتب Components أوControllers إلى جداول الأعمال.
- كل نص من translation key ثنائي، وتشمل البوابة AR/RTL ولوحة المفاتيح والاستجابة والأثرية.
- الاستفسار عبر WhatsApp لا ينشئ Order أوDebit أوExecution.

---

### Task 1: Public Web, Authentication and Account Shell

**Files:**
- Create: `packages/Rehla/Web/src/routes/web.php`
- Create: `packages/Rehla/Web/src/Http/Controllers/{HomeController,ServiceController,LocaleController}.php`
- Create: `packages/Rehla/Web/src/Livewire/Auth/{Register,Login}.php`
- Create: `packages/Rehla/Web/src/Livewire/Account/{Profile,TravelerIndex,TravelerForm,WalletOverview,TopUpIndex,OrderIndex,NotificationIndex}.php`
- Create: `packages/Rehla/Web/src/resources/views/{layouts,public,livewire}/**/*.blade.php`
- Create: `packages/Rehla/Web/src/resources/lang/{en,ar}/messages.php`
- Test: `packages/Rehla/Web/tests/Feature/PublicWebTest.php`
- Test: `packages/Rehla/Web/tests/Feature/AccountIsolationTest.php`

**Mandatory Package Contract — Web:**
- Create/verify: `packages/Rehla/Web/composer.json` and `packages/Rehla/Web/README.md`.
- Create/verify: `packages/Rehla/Web/src/Providers/WebServiceProvider.php`.
- Create/verify: `packages/Rehla/Web/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Web/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Web/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-web`; `WebServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-web')`.
- يبدأ ملفا `messages.php` متطابقين ولو كانا فارغين، وتضاف مفاتيح EN/AR في الالتزام نفسه. يمنع الحارس النص المرئي الصريح في PHP خارج الثوابت التقنية وfixtures المعلنة.
- يوثق README عقود Actions/Queries، التفويض، حدود المعاملة، error codes، owned tables، والاستعادة. لا يستورد العرض Models قابلة للتعديل ولا يكتب DB مباشرة.
- يبدأ التنفيذ باختبار RED، ثم اختبار الحزمة المركز، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة.

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
- Create: `packages/Rehla/Web/src/resources/views/livewire/account/{top-up-create,order-checkout,order-show,customer-action-response}.blade.php`
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

## Final Web Gate

تغطي البوابة التصفح العام والتسجيل والحساب والمسافرين والمحفظة والشحن واستبدال الإيصال والشراء ومتابعة Order/Execution وإجراءات العميل والمستندات والإشعارات، بالإنجليزية والعربية/RTL، مع ownership وaccessibility وresponsive proof. تنتج الحزمة عقود routes وview models مثبتة تستهلكها بوابة الإصدار.
