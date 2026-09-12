# Rehla Foundation and Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** إنشاء مضيف Laravel قابل للتثبيت وحزم Rehla التسع عشرة وCore وحراس الاعتماد وبيئة PostgreSQL الآمنة.

**Architecture:** يبقى Laravel في الجذر ويحمّل حزم Composer المحلية من `packages/Rehla/*`. يقرأ اختبار المعمارية خريطة JSON ويقارنها بالـmanifests والاستيرادات، بينما يقدم Core قيمًا مستقلة بلا اعتماد على أي حزمة أعمال.

**Tech Stack:** Laravel 13.x، PHP 8.5، Composer path repositories، PostgreSQL 18، Pest، PHPStan/Larastan، Pint، Vite.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

**Prerequisites:** لا توجد خطة تنفيذ سابقة؛ يتطلب البدء قرارات المنتج المثبتة ووثيقة المفهوم R01–R65 والخرائط الآلية المعتمدة.

## Global Constraints

- نفذ القيود العامة في `docs/superpowers/plans/2026-09-11-rehla-platform-build.md`.
- لا تنشئ جداول أعمال في هذه الخطة.
- لا يملك Core Service Locator أوFacade لحزمة أخرى.
- يجب أن يكتشف `php artisan test packages/Rehla` اختبارات الحزم فعلًا.

---

### Task 1: Bootstrap Laravel Host

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
- **Acceptance IDs:** `R04, R60, R64 (supporting evidence)`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `artisan`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `vite.config.js`, `phpunit.xml`
- Create: `.editorconfig`, `.gitattributes`, `.gitignore`, `.env.example`, `README.md`
- Create: Laravel host files under `app/**`, `bootstrap/**`, `config/**`, `database/**`, `public/**`, `resources/**`, `routes/**`, and `storage/**`.
- Create: `tests/Pest.php`, `tests/TestCase.php`, `tests/Feature/HostBootTest.php`
- Preserve: `docs/**`, `specs/**`, `scripts/**`, `.agents/**`, `.codex/**`, and every pre-existing file outside the listed host paths.
- Never stage: `.env`, `.env.testing`, credentials, generated keys, `vendor/**`, or `node_modules/**`.

**Interfaces:**
- Consumes: PHP8.5، Composer، PostgreSQL18 المتاحان على المضيف.
- Produces: تطبيق Laravel يقلع من الجذر وبيئة اختبار اسم قاعدة بياناتها `rehla_testing`.

- [ ] **Step 1: أنشئ Laravel في مجلد مؤقت وانسخه إلى الجذر**

```bash
composer create-project laravel/laravel:^13.0 /tmp/rehla-laravel-host
rsync -a --exclude=.git --exclude=.env /tmp/rehla-laravel-host/ ./
composer require --dev pestphp/pest:^4.7.8 pestphp/pest-plugin-laravel:^4.1 larastan/larastan:^3.12 --with-all-dependencies
./vendor/bin/pest --init
```

لا تستبدل `.env.testing` محليًا موجودًا ولا تضعه في Git. تبقى القيم الآمنة المشتركة في `phpunit.xml` و`.env.example`، وتبقى الأسرار محلية.

- [ ] **Step 2: أنشئ اختبار إقلاع مضيف فاشلًا سلوكيًا**

```php
<?php

it('boots the Rehla host in testing mode', function (): void {
    expect(app()->environment())->toBe('testing');
    expect(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.pgsql.database'))->toBe('rehla_testing');
});
```

- [ ] **Step 3: تحقق من RED بعد اكتمال bootstrap**

Run: `php artisan test tests/Feature/HostBootTest.php`

Expected: FAIL لأن scaffold الافتراضي لا يضبط PostgreSQL و`rehla_testing` بعد؛ غياب `artisan` أوbootstrap أوautoload ليس RED مقبولًا.

- [ ] **Step 4: اضبط عقد بيئة الاختبار الملتزم**

اضبط `phpunit.xml` على `DB_CONNECTION=pgsql` و`DB_DATABASE=rehla_testing`، واحتفظ بـ`APP_ENV=testing` و`CACHE_STORE=array` و`MAIL_MAILER=array` و`QUEUE_CONNECTION=database`. حدّث `.env.example` بقيم PostgreSQL غير سرية؛ يجوز أن يضيف المطور القيم نفسها إلى `.env.testing` المحلي المستبعد من Git.

- [ ] **Step 5: ثبت الإصدارات وحقق الإقلاع**

Run: `composer show laravel/framework && php artisan test tests/Feature/HostBootTest.php`

Expected: Laravel13.x وPASS.

- [ ] **Step 6: ابنِ أصول المضيف**

Run: `npm ci && npm run build`

Expected: Vite build ناجح بلا ملفات مفقودة.

- [ ] **Step 7: Commit**

```bash
git add .editorconfig .gitattributes .gitignore .env.example README.md artisan app bootstrap composer.json composer.lock config database package.json package-lock.json phpunit.xml public resources routes storage tests vite.config.js
git commit -m "build: bootstrap Laravel host"
```

### Task 2: Record Decisions and Atomic Acceptance Register

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
- **Acceptance IDs:** `R04`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `docs/adr/0001-platform-baseline.md`
- Create: `docs/adr/0002-money-and-time.md`
- Create: `docs/adr/0003-authentication-and-mfa.md`
- Create: `docs/adr/0004-document-lifecycle.md`
- Create: `docs/requirements/rehla-phase-1-acceptance.csv`
- Modify: `tests/Pest.php`
- Create: `tests/Architecture/AcceptanceRegisterTest.php`

**Interfaces:**
- Consumes: قسم القرارات وسجل القبول في الخطة الرئيسية.
- Produces: قرارات ثابتة وصف قبول لكل متطلب ذري يمكن فحصه آليًا.

- [ ] **Step 1: اكتب اختبار بنية السجل**

```php
<?php

use Illuminate\Support\LazyCollection;

it('maps every product section and mandatory atomic family', function (): void {
    $rows = LazyCollection::make(fn () => yield from array_map('str_getcsv', file(base_path('docs/requirements/rehla-phase-1-acceptance.csv'))));
    $records = $rows->skip(1)->values();
    $ids = $records->pluck(0);

    foreach (range(1, 65) as $number) {
        expect($ids->contains(fn (string $id): bool => str_starts_with($id, sprintf('R%02d', $number))))->toBeTrue();
    }

    foreach (['R08.01', 'R08.11', 'R40.01', 'R40.14', 'R50.05', 'R62.01', 'R62.12', 'R63.W13', 'R63.A09'] as $id) {
        expect($ids)->toContain($id);
    }
});
```

- [ ] **Step 2: شغل الاختبار الأحمر**

Run: `php artisan test tests/Architecture/AcceptanceRegisterTest.php`

Expected: FAIL لأن ADRs وCSV غير موجودة.

- [ ] **Step 3: اكتب ADRs والسجل بالقيم المعتمدة**

استخدم رأس CSV التالي حرفيًا، وأنشئ صفوفًا ذرية تغطي R01–R65 والعائلات المحددة في الخطة الرئيسية:

```csv
acceptance_id,source_requirement,package,interface,db_invariant,test_file,test_name,status,evidence,deferred_reason
```

استخدم `planned` لكل صف، واترك `evidence` فارغًا حتى ينجح اختباره. لا تترك `package` أو`interface` أو`test_file` أو`test_name` فارغة للمتطلبات الداخلة في الإصدار الأول.

- [ ] **Step 4: تحقق من اكتمال السجل**

Run: `php artisan test tests/Architecture/AcceptanceRegisterTest.php`

Expected: PASS مع وجود 65عائلة R وكل IDs الإلزامية.

- [ ] **Step 5: Commit**

```bash
git add docs/adr docs/requirements tests/Architecture/AcceptanceRegisterTest.php
git commit -m "docs: lock phase one product decisions"
```

### Task 3: Create the Local Composer Package Workspace

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
- **Acceptance IDs:** `R04, R60, R64 (supporting evidence)`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Modify: `composer.json`
- Create: `scripts/create-rehla-packages.php`
- Create: `packages/Rehla/{Core,Identity,Catalog,Forms,Travelers,Documents,Wallet,TopUps,Orders,Fulfillment,Purchasing,Notifications,Content,Audit,Reporting,Integrations,Web,Api,Admin}/composer.json`
- Create: `packages/Rehla/<Package>/src/Providers/<Package>ServiceProvider.php`
- Create: `packages/Rehla/<Package>/src/resources/lang/en/messages.php`
- Create: `packages/Rehla/<Package>/src/resources/lang/ar/messages.php`
- Create: `packages/Rehla/<Package>/README.md`
- Create: `packages/Rehla/<Package>/tests/Unit/PackageBootTest.php`
- Create: `packages/Rehla/<Package>/tests/Architecture/TranslationCompletenessTest.php`
- Create: `tests/Architecture/PackageDiscoveryTest.php`
- Create: `tests/Architecture/PackageTranslationContractTest.php`

**Interfaces:**
- Consumes: `docs/architecture/rehla-package-map.json` بإصدار schema 2، و`docs/architecture/rehla-package-contract-map.json`، و`docs/architecture/table-ownership.json`.
- Produces: Composer package `rehla/<lowercase-name>` وnamespace `Rehla\<Package>\` لكل عقدة.

- [ ] **Step 1: اكتب اختبار اكتشاف الحزم**

```php
<?php

it('discovers every declared Rehla package provider', function (): void {
    $map = json_decode(file_get_contents(base_path('docs/architecture/rehla-package-map.json')), true, flags: JSON_THROW_ON_ERROR);

    foreach (array_keys($map['packages']) as $package) {
        $provider = "Rehla\\{$package}\\Providers\\{$package}ServiceProvider";
        expect(class_exists($provider))->toBeTrue();
        expect(app()->getProvider($provider))->not->toBeNull();
    }
});
```

- [ ] **Step 2: شغل الاختبار الأحمر**

Run: `php artisan test tests/Architecture/PackageDiscoveryTest.php`

Expected: FAIL على أول provider غير موجود.

- [ ] **Step 3: أنشئ manifests والـproviders من الخريطة**

يجعل `scripts/create-rehla-packages.php` كل manifest يحتوي:

```json
{
  "name": "rehla/core",
  "type": "library",
  "autoload": {"psr-4": {"Rehla\\Core\\": "src/"}},
  "autoload-dev": {"psr-4": {"Rehla\\Core\\Tests\\": "tests/"}},
  "extra": {"laravel": {"providers": ["Rehla\\Core\\Providers\\CoreServiceProvider"]}}
}
```

يتحقق المولد من `schema_version == 2` ومن 19 حزمة و98 حافة ومن غياب الدورات، ثم يستبدل الاسم والnamespace لكل حزمة ويضيف `require` مساويًا تمامًا لقائمة المستهلك في الخريطة. يضيف الجذر repository من النوع `path` على `packages/Rehla/*` ويطلب `rehla/web`, `rehla/api`, `rehla/admin` بـ`@dev`؛ تسحب اعتمادياتها بقية الحزم وتسجل providers كلها. يفشل اختبار الاكتشاف إذا كان Composer manifest ينقص حافة أو يزيدها.

ينشئ المولد لكل حزمة، بلا استثناء، `src/resources/lang/en/messages.php` و`src/resources/lang/ar/messages.php` كمصفوفتين متطابقتين ولو كانتا فارغتين، و`tests/Architecture/TranslationCompletenessTest.php`. يستدعي كل provider:

```php
$this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-'.strtolower($package));
```

يقرأ اختبار الحزمة ملفات اللغة recursively ويقارن مجموعة المفاتيح وشكل كل قيمة (`array` أوscalar). يقرأ `PackageTranslationContractTest` كل provider ويفشل إذا لم يحمل namespace الحزمة من `src/resources/lang`، أوغاب أحد ملفي اللغة، أووجد نص مرئي صريح في Actions أوControllers أوJobs أوPolicies أوFilament definitions خارج allowlist للثوابت التقنية وfixtures الاختبارية.

- [ ] **Step 4: حدث autoload ونفذ الاختبار**

Run: `php scripts/create-rehla-packages.php && composer update rehla/web rehla/api rehla/admin --with-all-dependencies && composer dump-autoload && php artisan test tests/Architecture/PackageDiscoveryTest.php tests/Architecture/PackageTranslationContractTest.php`

Expected: PASS لكل 19 provider و19 namespace وثنائي لغة متكافئ.

- [ ] **Step 5: تحقق من اكتشاف اختبارات الحزم**

Run: `php artisan test packages/Rehla`

Expected: 19 smoke tests ناجحة على الأقل.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock scripts packages tests/Architecture/PackageDiscoveryTest.php
git commit -m "build: establish Rehla package workspace"
```

### Task 4: Enforce Package Boundaries and Table Ownership

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
- **Acceptance IDs:** `R60, R64`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `tests/Architecture/Support/ArchitectureScanner.php`
- Create: `tests/Architecture/PackageDependencyTest.php`
- Create: `tests/Architecture/ModelBoundaryTest.php`
- Create: `tests/Architecture/MigrationOwnershipTest.php`
- Consume: `docs/architecture/rehla-package-map.json`
- Consume: `docs/architecture/rehla-package-contract-map.json`
- Consume: `docs/architecture/table-ownership.json`

**Interfaces:**
- Consumes: الخرائط الثلاث وComposer manifests وPHP source tree وmigrations.
- Produces: فشل CI عند cycle أوrequire/import غير مسموح أوحافة بلا عقد أوModel متسرب أوجدول له مالكان أوكاتب غير المالك.

- [ ] **Step 1: اكتب حالات RED تشمل bypasses**

أنشئ fixtures مؤقتة أثناء الاختبار لاستيرادات مباشرة ومؤهلة وgrouped، مثل:

```php
use Rehla\Admin\Resources\OrderResource;
use Rehla\Wallet\Models\{Wallet, LedgerEntry};
$model = new \Rehla\Orders\Models\Order();
```

وتوقع أن يبلغ الحارس الحزمة والملف والرمز المحظور، وأن يقبل `Rehla\Wallet\Contracts\DebitWallet`.

- [ ] **Step 2: شغل اختبارات الحارس وتحقق من RED**

Run: `php artisan test tests/Architecture/PackageDependencyTest.php tests/Architecture/ModelBoundaryTest.php tests/Architecture/MigrationOwnershipTest.php`

Expected: FAIL لأن الحراس والملكية غير مكتملة.

- [ ] **Step 3: نفذ parser يعتمد tokens وComposer JSON**

اقرأ `T_NAME_QUALIFIED`, `T_NAME_FULLY_QUALIFIED`, `T_USE` وgrouped imports عبر `token_get_all` بدل regex. قارن الحزمة المستوردة بقائمة المستهلك في JSON، وقارن `require` في كل manifest بالحواف نفسها مساواة تامة. امنع أي `Models` عبر الحزم حتى لو كان الاعتماد نفسه مسموحًا. تحقق من أن كل حافة لها سجل وحيد في contract map وأن كل surface مملوك للـprovider. افحص migrations بحثًا عن `Schema::create` وقارنها بخريطة الملكية، وافشل عند جدول غير مسجل أومالك ثان أوwriter ليس المالك.

- [ ] **Step 4: أثبت المنع والقبول**

Run: `php artisan test tests/Architecture`

Expected: PASS للحالات الصحيحة وحالات الالتفاف والـfalse positives.

- [ ] **Step 5: Commit**

```bash
git add tests/Architecture docs/architecture/table-ownership.json
git commit -m "test: enforce package architecture boundaries"
```

### Task 5: Implement Core Value Objects and Error Contract

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Core. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R04, R60, R64 (supporting evidence)`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Modify: `composer.json`
- Create: `phpstan.neon`
- Modify: `packages/Rehla/Core/src/Providers/CoreServiceProvider.php`
- Create: `packages/Rehla/Core/src/Money/Money.php`
- Create: `packages/Rehla/Core/src/Identifiers/OpaqueId.php`
- Create: `packages/Rehla/Core/src/Time/Clock.php`
- Create: `packages/Rehla/Core/src/Time/SystemClock.php`
- Create: `packages/Rehla/Core/src/Errors/ProblemCode.php`
- Test: `packages/Rehla/Core/tests/Unit/MoneyTest.php`
- Test: `packages/Rehla/Core/tests/Unit/OpaqueIdTest.php`
- Test: `packages/Rehla/Core/tests/Unit/ClockTest.php`
- Test: `packages/Rehla/Core/tests/Unit/ProblemCodeTest.php`

**Mandatory Package Contract — Core:**
- Create/verify: `packages/Rehla/Core/composer.json` and `packages/Rehla/Core/README.md`.
- Create/verify: `packages/Rehla/Core/src/Providers/CoreServiceProvider.php`.
- Create/verify: `packages/Rehla/Core/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Core/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Core/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-core`; `CoreServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-core')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Core/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `Money::sdg(int $minor)`, `Money::add`, `Money::subtract`, `Money::isLessThan`; `OpaqueId::generate/fromString`; `Clock::now(): CarbonImmutable`; و`ProblemCode` بقيم عامة lower dot notation.

- [ ] **Step 1: اكتب اختبارات Money الفاشلة**

```php
it('keeps SDG arithmetic in integer minor units', function (): void {
    $balance = Money::sdg(5_000_00);
    $price = Money::sdg(2_500_00);

    expect($balance->subtract($price)->minor())->toBe(2_500_00)
        ->and($balance->currency())->toBe('SDG');
});

it('rejects negative construction and subtraction below zero', function (): void {
    expect(fn () => Money::sdg(-1))->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Core/tests/Unit`

Expected: FAIL لأن الأنواع غير موجودة.

- [ ] **Step 3: نفذ Money بلا float**

```php
final readonly class Money
{
    private function __construct(private int $minor, private string $currency) {}

    public static function sdg(int $minor): self
    {
        if ($minor < 0) throw new InvalidArgumentException('Money cannot be negative.');
        return new self($minor, 'SDG');
    }

    public function minor(): int { return $this->minor; }
    public function currency(): string { return $this->currency; }
}
```

أكمل `add/subtract/isLessThan` مع فحص overflow ورفض النتيجة السالبة. يدعم الإصدار الأول SDG فقط. عرّف `ProblemCode` بالقيم العامة: `wallet.insufficient_balance`, `service.unavailable`, `service.price_changed`, `form.version_changed`, `traveler.passport_conflict`, `top_up.reference_used`, `idempotency.key_reused`, `operation.in_progress`, `document.not_clean`, `auth.forbidden_resource`. يجب أن تطابق كل قيمة regex القانوني للأكواد العامة.

- [ ] **Step 4: شغل اختبارات Core والتحليل**

Run: `php artisan test packages/Rehla/Core && composer analyse`

Expected: PASS ومنع أي parameter أوproperty مالية من نوع float.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Core
git commit -m "feat(core): add money time identifiers and error contracts"
```

### Task 6: Establish PostgreSQL Test Safety and CI Gates

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
- **Acceptance IDs:** `R04, R60, R64 (supporting evidence)`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `tests/Support/AssertsSafeTestingDatabase.php`
- Create: `tests/Support/PostgresConnections.php`
- Modify: `tests/TestCase.php`, `phpunit.xml`, `composer.json`
- Create: `.github/workflows/ci.yml`
- Test: `tests/Architecture/TestingDatabaseGuardTest.php`
- Test: `tests/Integration/PostgresConnectionTest.php`
- Test: `tests/EndToEnd/HostHealthTest.php`

**Interfaces:**
- Produces: guard يرفض driver غير pgsql أوdatabase لا تنتهي `_testing`؛ factory لاتصالين مستقلين لاختبارات السباق؛ ودليل اتصال فعلي بـPostgreSQL 18.

- [ ] **Step 1: اكتب اختبار guard الأحمر**

```php
it('rejects an unsafe integration database', function (): void {
    config()->set('database.connections.pgsql.database', 'rehla');
    expect(fn () => AssertsSafeTestingDatabase::check())->toThrow(RuntimeException::class, '_testing');
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test tests/Architecture/TestingDatabaseGuardTest.php`

Expected: FAIL لأن guard غير موجود.

- [ ] **Step 3: نفذ guard واتصالين حقيقيين**

```php
public static function check(): void
{
    $connection = DB::connection();
    $database = (string) $connection->getDatabaseName();
    if ($connection->getDriverName() !== 'pgsql' || ! str_ends_with($database, '_testing')) {
        throw new RuntimeException('Integration tests require a PostgreSQL database ending in _testing.');
    }
}
```

يجبر `PostgresConnections` اتصالين جديدين إلى القاعدة نفسها، ولا يستخدم test wrapper transaction في اختبارات concurrency.

- [ ] **Step 4: أضف scripts وCI**

أضف scripts المحددة في الخطة الرئيسية. يشغل CI PostgreSQL18 service، `composer install --no-interaction --prefer-dist`، `npm ci`، fresh migrations، `composer verify` و`npm run build`. لا تضف Dockerfile أوتشغيلًا container-native للمشروع.

- [ ] **Step 5: شغل بوابة الخطة**

Run: `php artisan migrate:fresh --env=testing && composer verify && npm run build && git diff --check`

Expected: كل الأوامر PASS، وكل tests داخل الحزم مكتشفة.

- [ ] **Step 6: Commit**

```bash
git add tests phpunit.xml composer.json .github/workflows/ci.yml
git commit -m "ci: enforce PostgreSQL and package quality gates"
```

## Plan Completion Gate

تغلق الخطة host وCore واكتشاف 19 package وحراس الاعتماد والعقود والجداول وPostgreSQL CI. تنتج `Money`, `Clock`, opaque IDs, public error contract، ومولد الحزم الذي تستهلكه الخطة 02. تفتح بوابة `phase-one-release` التي لا تغلق حتى الخطة 10؛ لا يعد ذلك نقصًا في اكتمال Foundation.
