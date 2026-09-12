# Rehla Admin Control Panel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

**Coverage:** `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`

**Goal:** بناء لوحة عمليات Filament كاملة وآمنة لإدارة رحلة دون تجاوز الحزم المالكة.

**Architecture:** حزمة Admin تقرأ Query/ReadModel contracts وتنفذ named Commands من الحزم المالكة. موارد Filament لا ترتبط بنماذج أعمال قابلة للتعديل ولا تستعمل DB مباشرة.

**Tech Stack:** Filament 5، Laravel staff sessions، TOTP، Pest، Playwright.

**Prerequisites:** إغلاق بوابة الخطة 08 وتوفر Reporting metrics وكل أوامر الإدارة والاستعلامات العامة من الحزم المالكة.

## Global Constraints

- Admin guard مستقل وdeny-by-default؛ القدرات الحساسة تتطلب MFA حديثة خلال أربع ساعات.
- لا `DB::` أوModels عابرة للحزم أوrelationship mutation أوbusiness transitions داخل closures.
- كل حقل حساس له قدرة وعرض مقنع وتدقيق وسياسة تصدير صريحة.
- كل النصوص ثنائية، والبوابة تثبت EN وAR/RTL ولوحة المفاتيح لموظف كامل وآخر محدود.

---

### Task 1: Filament Operations Panel

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Admin. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R05, R40, R43`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


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

**Mandatory Package Contract — Admin:**
- Create/verify: `packages/Rehla/Admin/composer.json` and `packages/Rehla/Admin/README.md`.
- Create/verify: `packages/Rehla/Admin/src/Providers/AdminServiceProvider.php`.
- Create/verify: `packages/Rehla/Admin/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Admin/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Admin/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-admin`; `AdminServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-admin')`.
- يبدأ ملفا `messages.php` متطابقين ولو كانا فارغين، وتضاف مفاتيح EN/AR في الالتزام نفسه. يمنع الحارس النص المرئي الصريح في PHP خارج الثوابت التقنية وfixtures المعلنة.
- يوثق README عقود Actions/Queries، التفويض، حدود المعاملة، error codes، owned tables، والاستعادة. لا يستورد العرض Models قابلة للتعديل ولا يكتب DB مباشرة.
- يبدأ التنفيذ باختبار RED، ثم اختبار الحزمة المركز، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة.

**Interfaces:**
- Consumes: domain Queries/ReadModels وActions فقط.
- Produces: 14قسمًا بصلاحيات مستقلة وحقول حساسة محدودة.

- [ ] **Step 1: اكتب capability matrix كـdataset**

```php
dataset('admin sections', [
    ['overview', 'admin.overview.view', 'view'],
    ['services', 'services.manage', 'mutate-via-action'],
    ['application-forms', 'forms.view', 'mutate-via-action'],
    ['customers', 'customers.view', 'masked-sensitive'],
    ['travelers', 'travelers.view', 'masked-passport'],
    ['wallets', 'wallets.view', 'read-only'],
    ['bank-accounts', 'banks.view', 'mutate-via-action'],
    ['top-up-requests', 'topups.review', 'approve-reject-action'],
    ['orders', 'orders.view', 'read-only'],
    ['service-executions', 'executions.view', 'transition-action'],
    ['content', 'content.manage', 'mutate-via-action'],
    ['notifications', 'notifications.view', 'replay-action'],
    ['roles-permissions', 'access.view', 'mfa-required'],
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

## Final Admin Gate

تثبت البوابة shell والصلاحيات وMFA ومصفوفة الموارد كاملة، وcommand-only mutations، وسياسات الحقول الحساسة والتنزيل والتصدير، ورحلة الموظف من مراجعة الشحن حتى الإكمال والتدقيق مع منع الموظف المحدود.
