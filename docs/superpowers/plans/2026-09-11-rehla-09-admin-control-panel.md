# Rehla Admin Control Panel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** بناء لوحة عمليات Filament كاملة وآمنة لإدارة رحلة دون تجاوز الحزم المالكة.

**Architecture:** حزمة Admin تقرأ Query/ReadModel contracts وتنفذ named Commands من الحزم المالكة. موارد Filament لا ترتبط بنماذج أعمال قابلة للتعديل ولا تستعمل DB مباشرة.

**Tech Stack:** Filament 5، Laravel staff sessions، TOTP، Pest، Playwright.

**Spec:** `specs/contracts/admin-operations-contract.md`

**Coverage:** `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`

**Prerequisites:** إغلاق بوابة الخطة 08 وتوفر Reporting metrics وكل أوامر الإدارة والاستعلامات العامة من الحزم المالكة.

## Global Constraints

- Admin guard وcookie وsession منفصلة عن customer Web وSanctum؛ staff account وحده لا يمنح أي وصول.
- القدرات الحساسة `topups.review`, `topups.settings.manage`, `access.manage`, `audit.view` وعرض المستندات/الحقول الحساسة تتطلب TOTP وreauth حديثة خلال أربع ساعات.
- لا `DB::` أوModels عابرة للحزم أوbuilder update/delete أوraw connection أوrelationship mutation أوbusiness transitions داخل closures.
- كل permitted action يستدعي named command من owner package؛ كل list/detail يستعمل immutable ReadModel allowlist.
- كل النصوص ثنائية، والبوابة تثبت EN وAR/RTL ولوحة المفاتيح لموظف كامل وآخر محدود.

---
### Task 1: Staff Access and Filament Shell

**Task Completeness Contract:**
- **Files:** القائمة exhaustive؛ يحدث أي ملف إضافي الخطة وسجل القبول قبل التنفيذ.
- **Contracts:** Admin يستهلك Query/ReadModel وnamed Command surfaces المسجلة فقط؛ لا mutable Models.
- **Database ownership:** Admin بلا جداول أعمال؛ `Identity/Admin shell` لا يكتب إلا عبر command من المالك.
- **Authorization:** deny-by-default لكل panel/page/resource/widget/action/field مع ability مستقلة.
- **Localization:** كل label/help/notification/action في `rehla-admin` بملفي EN/AR متكافئين.
- **Error codes:** domain codes ثابتة وتتحول إلى إشعار مترجم؛ لا يكشف exception أوSQL.
- **Transaction boundary:** Admin لا يفتح معاملة المجال؛ command المالكة تنفذ القفل وAudit/notification/Outbox.
- **External I/O:** لا network داخل command؛ الملفات عبر Documents والتسليم الخارجي عبر Outbox.
- **Privacy:** field allowlist وmasking وMFA/audit للتوسيع، بلا storage key أوraw payload أوsecret.
- **RED:** الاختبار المركز يفشل أولًا بسبب السلوك الناقص المحدد.
- **GREEN:** أقل page/resource/action يمر عبر العقد المالك.
- **Expanded verification:** Admin tests ثم owner-package integrations وArchitecture وbrowser ثم formatter وإعادة المتأثر.
- **Acceptance IDs:** `R05, R43, R47` مع command وtest result وartifact قبل `verified`.
- **Recovery:** إيقاف panel/action/ability؛ لا تعديل أوحذف سجل تاريخي أومالي.
- **Commit:** الرسالة المحددة بعد المراجعة و`git diff --check` بلا خلط مهام.

**Files:**
- Create: `packages/Rehla/Admin/src/Providers/AdminServiceProvider.php`
- Create: `packages/Rehla/Admin/src/Filament/AdminPanelProvider.php`
- Create: `packages/Rehla/Admin/src/Auth/{StaffLogin,TOTPSetup,TOTPChallenge,SensitiveActionReauth}.php`
- Create: `packages/Rehla/Admin/src/Http/Middleware/{RequireStaffGuard,RequireRecentMfa}.php`
- Create: `packages/Rehla/Admin/src/Navigation/AuthorizedNavigation.php`
- Create: `packages/Rehla/Admin/src/Filament/Pages/{Dashboard,Profile,LocaleSwitcher}.php`
- Create: `packages/Rehla/Admin/tests/Feature/{StaffAccessTest,MfaReauthenticationTest,NavigationAuthorizationTest}.php`
- Create: `packages/Rehla/Admin/tests/Architecture/AdminPresentationBoundaryTest.php`

**Mandatory Package Contract — Admin:**
- Create/verify: `packages/Rehla/Admin/composer.json` and `packages/Rehla/Admin/README.md`.
- Create/verify: `packages/Rehla/Admin/src/Providers/AdminServiceProvider.php`.
- Create/verify: `packages/Rehla/Admin/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Admin/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Admin/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-admin`; `AdminServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-admin')`.
- يبدأ ملفا اللغة متطابقين ولو فارغين، ويفشل الحارس عند literal ظاهر أوModel/DB/relationship mutation.

**Interfaces:**
- Consumes: Identity staff session, `AuthorizesActor`, TOTP and actor context.
- Produces: `/admin` panel, staff login/logout, TOTP setup/challenge, locale switch and authorized navigation.

- [ ] **Step 1: Write RED proof**

Run StaffAccessTest and expect panel/provider/guard absent.

- [ ] **Step 2: Implement the smallest contract**

Configure a distinct staff guard/cookie/session, rate-limited login, session regeneration, TOTP recovery policy, four-hour sensitive-action reauth and navigation generated only from abilities. Customer sessions/tokens never enter the panel.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove staff-with-no-abilities sees no pages, limited staff sees exact navigation, customer denied, MFA stale/absent denied, recovery codes one-time, locale EN/AR and boundary test green.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Admin && git commit -m "feat(admin): add staff shell MFA and deny-by-default navigation"
```


### Task 2: Complete Operational Resource Matrix

**Task Completeness Contract:**
- **Files:** القائمة exhaustive؛ يحدث أي ملف إضافي الخطة وسجل القبول قبل التنفيذ.
- **Contracts:** Admin يستهلك Query/ReadModel وnamed Command surfaces المسجلة فقط؛ لا mutable Models.
- **Database ownership:** Admin بلا جداول أعمال؛ `all 14 administrative areas` لا يكتب إلا عبر command من المالك.
- **Authorization:** deny-by-default لكل panel/page/resource/widget/action/field مع ability مستقلة.
- **Localization:** كل label/help/notification/action في `rehla-admin` بملفي EN/AR متكافئين.
- **Error codes:** domain codes ثابتة وتتحول إلى إشعار مترجم؛ لا يكشف exception أوSQL.
- **Transaction boundary:** Admin لا يفتح معاملة المجال؛ command المالكة تنفذ القفل وAudit/notification/Outbox.
- **External I/O:** لا network داخل command؛ الملفات عبر Documents والتسليم الخارجي عبر Outbox.
- **Privacy:** field allowlist وmasking وMFA/audit للتوسيع، بلا storage key أوraw payload أوsecret.
- **RED:** الاختبار المركز يفشل أولًا بسبب السلوك الناقص المحدد.
- **GREEN:** أقل page/resource/action يمر عبر العقد المالك.
- **Expanded verification:** Admin tests ثم owner-package integrations وArchitecture وbrowser ثم formatter وإعادة المتأثر.
- **Acceptance IDs:** `R40, R43` مع command وtest result وartifact قبل `verified`.
- **Recovery:** إيقاف panel/action/ability؛ لا تعديل أوحذف سجل تاريخي أومالي.
- **Commit:** الرسالة المحددة بعد المراجعة و`git diff --check` بلا خلط مهام.

**Files:**
- Create: `packages/Rehla/Admin/src/Filament/Widgets/OverviewMetrics.php`
- Create: `packages/Rehla/Admin/src/Filament/Resources/{Service,Form,ContentPage,Customer,Traveler,Wallet,BankAccount,TopUp,Order,Execution,Notification,AccessRole,AuditEntry}Resource.php`
- Create: `packages/Rehla/Admin/src/ReadModels/{AdminTableRow,AdminDetail}.php`
- Create: `packages/Rehla/Admin/tests/Feature/{AdminResourceMatrixTest,AdminCapabilityMatrixTest,AdminReadModelTest}.php`
- Modify: `packages/Rehla/Admin/src/resources/lang/{en,ar}/messages.php`

**Interfaces:**
- Consumes: owner-package Queries/ReadModels and commands named in the matrix.
- Produces the exhaustive resource contract below; omission orundeclared area fails `AdminResourceMatrixTest`.

| Area | View ability | Mutation ability | Sensitive-field ability | Query/ReadModel | Allowed named commands | Forbidden |
|---|---|---|---|---|---|---|
| Overview | `admin.overview.view` | none | none | `ProductMetricsReader` | none | edit/export raw cohorts |
| Services & Policies | `services.view` | `services.manage` | none | `ServiceAdminReader` | `CreateService`, `UpdateServiceContent`, `ChangeServicePrice`, `PublishService`, `DeactivateService`, `PublishFulfillmentPolicy` | edit published versions/delete history |
| Forms | `forms.view` | `forms.draft`, `forms.publish` | none | `FormAdminReader` | `CreateFormDraft`, `UpdateFormDraft`, `PublishFormVersion` | edit/delete published version |
| Content | `content.view` | `content.manage` | none | `ContentAdminReader` | `CreatePage`, `UpdatePage`, `PublishPage` | unsanitized markup/direct update |
| Customers | `customers.view` | `customers.manage_status` | `customers.view_sensitive` | `CustomerAdminReader` | `SuspendCustomer`, `ReactivateCustomer` | password/token/role mutation |
| Travelers | `travelers.view` | none | `travelers.view_sensitive` | `TravelerAdminReader` | none | staff profile edit/raw passport by default |
| Wallets & Ledger | `wallets.view` | none | none | `WalletAdminReader` | none in Phase 1 | credit/debit/edit/delete/export unrestricted |
| Bank Accounts | `banks.view` | `banks.manage` | `banks.manage` | `BankAccountAdminReader` | `CreateBankAccount`, `UpdateBankAccount`, `ToggleBankAccountStatus` | delete referenced bank/direct model save |
| Top-Ups | `topups.view` | `topups.review`, `topups.settings.manage` | `documents.view_sensitive` | `TopUpAdminReader` | `ApproveTopUp`, `RejectTopUp`, `ConfigureMinimumTopUp` | manual wallet write/edit terminal decision |
| Orders | `orders.view` | none | none | `OrderAdminReader` | none | edit/delete order orsnapshots |
| Executions, Actions & Documents | `executions.view` | `executions.transition`, `executions.note` | `executions.view_sensitive`, `documents.view_sensitive` | `ExecutionAdminReader` | `TransitionExecution`, `RequestCustomerAction`, `CompleteExecution`, `CancelExecution`, `AddInternalNote` | invalid policy jump/public file URL |
| Notifications & Dead Letters | `notifications.view` | `notifications.replay` | none | `NotificationAdminReader` | `ReplayOutboxMessage` | payload edit/unreasoned replay/raw recipient PII |
| Roles & Abilities | `access.view` | `access.manage` | `access.manage` | `AccessAdminReader` | `AssignRole`, `RevokeRole`, `UpdateAbilities` | self-lockout/undeclared ability alias |
| Audit | `audit.view` | none | none | `AuditLogReader` | none | update/delete/raw metadata export |

كل ability في هذه المصفوفة تنتمي حرفيًا إلى السجل الكامل في `specs/cross-cutting/security-and-privacy.md`. تبقى حقول الإشعارات وAudit التي لا يعرّف لها السجل قدرة كشف مستقلة masked دائمًا؛ لا تخترع الواجهة ability لتجاوز ذلك.


- [ ] **Step 1: Write RED proof**

Generate one failing dataset row per matrix cell; first failure is Overview without `admin.overview.view`.

- [ ] **Step 2: Implement the smallest contract**

Build list/detail pages from readonly DTOs and custom actions from named commands. Render no generic Edit/Delete for immutable/history resources. Make absent abilities remove navigation and actions and still enforce server-side denial.

- [ ] **Step 3: Verify focused and expanded behavior**

Run the three matrix tests across staff-none, staff-limited, finance, operations and access-admin actors. Assert every allowed/forbidden action, query, field and empty state in both locales.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Admin && git commit -m "feat(admin): add complete operational resource matrix"
```


### Task 3: Command-Only Mutation and Read-Model Guards

**Task Completeness Contract:**
- **Files:** القائمة exhaustive؛ يحدث أي ملف إضافي الخطة وسجل القبول قبل التنفيذ.
- **Contracts:** Admin يستهلك Query/ReadModel وnamed Command surfaces المسجلة فقط؛ لا mutable Models.
- **Database ownership:** Admin بلا جداول أعمال؛ `Admin source architecture` لا يكتب إلا عبر command من المالك.
- **Authorization:** deny-by-default لكل panel/page/resource/widget/action/field مع ability مستقلة.
- **Localization:** كل label/help/notification/action في `rehla-admin` بملفي EN/AR متكافئين.
- **Error codes:** domain codes ثابتة وتتحول إلى إشعار مترجم؛ لا يكشف exception أوSQL.
- **Transaction boundary:** Admin لا يفتح معاملة المجال؛ command المالكة تنفذ القفل وAudit/notification/Outbox.
- **External I/O:** لا network داخل command؛ الملفات عبر Documents والتسليم الخارجي عبر Outbox.
- **Privacy:** field allowlist وmasking وMFA/audit للتوسيع، بلا storage key أوraw payload أوsecret.
- **RED:** الاختبار المركز يفشل أولًا بسبب السلوك الناقص المحدد.
- **GREEN:** أقل page/resource/action يمر عبر العقد المالك.
- **Expanded verification:** Admin tests ثم owner-package integrations وArchitecture وbrowser ثم formatter وإعادة المتأثر.
- **Acceptance IDs:** `R40, R60, R64` مع command وtest result وartifact قبل `verified`.
- **Recovery:** إيقاف panel/action/ability؛ لا تعديل أوحذف سجل تاريخي أومالي.
- **Commit:** الرسالة المحددة بعد المراجعة و`git diff --check` بلا خلط مهام.

**Files:**
- Create: `packages/Rehla/Admin/tests/Architecture/{NoBusinessModelsTest,NoDirectDatabaseMutationTest,FilamentCommandOnlyTest,ReadModelImmutabilityTest}.php`
- Create: `tests/Architecture/AdminContractSurfaceTest.php`
- Modify: `packages/Rehla/Admin/README.md`

**Interfaces:**
- Consumes: package/contract maps and Admin PHP token stream.
- Produces: CI rejection for cross-package `Models`, `DB::`, query-builder mutations, raw connections, relationship writes, model-bound forms and business transition closures.

- [ ] **Step 1: Write RED proof**

Seed fixtures containing aliased imports, fully qualified Models, `DB::table()->update`, relation attach/sync, raw PDO and action closure transition; require one precise failure each plus false-positive fixtures.

- [ ] **Step 2: Implement the smallest contract**

Implement token-aware guard resolving namespaces/import aliases and method chains. Permit technical Filament state only through explicit allowlist; require every action class to reference a public command surface in the contract map.

- [ ] **Step 3: Verify focused and expanded behavior**

Run Admin architecture tests, root architecture suite and mutation resource feature tests after formatter. Prove comments/strings/tests do not cause false positives and dynamic bypasses fail.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Admin/tests tests/Architecture packages/Rehla/Admin/README.md && git commit -m "test(admin): enforce command-only presentation boundaries"
```


### Task 4: Sensitive Data, Documents and Export Policy

**Task Completeness Contract:**
- **Files:** القائمة exhaustive؛ يحدث أي ملف إضافي الخطة وسجل القبول قبل التنفيذ.
- **Contracts:** Admin يستهلك Query/ReadModel وnamed Command surfaces المسجلة فقط؛ لا mutable Models.
- **Database ownership:** Admin بلا جداول أعمال؛ `Identity/Travelers/Documents/Wallet/TopUps/Notifications/Audit sensitive data` لا يكتب إلا عبر command من المالك.
- **Authorization:** deny-by-default لكل panel/page/resource/widget/action/field مع ability مستقلة.
- **Localization:** كل label/help/notification/action في `rehla-admin` بملفي EN/AR متكافئين.
- **Error codes:** domain codes ثابتة وتتحول إلى إشعار مترجم؛ لا يكشف exception أوSQL.
- **Transaction boundary:** Admin لا يفتح معاملة المجال؛ command المالكة تنفذ القفل وAudit/notification/Outbox.
- **External I/O:** لا network داخل command؛ الملفات عبر Documents والتسليم الخارجي عبر Outbox.
- **Privacy:** field allowlist وmasking وMFA/audit للتوسيع، بلا storage key أوraw payload أوsecret.
- **RED:** الاختبار المركز يفشل أولًا بسبب السلوك الناقص المحدد.
- **GREEN:** أقل page/resource/action يمر عبر العقد المالك.
- **Expanded verification:** Admin tests ثم owner-package integrations وArchitecture وbrowser ثم formatter وإعادة المتأثر.
- **Acceptance IDs:** `R40, R43, R46, R48` مع command وtest result وartifact قبل `verified`.
- **Recovery:** إيقاف panel/action/ability؛ لا تعديل أوحذف سجل تاريخي أومالي.
- **Commit:** الرسالة المحددة بعد المراجعة و`git diff --check` بلا خلط مهام.

**Files:**
- Create: `packages/Rehla/Admin/src/Security/{SensitiveFieldPolicy,FieldMasker,ExportPolicy}.php`
- Create: `packages/Rehla/Admin/src/Filament/Actions/{ViewPrivateDocument,ExportAuthorizedRows}.php`
- Create: `packages/Rehla/Admin/tests/Feature/{SensitiveFieldMatrixTest,PrivateDocumentAccessTest,AdminExportPolicyTest}.php`
- Modify: `packages/Rehla/Admin/src/resources/lang/{en,ar}/messages.php`

**Interfaces:**
- Consumes: `AuthorizesActor`, Documents authorization/short-lived delivery, owner read models and `AuditWriter`.
- Produces: field allowlists and audited reveal/download/export decisions; no permanent URLs.

- [ ] **Step 1: Write RED proof**

Write matrix tests for passport, document, wallet/ledger, bank, receipt, notification body, Audit metadata and actor identity; expect masked oromitted fields without each specific ability.

- [ ] **Step 2: Implement the smallest contract**

Require recent MFA for reveal/download/export, audit actor/subject/reason/field set/correlation, safe CSV formula escaping and row/field allowlists. Documents reauthorizes and grants at most 15-minute session-bound delivery with nosniff.

- [ ] **Step 3: Verify focused and expanded behavior**

Prove limited staff cannot infer values from HTML, Livewire payload, export, logs ornotification text; expired/reused document grants fail; Audit payload is redacted and immutable.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Admin && git commit -m "feat(admin): protect sensitive fields documents and exports"
```


### Task 5: End-to-End Staff Operations Journey

**Task Completeness Contract:**
- **Files:** القائمة exhaustive؛ يحدث أي ملف إضافي الخطة وسجل القبول قبل التنفيذ.
- **Contracts:** Admin يستهلك Query/ReadModel وnamed Command surfaces المسجلة فقط؛ لا mutable Models.
- **Database ownership:** Admin بلا جداول أعمال؛ `complete admin operations journey` لا يكتب إلا عبر command من المالك.
- **Authorization:** deny-by-default لكل panel/page/resource/widget/action/field مع ability مستقلة.
- **Localization:** كل label/help/notification/action في `rehla-admin` بملفي EN/AR متكافئين.
- **Error codes:** domain codes ثابتة وتتحول إلى إشعار مترجم؛ لا يكشف exception أوSQL.
- **Transaction boundary:** Admin لا يفتح معاملة المجال؛ command المالكة تنفذ القفل وAudit/notification/Outbox.
- **External I/O:** لا network داخل command؛ الملفات عبر Documents والتسليم الخارجي عبر Outbox.
- **Privacy:** field allowlist وmasking وMFA/audit للتوسيع، بلا storage key أوraw payload أوsecret.
- **RED:** الاختبار المركز يفشل أولًا بسبب السلوك الناقص المحدد.
- **GREEN:** أقل page/resource/action يمر عبر العقد المالك.
- **Expanded verification:** Admin tests ثم owner-package integrations وArchitecture وbrowser ثم formatter وإعادة المتأثر.
- **Acceptance IDs:** `R19, R21, R36, R39, R41, R42, R44, R45, R46, R57, R61, R63` مع command وtest result وartifact قبل `verified`.
- **Recovery:** إيقاف panel/action/ability؛ لا تعديل أوحذف سجل تاريخي أومالي.
- **Commit:** الرسالة المحددة بعد المراجعة و`git diff --check` بلا خلط مهام.

**Files:**
- Create: `tests/EndToEnd/admin-operations-journey.spec.ts`
- Create: `tests/EndToEnd/admin-limited-staff.spec.ts`
- Create: `tests/EndToEnd/admin-accessibility-rtl.spec.ts`
- Test: `packages/Rehla/Admin/tests/Feature/AdminConcurrentDecisionTest.php`
- Modify: `package.json`

**Interfaces:**
- Consumes: Tasks 1–4 plus deterministic domain fixtures.
- Produces: browser evidence for full and limited staff in EN/AR/RTL and PostgreSQL concurrency evidence.

- [ ] **Step 1: Write RED proof**

Run browser specs; expect missing full sequence and denied-action assertions.

- [ ] **Step 2: Implement the smallest contract**

Cover staff login/TOTP; bank and top-up review/atomic approval; service/policy/form publication; execution transition/action/completion with policy-conditional document; notifications/dead-letter; Audit inspection. Repeat with limited staff and assert every excluded navigation, URL and action denied.

- [ ] **Step 3: Verify focused and expanded behavior**

Run concurrent top-up decision, Admin feature/architecture suites, and browser specs with keyboard/axe/RTL at desktop/tablet. Verify one credit, immutable history, exact Audit/Outbox and no hardcoded text.

- [ ] **Step 4: Commit**

```bash
git add tests/EndToEnd packages/Rehla/Admin package.json && git commit -m "test(admin): prove complete staff operations journey"
```


### Task 6: Admin Verification and Release Handoff

**Task Completeness Contract:**
- **Files:** القائمة exhaustive؛ يحدث أي ملف إضافي الخطة وسجل القبول قبل التنفيذ.
- **Contracts:** Admin يستهلك Query/ReadModel وnamed Command surfaces المسجلة فقط؛ لا mutable Models.
- **Database ownership:** Admin بلا جداول أعمال؛ `all Admin artifacts and acceptance rows` لا يكتب إلا عبر command من المالك.
- **Authorization:** deny-by-default لكل panel/page/resource/widget/action/field مع ability مستقلة.
- **Localization:** كل label/help/notification/action في `rehla-admin` بملفي EN/AR متكافئين.
- **Error codes:** domain codes ثابتة وتتحول إلى إشعار مترجم؛ لا يكشف exception أوSQL.
- **Transaction boundary:** Admin لا يفتح معاملة المجال؛ command المالكة تنفذ القفل وAudit/notification/Outbox.
- **External I/O:** لا network داخل command؛ الملفات عبر Documents والتسليم الخارجي عبر Outbox.
- **Privacy:** field allowlist وmasking وMFA/audit للتوسيع، بلا storage key أوraw payload أوsecret.
- **RED:** الاختبار المركز يفشل أولًا بسبب السلوك الناقص المحدد.
- **GREEN:** أقل page/resource/action يمر عبر العقد المالك.
- **Expanded verification:** Admin tests ثم owner-package integrations وArchitecture وbrowser ثم formatter وإعادة المتأثر.
- **Acceptance IDs:** `R05, R40, R43, R61, R63, R65` مع command وtest result وartifact قبل `verified`.
- **Recovery:** إيقاف panel/action/ability؛ لا تعديل أوحذف سجل تاريخي أومالي.
- **Commit:** الرسالة المحددة بعد المراجعة و`git diff --check` بلا خلط مهام.

**Files:**
- Create: `packages/Rehla/Admin/tests/Contract/AdminPlanAcceptanceTest.php`
- Create: `docs/releases/evidence/admin-control-panel.md`
- Modify: `docs/requirements/rehla-phase-1-acceptance.csv`
- Test: `tests/Architecture/AdminContractSurfaceTest.php`

**Interfaces:**
- Consumes: resource/capability/field matrices, architecture results and browser artifacts.
- Produces: one evidence link for every Admin acceptance row and a handoff to plan 10; no feature implementation is deferred to release.

- [ ] **Step 1: Write RED proof**

Make AdminPlanAcceptanceTest fail on any matrix row without test name/result/artifact orany `planned|red|green` Admin acceptance status.

- [ ] **Step 2: Implement the smallest contract**

Record exact commands, commit SHA, actor matrix, locale/accessibility artifacts and PostgreSQL concurrency result. Mark rows verified only when the named proof exists.

- [ ] **Step 3: Verify focused and expanded behavior**

Run all Admin, owner integration, Architecture and E2E suites from a fresh process; ensure zero incomplete Admin rows, zero direct mutation, zero sensitive leak and clean diff.

- [ ] **Step 4: Commit**

```bash
git add packages/Rehla/Admin docs/releases/evidence/admin-control-panel.md docs/requirements/rehla-phase-1-acceptance.csv && git commit -m "docs(admin): record control panel release evidence"
```

## Final Admin Gate

تثبت البوابة Tasks 1–6: guard/session/TOTP، الموارد الأربعة عشر كاملة، القدرة لكل view/mutation/field، readonly Queries وnamed Commands، الحراس المعمارية، سياسات الحقول والمستندات والتصدير، ورحلة الموظف الكامل والمحدود باللغتين وRTL ولوحة المفاتيح. لا تنتقل الخطة 10 إلى release proof حتى يصبح Admin acceptance handoff مكتملًا دون feature مؤجل.
