# Rehla Operations, Security and Release Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

**Coverage:** `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`

**Goal:** إثبات قابلية تشغيل رحلة وأمانها واستعادتها وإغلاق سجل قبول R01–R65 من artifact نظيف.

**Architecture:** artifact واحد غير قابل للتغيير يشغل web وqueue وscheduler، وتغلق بوابات E2E ثم observability ثم deploy/restore ثم security/performance/final evidence بالتسلسل.

**Tech Stack:** Laravel 13.x، PHP 8.5، PostgreSQL 18، Node.js 24.x LTS، Playwright، host-native process manager، shell release scripts، CI.

**Prerequisites:** إغلاق بوابات الخطط 01–09 وعدم استخدام هذه الخطة لتعويض feature أوintegration ناقص في خطة سابقة.

## Global Constraints

- التنفيذ host-native وفق متطلبات المشروع، مع PostgreSQL حقيقي لا SQLite لإثبات التزامن والمال.
- لا إطلاق دون restore rehearsal وRPO 15 دقيقة وRTO 4 ساعات وتناسق DB/private blobs.
- لا يغلق صف قبول بلا command وtest result وartifact path، ولا يبقى critical/high finding غير محسوم.
- كل مرحلة تستهلك دليلاً ناجحًا من المرحلة السابقة ولا تعيد تعريف عقود الأعمال.

---

### Task 1: Localization, RTL, Accessibility and Browser Journeys

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: no business-table owner in this task. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R61`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Modify: `resources/css/app.css`, `resources/js/app.js`
- Create: `tests/EndToEnd/customer-journey.spec.ts`
- Create: `tests/EndToEnd/admin-journey.spec.ts`
- Create: `tests/EndToEnd/accessibility.spec.ts`
- Create: `playwright.config.ts`
- Modify: `package.json`

**Interfaces:**
- Consumes: أدلة إغلاق Web وAPI وAdmin وfixtures آمنة؛ لا يعوض feature ناقصًا.
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

### Task 2: Health, Workers, Scheduler and Observability

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: no business-table owner in this task. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R01, R02, R03, R06, R52, R53, R61, R63, R65 (supporting evidence)`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


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
- Consumes: نجاح Task 1 وروابط artifacts الخاصة بالرحلتين والوصول.
- Produces: `/up` liveness بلا dependencies، و`/ready` يتحقق من PostgreSQL وstorage metadata؛ تعريفات host-native موثقة لـweb وqueue وscheduler، ومقاييس وتنبيهات مثبتة.

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

لا تفحص `/up` قاعدة البيانات. يفحص `/ready` `select 1` وقدرة disk الخاصة على metadata operation دون كتابة ملف عميل. وثق processes host-native: web،`queue:work --timeout=90 --tries=1`،scheduler، مع مستخدم محدود وworking directory وenvironment file وrestart/backoff وgraceful stop. اضبط `retry_after=120` ليكون أكبر منtimeout. يثبت preflight النسخ المقفلة Laravel 13.x وPHP 8.5 وPostgreSQL 18 وNode.js 24.x LTS ويرفض اختلاف major أوlockfile.

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

### Task 3: Deployment, Migration and Restore Proof

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: no business-table owner in this task. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R01, R02, R03, R06, R52, R53, R61, R63, R65 (supporting evidence)`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `scripts/release/verify-artifact.sh`
- Create: `scripts/release/backup.sh`
- Create: `scripts/release/restore-rehearsal.sh`
- Create: `docs/operations/deployment.md`
- Create: `docs/operations/backup-restore.md`
- Create: `tests/Integration/UpgradeMigrationTest.php`

**Interfaces:**
- Consumes: نجاح Task 2 وprocess/readiness/alert evidence.
- Produces: artifact immutable من commit واحد مع artifact SHA-256، سياسة expand/backfill/contract، وbackup/restore مشفر ومتناسق لـDB/private blobs.

- [ ] **Step 1: اكتب اختبار upgrade migration**

يحفظ fixture يمثل آخر schema منشورة، يشغل migrations الجديدة، ثم يتأكد أن Ledger/Audit/Orders/FormVersions وعدد الملفات وروابطها لم تتغير وأن التطبيق يقرأها.

Run: `php artisan test tests/Integration/UpgradeMigrationTest.php`

Expected: FAIL حتى يوجد fixture وسير الترقية.

- [ ] **Step 2: نفذ artifact verification**

يتحقق script من commit SHA واحد وlockfiles وproduction install وconfig/route/view cache وVite assets وmigrations pending وصحة `/ready`، ثم يولد artifact SHA-256 وmanifest الملفات والإصدارات. لا يبني dependencies على خادم الإنتاج ولا يقبل ملفًا غير متتبع أومعدلًا داخل artifact.

- [ ] **Step 3: وثق ونفذ expand/backfill/contract**

كل تغيير غير متوافق يقسم إلى: إضافة schema متوافقة،نشر code مزدوج القراءة/الكتابة،backfill قابل للاستئناف بمؤشر،تحقق counts/checksums،ثم إزالة قديمة في إصدار لاحق. يمنع down migration مدمرًا بعد production data.

- [ ] **Step 4: نفذ backup وrestore rehearsal**

`backup.sh` يلتقط PostgreSQL snapshot/WAL position وmanifest للـprivate blobs مع timestamp واحد، ويشفر database dump وblob archive بمفتاح من secret store خارج المستودع، ويسجل checksums وcounts في consistency manifest موقع. `restore-rehearsal.sh` يتحقق من التوقيع ويفكهما إلى بيئة معزولة، ويشغل integrity queries وsample authorized downloads وwallet reconciliation ومطابقة روابط documents، ويفشل إذا تجاوز RPO 15 minutes أوRTO 4 hours أوظهر checksum/count mismatch.

- [ ] **Step 5: شغل proof**

Run: `bash scripts/release/verify-artifact.sh && bash scripts/release/restore-rehearsal.sh`

Expected: artifact سليم وrestore report ناجح بزمن وcounts/checksums.

- [ ] **Step 6: Commit**

```bash
git add scripts/release docs/operations tests/Integration/UpgradeMigrationTest.php
git commit -m "ops: prove deploy upgrade backup and restore paths"
```

### Task 4: Security, Performance and Final R01–R65 Release Gate

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: no business-table owner in this task. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R01, R02, R03, R06, R52, R53, R63, R65`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `tests/EndToEnd/phase-one-acceptance.spec.ts`
- Create: `tests/Security/AuthorizationMatrixTest.php`
- Create: `tests/Security/PrivateDocumentExposureTest.php`
- Create: `tests/Performance/QueryBudgetTest.php`
- Create: `scripts/verify-acceptance-register.php`
- Create: `docs/releases/phase-1-readiness.md`
- Modify: `composer.json`, `package.json`, `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: نجاح Task 3 وartifact SHA-256 وupgrade/restore report، وكل متطلبات R01–R65 ونتائج الاختبارات والخدمات التشغيلية.
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

اختبر customer/staff-limited/staff-finance/staff-operations/admin لكل route وAdmin section. افحص IDOR وmass assignment وCSRF/session fixation وSanctum revocation وrate limits وupload abuse/content validation وlog redaction. افحص CSP وHSTS وCORS allowlist والكوكيز الأمنية. شغل dependency audit و`composer audit` و`npm audit --audit-level=high` وsecret scan على history/artifact وlicense audit لاعتماديات الإنتاج. شرط البوابة zero unresolved critical/high findings؛ يسجل أي قبول أدنى بخطر ومالك وموعد.

- [ ] **Step 4: ثبت ميزانيات الأداء**

على fixture يضم100خدمة و1000Order و10000ledger entry: service list≤20queries وp95<500ms محليًا؛order detail≤15queries؛admin overview≤20queries وp95<1s؛API list يستخدم pagination ولايعيد أكثر من100عنصر. يشغل load على PostgreSQL باتصالات حقيقية مع شراء واعتماد شحن متزامنين، ويراقب lock wait وdeadlock/retry وqueue/outbox depth/oldest age. تفشل الاختبارات عند N+1 أوتجاوز query/latency budget أوسلامة مالية مختلفة.

- [ ] **Step 5: شغل رحلة القبول الكاملة**

يشمل `phase-one-acceptance.spec.ts`: discovery→register→traveler→top-up→admin approval→checkout→debit/order/execution→status tracking→customer action response→completion، ثم family scenario بثلاثة Orders،price/form version change،duplicate transfer،duplicate approval،double submission وconcurrent purchase.

Run: `npm run test:e2e -- phase-one-acceptance.spec.ts`

Expected: PASS لكل R52–R59 وR63.

- [ ] **Step 6: أغلق سجل القبول بالدليل**

حدث كل صف داخل النطاق إلى`verified` مع `evidence` بصيغة `command :: test result :: artifact path`. أبق عناصر R60 وامتدادات R64 المؤجلة `deferred` بسبب واضح. شغل verifier حتى exit0.

- [ ] **Step 7: شغل بوابة الإصدار من checkout نظيف**

أنشئ fresh directory خارج working tree من commit المثبت، وتحقق أن artifact SHA-256 يطابق Task 3. ثبّت من lockfiles فقط، وأنشئ قاعدة PostgreSQL فارغة باسم ينتهي `_testing` ومستخدمًا محدودًا، ثم شغل migrations. لا تستخدم SQLite أوقاعدة مشتركة أوcache من checkout الأصلي. شغل اختبارات كل الحزم التسع عشرة والجذر وArchitecture/Integration/Security/Performance وWeb/API/Admin browser، ثم OpenAPI equality وacceptance verifier وrestore proof. يسجل الدليل المسار المؤقت وSHA والإصدارات وexit code لكل أمر.

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
bash scripts/release/restore-rehearsal.sh
git diff --check
```

Expected: كل الأوامر exit0 من fresh directory وempty PostgreSQL، و`all 19 package suites` ناجحة، ولاصف داخل النطاق بلا دليل، ولا unresolved critical/high finding.

- [ ] **Step 8: اكتب readiness record وCommit**

يسجل `phase-1-readiness.md` commit SHA،إصدارات PHP/Laravel/PostgreSQL،أوامر ونتائج التحقق،restore timing،المخاطر المقبولة،وهوية صاحب قرار الإطلاق. لا يصف النظام بالمكتمل قبل هذا السجل.

```bash
git add tests scripts/verify-acceptance-register.php docs/releases composer.json package.json .github/workflows/ci.yml docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "release: prove Rehla phase one acceptance"
```

## Final Release Gate

تغلق الخطة بالترتيب: (1) localization/RTL/accessibility E2E، ثم (2) health/workers/scheduler/observability، ثم (3) artifact/migrations/encrypted backup/restore، ثم (4) security/performance/R01–R65 clean-room proof. لا تبدأ مرحلة دون artifact evidence ناجح من سابقتها، وتصدر readiness record قابلًا للتدقيق.
