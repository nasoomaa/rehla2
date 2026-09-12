# Rehla Identity and Platform Services Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** بناء الهوية والصلاحيات والتدقيق والوثائق والمسافرين وOutbox كخدمات منصة تعتمد عليها بقية مجالات رحلة.

**Architecture:** تملك كل حزمة جداولها ونماذجها وتعرض عقودًا صغيرة. ينفذ Audit وWallet لاحقًا حماية append-only في PostgreSQL، وتظل الملفات الخاصة معروفة بالمعرف فقط ولا تتسرب مسارات التخزين.

**Tech Stack:** Laravel Auth، Sanctum، PostgreSQL triggers وrow locks، Laravel Storage، Pest.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

**Prerequisites:** إغلاق بوابة الخطة 01 وتوفر Laravel host وCore والحزم وحراس المعمارية وPostgreSQL testing.

## Global Constraints

- أكمل خطة Foundation أولًا.
- المنع هو نتيجة التفويض الافتراضية.
- لا يعيد أي Contract في هذه الخطة Eloquent Model إلى حزمة أخرى.
- كل عملية حساسة تسجل actor وsubject ووقتًا وmetadata منظفة من الأسرار.

---

### Task 1: Append-only Audit Trail

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Audit. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R46`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Modify: `docs/requirements/rehla-phase-1-acceptance.csv`
- Modify: `scripts/create-rehla-packages.php`
- Modify: `packages/Rehla/Audit/composer.json`
- Modify: `packages/Rehla/Audit/README.md`
- Modify: `packages/Rehla/Audit/src/Providers/AuditServiceProvider.php`
- Create: `packages/Rehla/Audit/src/database/migrations/*_create_audit_entries_table.php`
- Create: `packages/Rehla/Audit/src/database/migrations/*_protect_audit_entries.php`
- Create: `packages/Rehla/Audit/src/Data/AppendAuditData.php`
- Create: `packages/Rehla/Audit/src/Contracts/AuditWriter.php`
- Create: `packages/Rehla/Audit/src/Actions/AppendAuditEntry.php`
- Create: `packages/Rehla/Audit/src/Models/AuditEntry.php`
- Test: `packages/Rehla/Audit/tests/Integration/AuditImmutabilityTest.php`

**Mandatory Package Contract — Audit:**
- Create/verify: `packages/Rehla/Audit/composer.json` and `packages/Rehla/Audit/README.md`.
- Create/verify: `packages/Rehla/Audit/src/Providers/AuditServiceProvider.php`.
- Create/verify: `packages/Rehla/Audit/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Audit/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Audit/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-audit`; `AuditServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-audit')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Audit/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Consumes: Core IDs وClock فقط.
- Produces: `AuditWriter::append(AppendAuditData): string` يعيد audit UUID.

- [x] **Step 1: اكتب اختبار append والحماية المباشرة**

```php
it('cannot update or delete an audit entry even with direct SQL', function (): void {
    $id = app(AuditWriter::class)->append(new AppendAuditData(
        actorType: 'staff', actorId: fakeUuid(), action: 'top_up.approved',
        subjectType: 'top_up', subjectId: fakeUuid(), metadata: ['amount_minor' => 500000]
    ));

    expect(fn () => DB::table('audit_entries')->where('id', $id)->update(['action' => 'changed']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('audit_entries')->where('id', $id)->delete())
        ->toThrow(QueryException::class);
});
```

- [x] **Step 2: شغل RED على PostgreSQL**

Run: `php artisan test packages/Rehla/Audit/tests/Integration/AuditImmutabilityTest.php`

Expected: FAIL قبل وجود الجدول/trigger.

- [x] **Step 3: نفذ append-only storage**

أنشئ `audit_entries(id uuid, actor_type, actor_id nullable, action, subject_type, subject_id, metadata jsonb, old_state jsonb nullable, new_state jsonb nullable, reason nullable, correlation_id uuid, ip_hash nullable, user_agent_hash nullable, occurred_at)` بلا `updated_at`. أضف PostgreSQL function واحدة ترفع exception على UPDATE أوDELETE وtrigger يستدعيها.

```php
interface AuditWriter
{
    public function append(AppendAuditData $data): string;
}
```

لا تخزن passwords أوtokens أوMFA secrets أومحتوى مستندات داخل metadata أوstate snapshots؛ ينظف الكاتب هذه المفاتيح recursively قبل الإدخال.

- [x] **Step 4: شغل الاختبارات وراجع migration fresh**

Run: `php artisan migrate:fresh --env=testing && php artisan test packages/Rehla/Audit`

Expected: INSERT ينجح وUPDATE/DELETE يفشلان.

- [x] **Step 5: Commit**

```bash
git add packages/Rehla/Audit
git commit -m "feat(audit): add immutable audit trail"
```

### Task 2: Identity, Sessions and Capabilities

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Identity. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R47`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/Identity/src/database/migrations/*_create_identity_tables.php`
- Create: `packages/Rehla/Identity/src/database/migrations/*_create_personal_access_tokens_table.php`
- Create: `packages/Rehla/Identity/src/Models/{User,StaffProfile,Role,Ability}.php`
- Create: `packages/Rehla/Identity/src/Auth/{CustomerEloquentUserProvider,StaffEloquentUserProvider}.php`
- Create: `packages/Rehla/Identity/src/Enums/{AccountStatus,AbilityName}.php`
- Create: `packages/Rehla/Identity/src/Data/{ActorData,ResourceRef,RegisterCustomerData,UserData}.php`
- Create: `packages/Rehla/Identity/src/Actions/{RegisterCustomer,AssignRole,RevokeRole,AuthorizeActor}.php`
- Create: `packages/Rehla/Identity/src/Queries/{GetCurrentUser,FindCustomer}.php`
- Create: `packages/Rehla/Identity/src/Contracts/{AuthorizesActor,IdentityReader,RegistrationWalletInitializer,RegistrationNotificationRecorder}.php`
- Modify: `packages/Rehla/Identity/{composer.json,README.md}`
- Modify: `packages/Rehla/Identity/src/Providers/IdentityServiceProvider.php`
- Modify: `scripts/create-rehla-packages.php`
- Modify: `config/auth.php`
- Test: `packages/Rehla/Identity/tests/Feature/IdentityTest.php`
- Test: `packages/Rehla/Identity/tests/Integration/AuthorizationTest.php`

**Mandatory Package Contract — Identity:**
- Create/verify: `packages/Rehla/Identity/composer.json` and `packages/Rehla/Identity/README.md`.
- Create/verify: `packages/Rehla/Identity/src/Providers/IdentityServiceProvider.php`.
- Create/verify: `packages/Rehla/Identity/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Identity/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Identity/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-identity`; `IdentityServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-identity')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Identity/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `RegisterCustomer::handle(RegisterCustomerData): UserData`; `AuthorizesActor::allows(ActorData, AbilityName, ?ResourceRef): bool`.
- Produces: `IdentityReader::findCustomer(string $accountId): ?UserData`; `FindCustomer` implements this read-only contract, while `GetCurrentUser::handle(): ?UserData` resolves the authenticated customer guard.
- Produces: `AssignRole::handle(ActorData $actor, string $accountId, string $roleName, string $correlationId): void`; `RevokeRole` has the same signature. Both require `access.manage`, therefore a recent MFA challenge, and append Audit in their transaction.
- Produces: `RegistrationWalletInitializer::initialize(string $accountId): void` و`RegistrationNotificationRecorder::recordWelcome(string $accountId, string $locale, string $correlationId): void`؛ Identity يملك المنفذين وتوفر Wallet وNotifications التنفيذين.
- Produces the exact canonical registry in `specs/cross-cutting/security-and-privacy.md`; tests reject every undeclared alias.

- [x] **Step 1: اكتب اختبارات التسجيل والمنع الافتراضي**

```php
it('registers a customer without staff powers', function (): void {
    $user = app(RegisterCustomer::class)->handle(new RegisterCustomerData(
        name: 'Ahmed Ali', email: 'ahmed@example.test', password: 'Secret-12345'
    ));

    expect($user->email)->toBe('ahmed@example.test')
        ->and(app(AuthorizesActor::class)->allows($user->actor, AbilityName::TopUpsReview))->toBeFalse();
});

it('rolls registration back when a required collaborator fails', function (): void {
    bindRegistrationPorts(wallet: succeeds(), notification: throwsException());

    expect(fn () => registerCustomer())->toThrow(RuntimeException::class)
        ->and(DB::table('users')->count())->toBe(0);
});
```

- [x] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Identity/tests`

Expected: FAIL لأن الجداول والأنواع غير موجودة.

- [x] **Step 3: أنشئ schema والعقود**

أنشئ `users(id uuid, name, email citext unique, password, status, preferred_locale, email_verified_at, timestamps)`، و`staff_profiles(user_id unique, department, is_active, mfa_confirmed_at)`، و`roles`, `abilities`, `role_ability`, `user_role`, و`personal_access_tokens` المتوافق مع Sanctum والمملوك لـIdentity والمستخدم حصريًا لرموز العميل. تضيف خطة API اعتماد Sanctum وسلوك إصدار وإلغاء الرموز عند بناء الناقل؛ لا تضف اعتمادًا خاملًا هنا. لا تستخدم عمود `is_admin`. اربط الصلاحيات بالسجل الحرفي ذي الثلاثين اسمًا في `AbilityName` وارفض أي alias، واحفظ passwords عبر Laravel Hash فقط.

```php
interface AuthorizesActor
{
    public function allows(ActorData $actor, AbilityName $ability, ?ResourceRef $resource = null): bool;
}

interface RegistrationWalletInitializer
{
    public function initialize(string $accountId): void;
}

interface RegistrationNotificationRecorder
{
    public function recordWelcome(string $accountId, string $locale, string $correlationId): void;
}
```

ينفذ `RegisterCustomer` إدخال المستخدم ثم `AuditWriter` ثم المنفذين داخل `DB::transaction` واحدة وعلى الاتصال نفسه. لا يلتقط استثناءات المشاركين ولا يسمح لهم بـcommit مستقل. تستخدم اختبارات Task 2 fakes للمنفذين؛ يبقى مسار التسجيل الفعلي fail-closed حتى تسجل Notifications ثم Wallet التنفيذين. `CustomerRegistered`، إن أضيف، يطلق بعد commit للتحليلات فقط.

- [x] **Step 4: أثبت عزل customer/admin guards وMFA policy**

سجل provider باسم `rehla-customer` لا يعيد إلا مستخدمًا active بلا `staff_profile`، وprovider مستقلًا باسم `rehla-staff` لا يعيد إلا مستخدمًا active له `staff_profile` active، ثم اربط `web` بالأول و`admin` بالثاني؛ يظل cookie/session middleware المستقل ضمن مهمة Admin التي تملك سطح HTTP. اختبر العزل في الاتجاهين، وأن staff بلا قدرة يُمنع، وأن قدرات `topups.review`, `topups.settings.manage`, `access.manage`, `audit.view` تتطلب دليل `mfa_confirmed_at` خادميًا حديثًا وسياق تحدٍ حديثًا خاصًا بالجلسة داخل `ActorData` وفق نافذة أربع ساعات؛ لا يمنح تحقق جلسة صلاحية لجلسة أخرى.

Run: `php artisan test packages/Rehla/Identity/tests`

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add packages/Rehla/Identity config/auth.php
git commit -m "feat(identity): add accounts roles and deny-by-default abilities"
```

### Task 3: Private Document Lifecycle

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Documents. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R48`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/Documents/src/database/migrations/*_create_documents_tables.php`
- Create: `packages/Rehla/Documents/src/Enums/{DocumentStatus,DocumentPurpose}.php`
- Create: `packages/Rehla/Documents/src/Data/{BeginUploadData,StoreUploadData,ScanResult,DocumentReference}.php`
- Create: `packages/Rehla/Documents/src/Contracts/{DocumentScanner,OwnedDocuments,DocumentDownloadAuthorizer}.php`
- Create: `packages/Rehla/Documents/src/Exceptions/{DocumentAccessDenied,DocumentNotClean,InvalidDocumentUpload}.php`
- Create: `packages/Rehla/Documents/src/Actions/{BeginUpload,StoreUpload,ScanDocument,AttachDocument,DeleteExpiredUploads}.php`
- Create: `packages/Rehla/Documents/src/Queries/AuthorizeDocumentDownload.php`
- Create: `packages/Rehla/Documents/src/Infrastructure/ClamAvDocumentScanner.php`
- Create: `packages/Rehla/Documents/src/config/documents.php`
- Modify: `packages/Rehla/Documents/{composer.json,README.md}`
- Modify: `packages/Rehla/Documents/src/Providers/DocumentsServiceProvider.php`
- Modify: `packages/Rehla/Core/src/Errors/ProblemCode.php`
- Modify: `packages/Rehla/Core/tests/Unit/ProblemCodeTest.php`
- Modify: `config/filesystems.php`
- Modify: `phpstan.neon` to register package-owned `src/config` directories as Laravel config paths.
- Modify: `scripts/create-rehla-packages.php`
- Test: `packages/Rehla/Documents/tests/Feature/DocumentLifecycleTest.php`
- Test: `packages/Rehla/Documents/tests/Integration/DocumentRaceTest.php`

**Mandatory Package Contract — Documents:**
- Create/verify: `packages/Rehla/Documents/composer.json` and `packages/Rehla/Documents/README.md`.
- Create/verify: `packages/Rehla/Documents/src/Providers/DocumentsServiceProvider.php`.
- Create/verify: `packages/Rehla/Documents/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Documents/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Documents/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-documents`; `DocumentsServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-documents')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Documents/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `OwnedDocuments::assertCleanOwned(array $documentIds, string $ownerId, array $requiredPurposes): array<DocumentReference>`؛ تطابق قائمة الأغراض قائمة المعرفات موضعيًا، وتقفل المعرفات بترتيب ثابت وتحوّلها إلى `attached` داخل معاملة المستهلك.
- Produces: `DocumentDownloadAuthorizer::authorize(string $documentId, ActorData $actor): StreamedResponse` بعد فحص owner أوstaff ability؛ لا ينتج storage path أوpublic URL.
- Produces: `DocumentScanner::scan(string $absolutePath): ScanResult`; الاستدعاء الخارجي يحدث خارج المعاملة ويفشل مغلقًا.

- [x] **Step 1: اكتب اختبار دورة الحالات والملكية**

```php
it('allows attachment only for a clean document owned by the account', function (): void {
    $document = uploadPrivatePdf(owner: $ownerA, purpose: DocumentPurpose::Passport);
    expect(fn () => app(OwnedDocuments::class)->assertCleanOwned([$document->id], $ownerB->id, DocumentPurpose::Passport))
        ->toThrow(DocumentAccessDenied::class);
    expect(fn () => attachDocument($document->id))->toThrow(DocumentNotClean::class);

    scanAsClean($document->id);
    expect(attachDocument($document->id)->status)->toBe(DocumentStatus::Attached);
});
```

- [x] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Documents/tests`

Expected: FAIL لغياب lifecycle.

- [x] **Step 3: أنشئ schema والتخزين الآمن**

أنشئ `upload_sessions(id, owner_id, purpose, expires_at, claimed_at)` و`documents(id, upload_session_id, owner_id, purpose, disk, storage_key, original_name, declared_mime, detected_mime, size_bytes, sha256, status, rejection_code, scan_token, scan_lease_expires_at, cleanup_claim_token, cleanup_lease_expires_at, scanned_at, attached_at, purged_at, timestamps)`. استخدم أسماء تخزين عشوائية ولا تستخدم اسم العميل في المسار. يسجل `private` disk بجذر غير عام و`serve=false` و`throw=true`.

الحالات المسموحة: `pending_scan → quarantined → clean|rejected`; ثم `clean → attached|cleanup_claimed` و`pending_scan → cleanup_claimed` بعد 24 ساعة، و`rejected → cleanup_claimed` بعد 30 يومًا، و`cleanup_claimed → purged`. لا يوجد انتقال من rejected إلىclean؛ يعاد الرفع بمعرف جديد. يستخدم scan claim وcleanup claim token/lease لمنع عامل قديم من اعتماد نتيجة بعد انتهاء حجزه.

- [x] **Step 4: تحقق من البايتات والتنظيف والسباق**

اختبر MIME معلنًا يخالف magic bytes، وملف polyglot، وPDF تالفًا، وملفًا أكبر من الحد، واستجابة تنزيل بـ`Content-Disposition: attachment`, `nosniff`, private cache headers. نفذ cleanup بقفل الصف وشرط `claimed_at is null`; نفذ attach بقفل الصف نفسه.

Run: `php artisan test packages/Rehla/Documents/tests`

Expected: PASS، وسباق cleanup/attach لا يحذف ملفًا attached.

- [x] **Step 5: Commit**

```bash
git add packages/Rehla/Documents config/filesystems.php
git commit -m "feat(documents): secure private upload lifecycle"
```

### Task 4: Travelers and Passport Uniqueness

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Travelers. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R11, R12`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/Travelers/src/database/migrations/*_create_travelers_table.php`
- Create: `packages/Rehla/Travelers/src/Enums/Gender.php`
- Create: `packages/Rehla/Travelers/src/Data/{TravelerData,TravelerSnapshot}.php`
- Create: `packages/Rehla/Travelers/src/Actions/{CreateTraveler,UpdateTraveler}.php`
- Create: `packages/Rehla/Travelers/src/Queries/{ListOwnedTravelers,GetOwnedTravelerSnapshot}.php`
- Create: `packages/Rehla/Travelers/src/Support/NormalizePassportNumber.php`
- Test: `packages/Rehla/Travelers/tests/Feature/TravelerOwnershipTest.php`
- Test: `packages/Rehla/Travelers/tests/Integration/PassportUniquenessTest.php`

**Mandatory Package Contract — Travelers:**
- Create/verify: `packages/Rehla/Travelers/composer.json` and `packages/Rehla/Travelers/README.md`.
- Create/verify: `packages/Rehla/Travelers/src/Providers/TravelersServiceProvider.php`.
- Create/verify: `packages/Rehla/Travelers/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Travelers/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Travelers/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-travelers`; `TravelersServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-travelers')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Travelers/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `GetOwnedTravelerSnapshot::handle(string $accountId, string $travelerId): TravelerSnapshot`.
- Produces snapshot: fullName،dateOfBirth،gender،passportNumber،passportIssuedAt،passportExpiresAt.

- [ ] **Step 1: اكتب اختبارات التطبيع والملكية**

```php
it('normalizes passport globally without revealing another owner', function (): void {
    createTraveler($ownerA, passport: ' p-12 34 ');

    expect(fn () => createTraveler($ownerB, passport: 'P1234'))
        ->toThrow(DuplicatePassport::class, ProblemCode::TravelerPassportConflict->value);
    expect(fn () => getTraveler($ownerB, travelerOf($ownerA)->id))->toThrow(TravelerNotFound::class);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Travelers/tests`

Expected: FAIL قبل schema/actions.

- [ ] **Step 3: نفذ traveler والـsnapshot**

أنشئ `travelers(id uuid, owner_id uuid, full_name, date_of_birth date, gender, passport_number, normalized_passport_number unique, passport_issued_at date, passport_expires_at date, timestamps)`. يطبق normalizer `mb_strtoupper` ثم يحذف Unicode whitespace و`-`. يمنع تاريخ إصدار بعد الانتهاء أوانتهاء قبل تاريخ الميلاد.

لا تضف nationality أوpassport country. أعد404 موحدة عند عدم الملكية لتجنب كشف المعرف.

- [ ] **Step 4: أثبت سباق uniqueness**

استخدم اتصالين PostgreSQL لإدخال الشكلين المطبعين نفسيهما، وتوقع نجاح واحد وخطأ domain واحد بلا500.

Run: `php artisan test packages/Rehla/Travelers/tests`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Travelers
git commit -m "feat(travelers): add owned traveler profiles and passport uniqueness"
```

### Task 5: Transactional Outbox and In-app Notifications

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Notifications. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R11, R12, R46, R47, R48 (supporting evidence)`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/Notifications/src/database/migrations/*_create_notification_tables.php`
- Create: `packages/Rehla/Notifications/src/Data/OutboxMessageData.php`
- Create: `packages/Rehla/Notifications/src/Contracts/OutboxWriter.php`
- Create: `packages/Rehla/Notifications/src/Infrastructure/IdentityRegistrationNotificationRecorder.php`
- Modify: `packages/Rehla/Notifications/src/Providers/NotificationsServiceProvider.php`
- Create: `packages/Rehla/Notifications/src/Actions/{AppendOutboxMessage,ClaimOutboxBatch,MarkDelivered,MarkFailed,CreateInAppNotification,MarkNotificationRead}.php`
- Create: `packages/Rehla/Notifications/src/Models/{OutboxMessage,Notification}.php`
- Test: `packages/Rehla/Notifications/tests/Integration/OutboxTransactionTest.php`
- Test: `packages/Rehla/Notifications/tests/Integration/OutboxLeaseTest.php`
- Test: `packages/Rehla/Notifications/tests/Feature/InAppNotificationTest.php`

**Mandatory Package Contract — Notifications:**
- Create/verify: `packages/Rehla/Notifications/composer.json` and `packages/Rehla/Notifications/README.md`.
- Create/verify: `packages/Rehla/Notifications/src/Providers/NotificationsServiceProvider.php`.
- Create/verify: `packages/Rehla/Notifications/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Notifications/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Notifications/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-notifications`; `NotificationsServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-notifications')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Notifications/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `OutboxWriter::append(OutboxMessageData): string` يعمل على اتصال ومعاملة المستدعي.
- Produces: `ClaimOutboxBatch::handle(int $limit, string $workerId, CarbonImmutable $now): array<OutboxEnvelope>`.
- Implements: `Rehla\Identity\Contracts\RegistrationNotificationRecorder` عبر adapter يكتب إشعار الترحيب وOutbox القنوات المفعلة في معاملة التسجيل بلا network I/O أوcommit.

- [ ] **Step 1: اكتب اختبارات المعاملة والـlease**

```php
it('rolls back outbox with the business transaction', function (): void {
    try {
        DB::transaction(function (): void {
            app(OutboxWriter::class)->append(outboxData('top_up.approved', 'top-up-1'));
            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {}

    expect(DB::table('outbox_messages')->count())->toBe(0);
});

it('commits the in-app notification with its business event', function (): void {
    createBusinessEventAndNotification();
    expect(DB::table('notifications')->count())->toBe(1);
});

it('rolls registration back when recording the welcome message fails', function (): void {
    failNextNotificationInsert();
    expect(fn () => registerCustomer())->toThrow(QueryException::class)
        ->and(DB::table('users')->count())->toBe(0)
        ->and(DB::table('notifications')->count())->toBe(0);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Notifications/tests`

Expected: FAIL قبل schema.

- [ ] **Step 3: نفذ outbox schema وclaim**

أنشئ `outbox_messages(id, event_name, aggregate_type, aggregate_id, payload_version, payload jsonb, deduplication_key unique, available_at, locked_at, locked_by, lock_token, lease_expires_at, attempts default0, delivered_at, last_error, last_trace_id, created_at)` و`notifications(id, user_id, type, payload, read_at, created_at)`. ينشأ in-app notification مع العملية، ويستخدم claim معاملة قصيرة و`FOR UPDATE SKIP LOCKED` ويولد token جديدًا عند كل claim أو استعادة lease منتهية. اربط `RegistrationNotificationRecorder` بالـadapter في `NotificationsServiceProvider`، واجعله ينضم لمعاملة Identity ولا يطلق event مطلوبًا لصحة التسجيل.

- [ ] **Step 4: أثبت التنافس والاسترداد**

باستخدام اتصالين، توقع ألا يطالب عاملان بالسجل نفسه. قدم clock بعد انتهاء lease وتوقع claim جديدًا وtoken مختلفًا، ثم أثبت أن العامل القديم لا يستطيع MarkDelivered أو MarkFailed. ينقل الفشل الخامس الرسالة إلى dead-letter دون حذفها، ويحفظ error منظفًا وtrace ID فقط.

Run: `php artisan test packages/Rehla/Notifications/tests`

Expected: PASS.

- [ ] **Step 5: حدث سجل القبول وبوابة الخطة**

Run: `composer verify && git diff --check`

Expected: PASS للهوية والتدقيق والوثائق والمسافرين والإشعارات وكل اختبارات المعمارية.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Notifications docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(notifications): add transactional outbox foundation"
```

## Plan Completion Gate

تغلق الخطة Identity وAudit وDocuments وTravelers وNotifications/Outbox foundation وتنتج منافذ التسجيل وعقود الملفات والهوية. تبقى ميزة التسجيل الفعلية `partial` ولا توصف end-to-end مكتملة حتى تنفذ الخطة 04 `RegistrationWalletInitializer` وتثبت rollback للحساب وAudit والمحفظة والإشعار وOutbox معًا. تبقى بوابة `external-delivery` مفتوحة حتى العامل والـdead-letter replay في الخطة 06.
