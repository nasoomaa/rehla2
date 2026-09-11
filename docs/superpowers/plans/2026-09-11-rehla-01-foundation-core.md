# Rehla Foundation and Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** إنشاء مضيف Laravel قابل للتثبيت وحزم Rehla التسع عشرة وCore وحراس الاعتماد وبيئة PostgreSQL الآمنة.

**Architecture:** يبقى Laravel في الجذر ويحمّل حزم Composer المحلية من `packages/Rehla/*`. يقرأ اختبار المعمارية خريطة JSON ويقارنها بالـmanifests والاستيرادات، بينما يقدم Core قيمًا مستقلة بلا اعتماد على أي حزمة أعمال.

**Tech Stack:** Laravel 13.x، PHP 8.5، Composer path repositories، PostgreSQL 18، Pest، PHPStan/Larastan، Pint، Vite.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

## Global Constraints

- نفذ القيود العامة في `docs/superpowers/plans/2026-09-11-rehla-platform-build.md`.
- لا تنشئ جداول أعمال في هذه الخطة.
- لا يملك Core Service Locator أوFacade لحزمة أخرى.
- يجب أن يكتشف `php artisan test packages/Rehla` اختبارات الحزم فعلًا.

---

### Task 1: Bootstrap Laravel Host

**Files:**
- Create: `artisan`, `composer.json`, `composer.lock`, `bootstrap/app.php`, `bootstrap/providers.php`
- Create: `app/Providers/AppServiceProvider.php`, `phpunit.xml`, `.env.example`
- Create: `tests/Feature/HostBootTest.php`
- Preserve: `docs/**`

**Interfaces:**
- Consumes: PHP8.5، Composer، PostgreSQL18 المتاحان على المضيف.
- Produces: تطبيق Laravel يقلع من الجذر وبيئة اختبار اسم قاعدة بياناتها `rehla_testing`.

- [ ] **Step 1: أنشئ اختبار إقلاع مضيف فاشلًا**

```php
<?php

it('boots the Rehla host in testing mode', function (): void {
    expect(app()->environment())->toBe('testing');
    expect(config('database.default'))->toBe('pgsql');
});
```

- [ ] **Step 2: تحقق من الفشل قبل وجود Laravel**

Run: `php artisan test tests/Feature/HostBootTest.php`

Expected: FAIL لأن `artisan` أوbootstrap غير موجود.

- [ ] **Step 3: أنشئ Laravel في مجلد مؤقت وانسخه إلى الجذر**

```bash
composer create-project laravel/laravel:^13.0 /tmp/rehla-laravel-host
rsync -a --exclude=.git /tmp/rehla-laravel-host/ ./
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan
php artisan pest:install
```

اضبط `.env.testing` على `DB_CONNECTION=pgsql` و`DB_DATABASE=rehla_testing`، واحتفظ بـ`APP_ENV=testing` و`CACHE_STORE=array` و`MAIL_MAILER=array` و`QUEUE_CONNECTION=database`.

- [ ] **Step 4: ثبت الإصدارات وحقق الإقلاع**

Run: `composer show laravel/framework && php artisan test tests/Feature/HostBootTest.php`

Expected: Laravel13.x وPASS.

- [ ] **Step 5: ابنِ أصول المضيف**

Run: `npm ci && npm run build`

Expected: Vite build ناجح بلا ملفات مفقودة.

- [ ] **Step 6: Commit**

```bash
git add artisan app bootstrap composer.json composer.lock config database package.json package-lock.json phpunit.xml public resources routes storage tests .env.example
git commit -m "build: bootstrap Laravel host"
```

### Task 2: Record Decisions and Atomic Acceptance Register

**Files:**
- Create: `docs/adr/0001-platform-baseline.md`
- Create: `docs/adr/0002-money-and-time.md`
- Create: `docs/adr/0003-authentication-and-mfa.md`
- Create: `docs/adr/0004-document-lifecycle.md`
- Create: `docs/requirements/rehla-phase-1-acceptance.csv`
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

**Files:**
- Modify: `composer.json`
- Create: `scripts/create-rehla-packages.php`
- Create: `packages/Rehla/{Core,Identity,Catalog,Forms,Travelers,Documents,Wallet,TopUps,Orders,Fulfillment,Purchasing,Notifications,Content,Audit,Reporting,Integrations,Web,Api,Admin}/composer.json`
- Create: `packages/Rehla/<Package>/src/<Package>ServiceProvider.php`
- Create: `packages/Rehla/<Package>/README.md`
- Create: `packages/Rehla/<Package>/tests/Unit/PackageBootTest.php`
- Create: `tests/Architecture/PackageDiscoveryTest.php`

**Interfaces:**
- Consumes: `docs/architecture/rehla-package-map.json`.
- Produces: Composer package `rehla/<lowercase-name>` وnamespace `Rehla\<Package>\` لكل عقدة.

- [ ] **Step 1: اكتب اختبار اكتشاف الحزم**

```php
<?php

it('discovers every declared Rehla package provider', function (): void {
    $map = json_decode(file_get_contents(base_path('docs/architecture/rehla-package-map.json')), true, flags: JSON_THROW_ON_ERROR);

    foreach (array_keys($map['packages']) as $package) {
        $provider = "Rehla\\{$package}\\{$package}ServiceProvider";
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
  "extra": {"laravel": {"providers": ["Rehla\\Core\\CoreServiceProvider"]}}
}
```

يستبدل الاسم والnamespace لكل حزمة، ويضيف `require` وفق الخريطة فقط. يضيف الجذر repository من النوع `path` على `packages/Rehla/*` ويطلب `rehla/web`, `rehla/api`, `rehla/admin` بـ`@dev`؛ تسحب اعتمادياتها بقية الحزم وتسجل providers كلها.

- [ ] **Step 4: حدث autoload ونفذ الاختبار**

Run: `php scripts/create-rehla-packages.php && composer update rehla/web rehla/api rehla/admin --with-all-dependencies && composer dump-autoload && php artisan test tests/Architecture/PackageDiscoveryTest.php`

Expected: PASS لكل 19 provider.

- [ ] **Step 5: تحقق من اكتشاف اختبارات الحزم**

Run: `php artisan test packages/Rehla`

Expected: 19 smoke tests ناجحة على الأقل.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock scripts packages tests/Architecture/PackageDiscoveryTest.php
git commit -m "build: establish Rehla package workspace"
```

### Task 4: Enforce Package Boundaries and Table Ownership

**Files:**
- Create: `tests/Architecture/PackageDependencyTest.php`
- Create: `tests/Architecture/ModelBoundaryTest.php`
- Create: `tests/Architecture/MigrationOwnershipTest.php`
- Create: `docs/architecture/table-ownership.json`

**Interfaces:**
- Consumes: package map وComposer manifests وPHP source tree.
- Produces: فشل CI عند cycle أوrequire/import غير مسموح أوModel متسرب أوجدول له مالكان.

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

اقرأ `T_NAME_QUALIFIED`, `T_NAME_FULLY_QUALIFIED`, `T_USE` وgrouped imports عبر `token_get_all` بدل regex. قارن الحزمة المستوردة بقائمة المستهلك في JSON. امنع أي `Models` عبر الحزم حتى لو كان الاعتماد نفسه مسموحًا. افحص migrations بحثًا عن `Schema::create` وسجل المالك الوحيد في `table-ownership.json`.

- [ ] **Step 4: أثبت المنع والقبول**

Run: `php artisan test tests/Architecture`

Expected: PASS للحالات الصحيحة وحالات الالتفاف والـfalse positives.

- [ ] **Step 5: Commit**

```bash
git add tests/Architecture docs/architecture/table-ownership.json
git commit -m "test: enforce package architecture boundaries"
```

### Task 5: Implement Core Value Objects and Error Contract

**Files:**
- Create: `packages/Rehla/Core/src/Money/Money.php`
- Create: `packages/Rehla/Core/src/Identifiers/Uuid.php`
- Create: `packages/Rehla/Core/src/Time/Clock.php`
- Create: `packages/Rehla/Core/src/Time/SystemClock.php`
- Create: `packages/Rehla/Core/src/Errors/ErrorCode.php`
- Test: `packages/Rehla/Core/tests/Unit/MoneyTest.php`
- Test: `packages/Rehla/Core/tests/Unit/UuidTest.php`

**Interfaces:**
- Produces: `Money::sdg(int $minor)`, `Money::add`, `Money::subtract`, `Money::isLessThan`; `Clock::now(): CarbonImmutable`; رموز أخطاء ثابتة.

- [ ] **Step 1: اكتب اختبارات Money الفاشلة**

```php
it('keeps SDG arithmetic in integer minor units', function (): void {
    $balance = Money::sdg(5_000_00);
    $price = Money::sdg(2_500_00);

    expect($balance->subtract($price)->minor())->toBe(2_500_00)
        ->and($balance->currency())->toBe('SDG');
});

it('rejects currency mismatch and negative construction', function (): void {
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

أكمل `add/subtract/isLessThan` مع رفض اختلاف العملة والنتيجة السالبة. عرف ErrorCode على الأقل: `INSUFFICIENT_BALANCE`, `SERVICE_UNAVAILABLE`, `PRICE_CHANGED`, `FORM_VERSION_CHANGED`, `DUPLICATE_PASSPORT`, `TRANSACTION_REFERENCE_USED`, `IDEMPOTENCY_KEY_REUSED`, `OPERATION_IN_PROGRESS`, `DOCUMENT_NOT_CLEAN`, `FORBIDDEN_RESOURCE`.

- [ ] **Step 4: شغل اختبارات Core والتحليل**

Run: `php artisan test packages/Rehla/Core && composer analyse`

Expected: PASS ومنع أي parameter أوproperty مالية من نوع float.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Core
git commit -m "feat(core): add money time identifiers and error contracts"
```

### Task 6: Establish PostgreSQL Test Safety and CI Gates

**Files:**
- Create: `tests/Support/AssertsSafeTestingDatabase.php`
- Create: `tests/Support/PostgresConnections.php`
- Modify: `tests/TestCase.php`, `phpunit.xml`, `composer.json`
- Create: `.github/workflows/ci.yml`
- Test: `tests/Architecture/TestingDatabaseGuardTest.php`

**Interfaces:**
- Produces: guard يرفض driver غير pgsql أوdatabase لا تنتهي `_testing`؛ factory لاتصالين مستقلين لاختبارات السباق.

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
