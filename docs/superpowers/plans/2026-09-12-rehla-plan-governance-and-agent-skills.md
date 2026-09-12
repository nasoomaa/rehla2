# Rehla Plan Governance and Agent Skills Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** تحويل خطط بناء رحلة إلى برنامج كامل من عشر مراحل مرتبة وقابل للتحقق آليًا، وإنشاء مهارات محلية تلزم وكلاء البرمجة ببنية الحزم والترجمة والأمان والاختبارات والتشغيل الصحيحة.

**Architecture:** تبقى خرائط الحزم والعقود والجداول مصادر حقيقة معمارية، وتضاف إليها `rehla-plan-contract.json` لملكية الخطط والمهام والتسلسل. يقسم ملف الواجهات الضخم إلى خطط Web وAPI وAdmin وOperations ظاهرة، ويطبق مدقق Python عقدًا موحدًا على كل حزمة وكل خطة، بينما تقدم `.agents/skills` تعليمات قصيرة ومتخصصة مرتبطة بالمصادر القانونية.

**Tech Stack:** Markdown، JSON، CSV، Python 3 standard library، Agent Skills (`SKILL.md`)، Laravel package conventions، PHPUnit/Pest plan contracts.

**Spec:** `docs/superpowers/specs/2026-09-12-rehla-plan-governance-and-agent-skills-design.md`

## Global Constraints

- نفذ هذه الخطة بعد نجاح وإغلاق `docs/superpowers/plans/2026-09-12-rehla-package-structure-and-contract-alignment.md`؛ لا تخلط التزامات الخطتين.
- يبقى `docs/REHLA-PROJECT-CONCEPT-AND-USER-JOURNEY.md` مرجع المنتج، وتبقى R01–R65 مجموعة المتطلبات الكاملة للإصدار الأول.
- تبقى `rehla-package-map.json` و`rehla-package-contract-map.json` و`table-ownership.json` مصادر الاعتماد والعقود والملكية.
- تتكون سلسلة التنفيذ من عشر خطط ظاهرة: Foundation، Platform، Catalog، Wallet، Purchasing، Reporting، Web، API، Admin، Operations/Release.
- كل واحدة من الحزم الـ19 لها مهمة إنشاء مالكة واحدة، ولا تسبق خطةٌ اعتمادها التشغيلي.
- يحتوي كل package منذ إنشائه `src/resources/lang/en/messages.php` و`src/resources/lang/ar/messages.php` واختبار key parity، حتى إن بدأ الملفان بمصفوفتين فارغتين.
- يستعمل كل Package Service Provider `loadTranslationsFrom` من مسار الحزمة تحت `src/`.
- لا تحتوي Actions أوControllers أوJobs أوPolicies أوFilament definitions نصوصًا مرئية للمستخدم أوالموظف؛ تستخدم translation keys.
- Admin خطة مستقلة كاملة، ولا تكتب مباشرة إلى Models أوDB أوrelationships لحزم الأعمال.
- يبدأ كل تغيير باختبار RED ذي فشل متوقع محدد، ثم أقل تعديل GREEN، ثم تحقق مركز، ثم تحقق موسع، ثم commit مستقل.
- لا يوصف أي قسم بأنه مكتمل بلا manifest وتغطية واختبارات وروابط صالحة.
- لا scaffolding لتطبيق Laravel في هذه الخطة؛ الناتج هو الخطط والمدققات والمهارات وتقارير الإثبات.

---

## File Responsibility Map

| File | Responsibility |
|---|---|
| `docs/architecture/rehla-plan-contract.json` | القائمة القانونية لترتيب الخطط وملكية الحزم والآثار والبوابات |
| `scripts/docs_checks/plan_quality.py` | التحقق من خطة البرنامج وعقود كل package task |
| `scripts/docs_checks/agent_skills.py` | التحقق من بنية مهارات `.agents` ومراجعها |
| `tests/documentation/test_plan_quality.py` | fixtures للتسلسل والملكية والترجمة والتغطية |
| `tests/documentation/test_agent_skills.py` | fixtures لبنية المهارة والفهرس والتفعيل |
| `docs/superpowers/plans/2026-09-11-rehla-platform-build.md` | الخطة الرئيسية ومراحل 01–10 |
| `docs/superpowers/plans/2026-09-11-rehla-07-customer-web.md` | خطة Web المستقلة |
| `docs/superpowers/plans/2026-09-11-rehla-08-customer-api.md` | خطة REST API المستقلة |
| `docs/superpowers/plans/2026-09-11-rehla-09-admin-control-panel.md` | خطة Filament Admin المستقلة |
| `docs/superpowers/plans/2026-09-11-rehla-10-operations-security-release.md` | خطة التشغيل والأمان والإصدار المستقلة |
| `docs/superpowers/plans/2026-09-11-rehla-07-interfaces-operations-release.md` | ملف إحالة deprecated بلا مهام تنفيذ مكررة |
| `docs/superpowers/plans/2026-09-12-rehla-plan-manifest.csv` | manifest لكل خطة ودورها وترتيبها وحالتها |
| `docs/reviews/2026-09-12-rehla-build-plans-and-agent-skills-audit.md` | التقرير النهائي والفجوات المصححة والدليل |
| `.agents/README.md` | فهرس اكتشاف المهارات وترتيب استخدامها |
| `.agents/skills/*/SKILL.md` | تسع مهارات Rehla متخصصة |

### Task 1: Add a Failing Plan-Quality Contract

**Files:**
- Create: `scripts/docs_checks/plan_quality.py`
- Create: `tests/documentation/test_plan_quality.py`
- Modify: `scripts/docs_checks/run.py`

**Interfaces:**
- Produces: `validate_plan_sequence(contract)`, `validate_package_owners(contract)`, `validate_package_task(text, package)`, و`check()`.
- Consumes: ملفات الخطط و`rehla-plan-contract.json` الذي سينشأ في Task 2.

- [ ] **Step 1: Write fixtures for sequence and ownership failures**

```python
class PlanContractTest(TestCase):
    def test_sequence_requires_exactly_ten_unique_ordered_plans(self) -> None:
        contract = fixture_contract(plan_count=9)
        with self.assertRaisesRegex(CheckFailure, "expected 10 implementation plans"):
            validate_plan_sequence(contract)

    def test_every_package_has_one_build_owner(self) -> None:
        contract = fixture_contract()
        contract["packages"]["Core"]["owner_task"] = ""
        with self.assertRaisesRegex(CheckFailure, "Core.*owner"):
            validate_package_owners(contract)
```

- [ ] **Step 2: Write a package-task fixture that requires localization**

```python
def test_package_task_requires_provider_locales_and_translation_test(self) -> None:
    with self.assertRaisesRegex(CheckFailure, "resources/lang/ar/messages.php"):
        validate_package_task("Create: packages/Rehla/Core/composer.json", "Core")
```

- [ ] **Step 3: Run RED before the validator exists**

Run: `python3 -m unittest tests.documentation.test_plan_quality -v`

Expected: FAIL importing the missing validation functions.

- [ ] **Step 4: Implement the narrow validator and register the group**

`run.py` adds:

```python
"plans": "scripts.docs_checks.plan_quality",
```

`plan_quality.py` must report exact missing plan IDs, duplicate package owners, missing file fragments, broken task references, and topological inversions. It must not silently skip a malformed contract row.

- [ ] **Step 5: Prove fixture GREEN and live corpus RED**

Run: `python3 -m unittest tests.documentation.test_plan_quality -v`

Expected: PASS للـfixtures.

Run: `python3 -m scripts.docs_checks.run --group plans`

Expected: FAIL لأن `rehla-plan-contract.json` والخطط 08–10 غير موجودة بعد.

- [ ] **Step 6: Commit**

```bash
git add scripts/docs_checks/run.py scripts/docs_checks/plan_quality.py tests/documentation/test_plan_quality.py
git commit -m "test(docs): add Rehla plan quality contract"
```

### Task 2: Create the Machine-Readable Ten-Plan Program

**Files:**
- Create: `docs/architecture/rehla-plan-contract.json`
- Modify: `scripts/docs_checks/plan_quality.py`
- Modify: `tests/documentation/test_plan_quality.py`

**Interfaces:**
- Consumes: package map schema 2، contract map schema 1، table ownership schema 1.
- Produces: ten `implementation_plans` records، 19 `packages` records، 65 `requirements` records، وcross-plan gates.

- [ ] **Step 1: Define the exact ordered plan IDs**

```json
[
  "01-foundation-core",
  "02-identity-platform-services",
  "03-catalog-forms-content",
  "04-wallet-topups",
  "05-orders-purchasing-fulfillment",
  "06-reporting-integrations",
  "07-customer-web",
  "08-customer-api",
  "09-admin-control-panel",
  "10-operations-security-release"
]
```

Each record contains `id`, `order`, `path`, `depends_on`, `outcome`, `owned_packages`, and `release_gate`.

- [ ] **Step 2: Record every package owner and mandatory artifacts**

Each package record contains:

```json
{
  "owner_plan": "02-identity-platform-services",
  "owner_task": "Task 1",
  "mandatory_artifacts": [
    "composer.json",
    "README.md",
    "src/Providers/IdentityServiceProvider.php",
    "src/resources/lang/en/messages.php",
    "src/resources/lang/ar/messages.php",
    "tests/Architecture/TranslationCompletenessTest.php"
  ],
  "required_test_kinds": ["unit", "feature", "architecture"]
}
```

Use the actual owner plan/task for all 19 packages. Add integration/concurrency/browser kinds only where behavior requires them.

The owner mapping is exact:

| Package | Owner plan | Owner task |
|---|---|---|
| Core | 01 | Task 5 |
| Identity | 02 | Task 1 |
| Audit | 02 | Task 2 |
| Documents | 02 | Task 3 |
| Travelers | 02 | Task 4 |
| Notifications | 02 | Task 5 |
| Catalog | 03 | Task 1 |
| Forms | 03 | Task 2 |
| Content | 03 | Task 3 |
| Wallet | 04 | Task 1 |
| TopUps | 04 | Task 2 |
| Orders | 05 | Task 1 |
| Purchasing | 05 | Task 2 |
| Fulfillment | 05 | Task 3 |
| Reporting | 06 | Task 1 |
| Integrations | 06 | Task 3 |
| Web | 07 | Task 1 |
| Api | 08 | Task 1 |
| Admin | 09 | Task 1 |

- [ ] **Step 3: Record R01–R65 and gates**

Each requirement row contains `requirement_id`, `owner_plan`, `owner_task`, `acceptance_source`, and `final_evidence_plan`. IDs must equal exactly `{R01..R65}` and agree with both existing coverage CSV files.

- [ ] **Step 4: Validate against all architecture maps**

Require package names equal the 19 package-map keys. Require every package dependency's owner plan order to be less than or equal to the consumer's owner plan, with explicit inversion bindings allowed only when declared by the contract map. Require every table owner to have migrations scheduled in its owner task.

- [ ] **Step 5: Run focused verification**

Run: `python3 -m unittest tests.documentation.test_plan_quality -v`

Expected: PASS للقارئ والمخطط والملكية، بينما live plan check يبقى RED لغياب الخطط الجديدة.

- [ ] **Step 6: Commit**

```bash
git add docs/architecture/rehla-plan-contract.json scripts/docs_checks/plan_quality.py tests/documentation/test_plan_quality.py
git commit -m "docs: define ten-plan Rehla build program"
```

### Task 3: Enforce the Universal Package and Localization Contract

**Files:**
- Modify: `docs/superpowers/plans/2026-09-11-rehla-01-foundation-core.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-02-identity-platform-services.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-03-catalog-forms-content.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-04-wallet-topups.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-06-reporting-integrations.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-platform-build.md`
- Modify: `docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md`
- Modify: `scripts/docs_checks/plan_quality.py`
- Test: `tests/documentation/test_plan_quality.py`

**Interfaces:**
- Produces: uniform file lists and gates for all 19 package creation tasks.
- Consumes: mandatory artifacts from the plan contract.

- [ ] **Step 1: Add the package generator obligations to Foundation**

The generator creates for every package:

```text
src/Providers/<Package>ServiceProvider.php
src/resources/lang/en/messages.php
src/resources/lang/ar/messages.php
tests/Architecture/TranslationCompletenessTest.php
```

The provider includes:

```php
$this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-'.strtolower($package));
```

The generated test recursively compares English and Arabic keys and fails on a scalar/array shape mismatch.

- [ ] **Step 2: Add strict language rules to the master plan**

Require empty paired arrays for packages with no messages, bilingual keys in the same commit as any new public text, no hardcoded customer/staff copy in PHP presentation or domain code, bilingual publication validation, API language-neutral codes, and AR/RTL browser proof.

- [ ] **Step 3: Update every package-owning task**

Every creation task names its provider, both language files, parity test, namespace, README, contracts, authorization, transaction boundary, error codes, relevant tests, focused command, expanded command, and acceptance IDs. Do not add unused runtime directories; only locale files are universal.

- [ ] **Step 4: Add hardcoded-text and provider-load tests to plans**

Foundation schedules `PackageTranslationContractTest` that parses provider registration and locale arrays. Web/API/Admin plans schedule an AST/token guard rejecting visible literal strings outside tests, seed fixtures, and explicitly allowlisted technical constants.

- [ ] **Step 5: Verify**

Run: `python3 -m scripts.docs_checks.run --group plans`

Expected: package-task checks pass for plans 01–06; failure advances to the absent/split interface plans.

- [ ] **Step 6: Commit**

```bash
git add docs/superpowers/plans docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md scripts/docs_checks/plan_quality.py tests/documentation/test_plan_quality.py
git commit -m "docs: enforce package localization and task gates"

```

### Task 4: Split the Interface Plan into Four Visible Plans

**Files:**
- Create: `docs/superpowers/plans/2026-09-11-rehla-07-customer-web.md`
- Create: `docs/superpowers/plans/2026-09-11-rehla-08-customer-api.md`
- Create: `docs/superpowers/plans/2026-09-11-rehla-09-admin-control-panel.md`
- Create: `docs/superpowers/plans/2026-09-11-rehla-10-operations-security-release.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-07-interfaces-operations-release.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-platform-build.md`
- Modify: `docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md`
- Modify: `docs/architecture/rehla-plan-contract.json`
- Test: `tests/documentation/test_plan_quality.py`

**Interfaces:**
- Consumes: the final domain contracts after plan 06.
- Produces: independently reviewable Web, API, Admin, and release deliverables.

- [ ] **Step 1: Move Web Tasks 1–2 into plan 07**

Preserve all file lists, interfaces, tests, RED/GREEN commands, and commits. Add package localization artifacts and a final Web gate covering public browsing, account, traveler, top-up receipt replacement, checkout, order/execution tracking, customer actions, documents, notifications, EN, AR, RTL, ownership, accessibility, and responsive behavior.

- [ ] **Step 2: Move API Tasks 3–4 into plan 08**

Renumber them as Tasks 1–2. Preserve the exact 28-operation set and add an explicit final gate for OpenAPI equality, Sanctum customer-only abilities, rate limits, content types, Problem Details identifiers, all ownership outcomes, idempotency, uploads, and receipt replacement.

- [ ] **Step 3: Move and expand Admin Task 5 into plan 09**

Use the complete contract in Task 7 of this plan. The file must be a standalone plan with its own goal, architecture, tech stack, spec, constraints, tasks, and release gate.

- [ ] **Step 4: Move Tasks 6–9 into plan 10**

Renumber localization/browser E2E, health/observability, deployment/restore, and final release as Tasks 1–4. Preserve every verification command and acceptance artifact.

- [ ] **Step 5: Replace the old plan with a non-authoritative redirect**

The old `07-interfaces-operations-release.md` contains no `### Task` headings. It explains the split and links directly to plans 07–10, preventing two executable copies from drifting.

- [ ] **Step 6: Update the master sequence and validate no duplicate tasks**

Run: `python3 -m scripts.docs_checks.run --group plans`

Expected: `10 plans, 19 package owners, 0 duplicate executable tasks`; remaining failures may concern detailed package/admin coverage fixed by later tasks.

- [ ] **Step 7: Commit**

```bash
git add docs/superpowers/plans docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md docs/architecture/rehla-plan-contract.json
git commit -m "docs: split Web API Admin and release plans"
```

### Task 5: Reconcile Domain Plans and Cross-Plan Gates

**Files:**
- Modify: `docs/superpowers/plans/2026-09-11-rehla-01-foundation-core.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-02-identity-platform-services.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-03-catalog-forms-content.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-04-wallet-topups.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-06-reporting-integrations.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-platform-build.md`
- Modify: `docs/architecture/rehla-plan-contract.json`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`
- Test: `tests/documentation/test_plan_quality.py`

**Interfaces:**
- Consumes: all domain specs, three architecture maps, and R01–R65 coverage.
- Produces: valid prerequisites and explicit acceptance handoffs for plans 01–06.

- [ ] **Step 1: Validate every task against a fixed completeness checklist**

For each task, record evidence for: files, contracts, database ownership, authorization, localization, error codes, transaction boundary, external I/O, privacy, RED, GREEN, expanded verification, acceptance IDs, recovery, and commit. The checker reports every missing category by plan and task.

- [ ] **Step 2: Correct known ordering dependencies**

Identity defines the registration ports in plan 02 but its end-to-end acceptance gate remains open until Notifications and Wallet implementations exist. Catalog owns fulfillment-policy drafts/versions before Purchasing. Notifications foundation precedes TopUps and checkout. `ExecutionCreator` is owned by Purchasing and implemented by Fulfillment. Reporting follows all its declared read sources.

- [ ] **Step 3: Make cross-plan handoffs explicit**

Every plan header lists prerequisites and every final gate lists produced contracts and evidence consumed by the next plan. A later plan cannot retroactively satisfy an earlier plan's Definition of Done; the earlier feature is marked partial until its scheduled closing integration test.

- [ ] **Step 4: Reconcile table and contract creation tasks**

Every contract-map surface maps to one task and every table maps to one migration task in its owner package. Policy versions belong to Catalog, purchase attempts to Purchasing, delivery attempts to Notifications, and reporting objects to Reporting.

- [ ] **Step 5: Verify requirements and topology**

Run: `python3 -m scripts.docs_checks.run --group package && python3 -m scripts.docs_checks.run --group semantics && python3 -m scripts.docs_checks.run --group plans`

Expected: exact dependency/contract/table equality and no plan-order inversion.

- [ ] **Step 6: Commit**

```bash
git add docs/superpowers/plans/2026-09-11-rehla-0*.md docs/superpowers/plans/2026-09-11-rehla-platform-build.md docs/architecture/rehla-plan-contract.json
git commit -m "docs: reconcile Rehla plan dependencies and gates"
```

### Task 6: Complete the Customer Web and REST API Plans

**Files:**
- Modify: `docs/superpowers/plans/2026-09-11-rehla-07-customer-web.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-08-customer-api.md`
- Modify: `docs/architecture/rehla-plan-contract.json`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`
- Test: `tests/documentation/test_plan_quality.py`

**Interfaces:**
- Web consumes domain Actions/Queries with session authentication.
- API produces the exact 28 REST operations and never acts as Web's internal transport.

- [ ] **Step 1: Expand Web into explicit feature tasks**

Create separate tasks for public/catalog/content/inquiry, customer auth/account shell, travelers/wallet, top-ups and receipt replacement, checkout, order/execution/action tracking, documents/notifications, and browser accessibility/localization. Each task names routes/components/requests/view models, ownership tests, locale files, and focused commands.

- [ ] **Step 2: Expand API into explicit contract tasks**

Create separate tasks for API foundation/auth/Problem Details, public catalog, travelers/wallet, top-ups, uploads/documents, checkout/orders/executions, notifications, and OpenAPI release proof. Every operation maps to controller, request, resource, Action/Query, auth, ownership, rate limit, error set, and OpenAPI operation ID.

- [ ] **Step 3: Enforce transport boundaries**

Add tests planned to reject HTTP calls from Web to `/api/v1`, mutable Models in either presentation package, and staff abilities on API customer tokens.

- [ ] **Step 4: Verify exact route and requirement coverage**

Run: `python3 -m scripts.docs_checks.run --group api && python3 -m scripts.docs_checks.run --group plans`

Expected: 28/28 API operations and all Web/API coverage rows resolve to exact task headings.

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/plans/2026-09-11-rehla-07-customer-web.md docs/superpowers/plans/2026-09-11-rehla-08-customer-api.md docs/architecture/rehla-plan-contract.json docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv
git commit -m "docs: complete customer Web and API build plans"
```

### Task 7: Build a Complete Standalone Admin Plan

**Files:**
- Modify: `docs/superpowers/plans/2026-09-11-rehla-09-admin-control-panel.md`
- Modify: `docs/architecture/rehla-plan-contract.json`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`
- Test: `tests/documentation/test_plan_quality.py`

**Interfaces:**
- Consumes: Reporting metrics and domain admin Commands/Queries/ReadModels only.
- Produces: isolated Filament operations panel with no direct business-table writes.

- [ ] **Step 1: Define staff access and shell task**

List Admin guard/cookie/session, Filament panel provider, login, TOTP setup/challenge, four-hour reauthentication for sensitive abilities, navigation authorization, EN/AR locale switch, and deny-by-default tests.

- [ ] **Step 2: Define the complete resource matrix**

Create exact task file lists and tests for Overview; Services and policies; Forms; Content; Customers; Travelers; Wallets; Bank Accounts; Top-Ups; Orders; Executions/actions/documents; Notifications/dead letters; Roles/Abilities; Audit. Each row names view ability, mutation ability, sensitive-field ability, query contract, allowed command, and forbidden actions.

- [ ] **Step 3: Define command-only mutation and read-model guards**

Plan token/architecture tests that reject cross-package `Models`, `DB::`, builder `update/delete`, raw connections, relationship mutation, model-bound Filament forms, and closures containing business transitions. Every permitted button maps to a named owner-package command.

- [ ] **Step 4: Define sensitive data and export rules**

Passport, document, wallet, bank, notification body, Audit metadata, and actor information each receive an explicit field allowlist, ability, audit rule, masking rule, and export policy. Downloads use Documents authorization and short-lived delivery only.

- [ ] **Step 5: Define the end-to-end staff journey**

The browser test covers staff login and MFA, bank/top-up review, atomic approval, service/policy/form publication, execution transition, customer action, completion with policy-conditional document, notification/dead-letter view, Audit inspection, and a limited staff member denied at every excluded action.

- [ ] **Step 6: Verify Admin completeness**

Run: `python3 -m scripts.docs_checks.run --group plans`

Expected: all required Admin areas, capability rows, contracts, localization files, architecture guards, and acceptance mappings pass.

- [ ] **Step 7: Commit**

```bash
git add docs/superpowers/plans/2026-09-11-rehla-09-admin-control-panel.md docs/architecture/rehla-plan-contract.json docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv
git commit -m "docs: define complete Rehla Admin build plan"
```

### Task 8: Complete Operations, Security, and Release Sequencing

**Files:**
- Modify: `docs/superpowers/plans/2026-09-11-rehla-10-operations-security-release.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-platform-build.md`
- Modify: `docs/architecture/rehla-plan-contract.json`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`
- Test: `tests/documentation/test_plan_quality.py`

**Interfaces:**
- Consumes: completed plans 01–09 and all acceptance evidence.
- Produces: deployable artifact proof, recovery proof, security/performance/accessibility results, and closed R01–R65 register.

- [ ] **Step 1: Order the four release workstreams**

Run localization/RTL/accessibility E2E first, health/workers/scheduler/observability second, artifact/migration/backup/restore third, and security/performance/final R01–R65 gate last. State why each gate consumes the previous one.

- [ ] **Step 2: Make environment and recovery evidence exact**

Require immutable artifact hash, supported PHP/PostgreSQL/Node versions, host-native process definitions, expand/backfill/contract migrations, encrypted database/blob backup, consistency manifest, restore rehearsal, RPO 15 minutes, RTO 4 hours, and reconciliation/document retrieval after restore.

- [ ] **Step 3: Make security and performance gates exact**

Require dependency/license audit, secret scan, authorization matrix, rate-limit tests, file-upload abuse tests, mass-assignment guard, session/token isolation, CSP/HSTS/CORS, PostgreSQL concurrency load, page/API budgets, queue/outbox metrics, and zero unresolved critical/high findings.

- [ ] **Step 4: Define final clean-room execution**

The final task checks out the committed artifact into a fresh directory, installs from lockfiles, builds assets, migrates an empty PostgreSQL testing database, runs all package/root/browser tests, verifies OpenAPI and architecture, runs restore proof, and writes exact evidence paths into R01–R65.

- [ ] **Step 5: Verify sequencing and coverage**

Run: `python3 -m scripts.docs_checks.run --group plans`

Expected: ten ordered plans, final plan depends on 09, and every requirement has one owner plus final evidence.

- [ ] **Step 6: Commit**

```bash
git add docs/superpowers/plans/2026-09-11-rehla-10-operations-security-release.md docs/superpowers/plans/2026-09-11-rehla-platform-build.md docs/architecture/rehla-plan-contract.json docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv
git commit -m "docs: complete operations security and release plan"
```

### Task 9: Create and Validate Repository-Local Agent Skills

**Files:**
- Create: `.agents/README.md`
- Create: `.agents/skills/rehla-implementation-gate/SKILL.md`
- Create: `.agents/skills/rehla-laravel-package-development/SKILL.md`
- Create: `.agents/skills/rehla-localization/SKILL.md`
- Create: `.agents/skills/rehla-api-contracts/SKILL.md`
- Create: `.agents/skills/rehla-filament-admin/SKILL.md`
- Create: `.agents/skills/rehla-postgres-integrity/SKILL.md`
- Create: `.agents/skills/rehla-security-and-privacy/SKILL.md`
- Create: `.agents/skills/rehla-testing-and-verification/SKILL.md`
- Create: `.agents/skills/rehla-release-operations/SKILL.md`
- Create: `scripts/docs_checks/agent_skills.py`
- Create: `tests/documentation/test_agent_skills.py`
- Modify: `scripts/docs_checks/run.py`

**Interfaces:**
- Produces: nine discoverable skills and validation group `skills`.
- Consumes: plan contract and repository authority paths.

- [ ] **Step 1: Write failing skill-structure tests**

Test exact skill names, YAML frontmatter fields `name` and discriminating `description`, folder/name equality, no unfinished scaffold markers, valid local references, implementation-gate links to the eight specialists, and README index equality.

- [ ] **Step 2: Run RED**

Run: `python3 -m unittest tests.documentation.test_agent_skills -v`

Expected: FAIL because `.agents/skills` is empty.

- [ ] **Step 3: Write the implementation gate and index**

`rehla-implementation-gate` requires the agent to resolve plan/task, read the spec and three maps, confirm prerequisites, preserve scope, select specialist skills, record RED evidence, run focused/expanded/fresh checks, update acceptance evidence, and stop on an unresolved authority conflict. `.agents/README.md` lists trigger examples and requires this gate first.

- [ ] **Step 4: Write the eight specialist skills**

Each `SKILL.md` contains: when to use, authorities, mandatory rules, workflow, verification, stop conditions, and handoff evidence. Localization is mandatory for every package task. Testing is mandatory whenever code or executable plans change. Other skills activate only on their described surface.

- [ ] **Step 5: Validate with both validators**

Run the system validator for the exact skill directories:

```bash
for skill in rehla-implementation-gate rehla-laravel-package-development rehla-localization rehla-api-contracts rehla-filament-admin rehla-postgres-integrity rehla-security-and-privacy rehla-testing-and-verification rehla-release-operations; do
  python3 /home/ubuntu/.codex/skills/.system/skill-creator/scripts/quick_validate.py ".agents/skills/$skill"
done
```

Run repository validation:

`python3 -m unittest tests.documentation.test_agent_skills -v && python3 -m scripts.docs_checks.run --group skills`

Expected: 9 valid skills, 8 specialist links, 0 broken references, 0 unfinished scaffold markers.

- [ ] **Step 6: Commit**

```bash
git add .agents scripts/docs_checks/agent_skills.py scripts/docs_checks/run.py tests/documentation/test_agent_skills.py
git commit -m "docs(agents): add strict Rehla implementation skills"
```

### Task 10: Produce Full Plan Coverage and Final Audit Evidence

**Files:**
- Create: `docs/superpowers/plans/2026-09-12-rehla-plan-manifest.csv`
- Create: `docs/reviews/2026-09-12-rehla-build-plans-and-agent-skills-audit.md`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv`
- Modify: `scripts/docs_checks/plan_quality.py`
- Modify: `scripts/docs_checks/inventory.py`
- Test: `tests/documentation/test_plan_quality.py`
- Test: `tests/documentation/test_inventory.py`

**Interfaces:**
- Consumes: final `docs/superpowers/plans/`, `.agents/skills/`, specs coverage, architecture maps.
- Produces: complete manifest, gap-resolution matrix, and one fresh release command.

- [ ] **Step 1: Generate the plan manifest**

One CSV row per plan file with: path, kind (`master|implementation|maintenance|redirect`), execution order, task count, package owners, prerequisite plan IDs, requirement IDs, status, and verification command. Dynamic inventory must equal committed rows exactly.

- [ ] **Step 2: Reconcile R01–R65 plan coverage**

Require each row to use an existing exact plan path and task heading, one primary owner, a test/evidence kind, and final verification in plan 10. Reject generic references such as only a filename with no task.

- [ ] **Step 3: Write the audit report with exhaustive matrices**

Include: total files and lines inspected; plan-by-plan status; 19-package owner matrix; translation compliance matrix; Web/API/Admin surface matrix; cross-cutting security/data/operations matrix; R01–R65 counts; every corrected gap; deferred product exclusions; commands and outputs; git commit list; residual limitations.

- [ ] **Step 4: Run narrow and broad validation**

Run:

```bash
python3 -m unittest discover -s tests/documentation -v
python3 -m scripts.docs_checks.run --group package
python3 -m scripts.docs_checks.run --group api
python3 -m scripts.docs_checks.run --group semantics
python3 -m scripts.docs_checks.run --group inventory
python3 -m scripts.docs_checks.run --group plans
python3 -m scripts.docs_checks.run --group skills
python3 -m scripts.docs_checks.run --group all
git diff --check
```

Expected: all groups PASS, `10 plans`, `19 package owners`, `65/65 requirements`, `28/28 API operations`, `9 skills`, and zero broken links.

- [ ] **Step 5: Inspect scope and commit**

Confirm no Laravel application source or deferred domain package was scaffolded. Confirm old interface plan is redirect-only and no duplicate executable task remains.

```bash
git add docs scripts/docs_checks tests/documentation .agents
git commit -m "docs: prove complete Rehla build plan coverage"
```

- [ ] **Step 6: Confirm final repository state**

Run: `git status --short && git log --oneline -15`

Expected: clean working tree and a reviewable sequence of plan-governance commits. Do not push.
