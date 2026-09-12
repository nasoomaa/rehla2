# Rehla Plan Governance and Agent Skills Design

**Date:** 2026-09-12  
**Status:** Approved in conversation  
**Scope:** `docs/superpowers/plans/`, `.agents/`, and documentation validation tooling

## 1. Purpose

This design turns the Rehla build plans into a complete, ordered, machine-verifiable program and gives coding agents repository-local skills for implementing that program consistently. It addresses two recurring failure modes:

1. a package task can omit a required concern such as bilingual language resources, registration, provider loading, security, or verification; and
2. a major product surface can exist only as a task buried inside a broad plan, making the build sequence and completion state hard to see.

The resulting plans must build the complete Phase 1 product described by the Rehla product concept and specifications. A plan may not claim completion merely because a feature is mentioned; it must name the files, contracts, tests, dependencies, acceptance requirements, and completion gate.

## 2. Current Baseline

The executable program currently consists of one master plan and seven implementation plans. The seventh plan contains Web, REST API, Filament Admin, localization, operations, deployment, security, performance, and release work in one file. Admin does exist as Task 5, but its placement makes it easy to overlook and makes the final plan too broad.

Language obligations are also uneven. Catalog/Forms/Content and the final interface plan mention bilingual behavior, while most package construction tasks do not require language files or key-parity tests. There is no machine-readable registry proving that all 19 packages have one owning build task and all 65 product requirements reach an acceptance gate.

The existing package, contract, and table-ownership maps remain authoritative. This design adds plan governance; it does not replace those maps.

## 3. Chosen Approach

Use a machine-enforced program contract plus focused implementation plans and repository-local agent skills.

This is preferred over keeping one large final plan because separate Web, API, Admin, and operations plans make their prerequisites, deliverables, and gates visible. It is preferred over prose-only rules because a prose rule can drift without failing CI.

The rejected alternatives are:

- **Keep seven plans and only expand Task 5:** fewer file moves, but Admin and release work remain hidden inside an oversized plan.
- **Create one skill containing every rule:** simple discovery, but too large for reliable use and unable to distinguish package, API, Admin, database, localization, and release concerns.

## 4. Target Plan Program

The master plan will link exactly ten ordered implementation plans:

| Order | Plan | Primary outcome |
|---:|---|---|
| 01 | Foundation and Core | Laravel host, package generator, architecture guards, Core, PostgreSQL CI |
| 02 | Identity and Platform Services | Identity, Audit, Documents, Travelers, Notifications foundation |
| 03 | Catalog, Forms, and Content | Service catalog, policy versions, dynamic forms, public content |
| 04 | Wallet and Top-Ups | Wallet ledger, registration wallet adapter, bank accounts, funding review |
| 05 | Orders, Purchasing, and Fulfillment | immutable orders, atomic checkout, execution lifecycle |
| 06 | Reporting and Integrations | deterministic metrics, Outbox delivery, external adapters |
| 07 | Customer Web | public site, customer account, top-up, checkout, tracking, notifications |
| 08 | Customer REST API | 28 operations, OpenAPI, Sanctum, resources, ownership, Problem Details |
| 09 | Admin Control Panel | Filament panel, read models, capability matrix, all operational actions |
| 10 | Operations, Security, and Release | localization/RTL E2E, health, workers, deployment, restore, security, performance, R01-R65 release gate |

The documentation-alignment plan dated 2026-09-12 remains a maintenance plan and is not an implementation phase.

### 4.1 Dependency sequence

The plan program follows this mandatory sequence:

```text
01 Foundation
  -> 02 Identity/Platform
  -> 03 Catalog/Forms/Content
  -> 04 Wallet/Top-Ups
  -> 05 Orders/Purchasing/Fulfillment
  -> 06 Reporting/Integrations
  -> 07 Customer Web
  -> 08 Customer REST API
  -> 09 Admin Control Panel
  -> 10 Operations/Security/Release
```

Parallel work is allowed only where a plan explicitly names independent tasks and the merge gate reruns all impacted checks. A later plan may not provide a runtime dependency required by an earlier plan's acceptance gate. For example, Identity can define registration ports early, but complete registration acceptance occurs only after Notifications and Wallet implementations are bound and an integration test proves rollback across account, wallet, Audit, notification, and Outbox.

## 5. Machine-Readable Plan Contract

Create `docs/architecture/rehla-plan-contract.json` as the authoritative program registry. Its schema records:

- `schema_version`;
- the ten ordered implementation plan IDs and paths;
- every package, its owning plan/task, and direct prerequisite packages;
- mandatory package artifacts;
- package-specific artifacts and public contracts;
- required test categories and PostgreSQL requirements;
- R01-R65 ownership and final proof plan;
- cross-plan gates;
- explicitly deferred Phase 1 exclusions.

Every one of the 19 packages must appear exactly once as a build-owned package. Presentation packages may have later enhancement tasks, but one task remains responsible for their initial complete construction.

The registry must agree with:

- `rehla-package-map.json` for dependencies;
- `rehla-package-contract-map.json` for public cross-package surfaces;
- `table-ownership.json` for persistence ownership;
- the spec coverage manifest for R01-R65;
- the implementation-plan coverage register for plan/task ownership.

## 6. Mandatory Package Task Contract

Every task that creates a Rehla package must state and verify these artifacts:

```text
packages/Rehla/<Package>/
  composer.json
  README.md
  src/
    Providers/<Package>ServiceProvider.php
    resources/lang/en/messages.php
    resources/lang/ar/messages.php
  tests/
    Unit/ or Feature/ or Integration/
    Architecture/TranslationCompletenessTest.php
```

Other directories under `src/` are created only when the package uses them. Tests remain outside `src/`.

Each package provider must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-<package>')`. The package generator creates both language files and the translation parity test for all 19 packages, including backend-oriented packages. A package with no customer-visible messages starts with an empty returned array in each locale; introducing a key requires adding it to both files.

Every package task must include:

- responsibility and explicit exclusions;
- consumed and produced contracts;
- migrations and owned tables, if any;
- authorization and object-ownership rules;
- transaction and external-I/O boundaries;
- stable public error codes and translation keys;
- unit, feature, integration, architecture, concurrency, or browser evidence appropriate to the behavior;
- a failing RED command, focused GREEN command, expanded verification, and commit scope;
- acceptance IDs and evidence destinations;
- rollback or forward-recovery guidance.

## 7. Localization Rules

Localization is a build-time package obligation and a release-wide behavior gate.

1. Every package contains `en` and `ar` language files from creation.
2. Locale files return arrays with identical recursive key sets.
3. Customer-visible, staff-visible, validation, and public API message text uses translation keys; controllers, Actions, policies, jobs, and Filament definitions do not embed user-visible prose.
4. Stored bilingual content has explicit English/Arabic fields or a validated localized value object.
5. Publishing fails if either required locale is incomplete.
6. API `code` remains language-neutral; `title`, `message`, field errors, and safe action guidance are localized.
7. Arabic browser journeys verify RTL direction, logical reading order, focus, labels, and error association.
8. CI runs key parity, missing-key, forbidden-hardcoded-string, and bilingual-publication tests.

## 8. Plan Quality Validator

Create `scripts/docs_checks/plan_quality.py` and corresponding unit tests. Add a `plans` group to the documentation runner.

The validator must prove:

- exactly ten implementation plans exist in the master sequence;
- IDs are unique and ordered, and every linked file exists;
- all 19 packages have exactly one owning creation task;
- every package task lists provider, README, both locale files, a translation parity test, interfaces, verification, and commit step;
- provider examples load translations from the `src/resources/lang` path;
- plan dependencies are a valid topological order of the package map;
- all contract-map surfaces have an owning implementation task;
- all table-owning packages schedule their migrations before consumers need them;
- Web, REST API, Admin, and operations/release each have visible standalone plans;
- REST maintains the exact 28-operation set;
- Admin has a complete resource/action/capability matrix and no direct business writes;
- R01-R65 each map to a plan/task and final evidence target;
- no task accepts ambiguous outcomes or uses a success condition such as "one of these results";
- all relative links resolve and the two coverage registers contain no orphan, duplicate, or unknown task references.

The validator emits counts and precise file/line failures suitable for CI.

## 9. Admin Plan Completeness

The standalone Admin plan must visibly build:

- isolated staff guard, session, login, TOTP setup/challenge, reauthentication, and deny-by-default navigation;
- Overview metrics from Reporting;
- Services, fulfillment-policy versions, application forms, content, customers, travelers, wallets, bank accounts, top-up reviews, orders, executions, notifications/dead letters, roles/abilities, and Audit views;
- immutable/read-only treatment for ledger, orders/snapshots, published versions, and Audit;
- explicit command-backed mutations for every allowed action;
- capability and sensitive-field matrices;
- two-account and limited-staff authorization tests;
- document access through the authorized Documents contract;
- bilingual resources, RTL behavior, accessibility, audit, pagination, filtering, and safe export rules;
- architecture tests preventing Models, `DB::`, query-builder writes, relationship mutations, and hidden Facade writes in Admin.

The Admin plan ends with a complete staff journey from login/MFA through top-up review, execution processing, customer action, completion, audit inspection, and permission denial.

## 10. Repository-Local Agent Skills

Create the following Agent Skills under `.agents/skills/<skill-name>/SKILL.md`:

| Skill | Trigger and responsibility |
|---|---|
| `rehla-implementation-gate` | Start or resume any Rehla implementation task; resolves plan/task, prerequisites, acceptance IDs, clean scope, and required specialist skills |
| `rehla-laravel-package-development` | Create or change a package; enforces `src/` layout, provider loading, contracts, DTOs, Models, README, and Composer boundaries |
| `rehla-localization` | Add or change any user/staff-visible text, validation, content publication, locale behavior, or RTL UI |
| `rehla-api-contracts` | Change REST routes, controllers, Resources, OpenAPI, Problem Details, Sanctum, idempotency, or ownership behavior |
| `rehla-filament-admin` | Build or change Filament resources/pages/actions; enforces abilities, MFA, read models, sensitive fields, and command-only writes |
| `rehla-postgres-integrity` | Change migrations, money, transactions, locks, idempotency, append-only data, Outbox, or concurrency behavior |
| `rehla-security-and-privacy` | Change auth, authorization, documents, secrets, rate limits, audit, sensitive fields, or retention |
| `rehla-testing-and-verification` | Implement RED/GREEN, PostgreSQL integration/concurrency proof, architecture tests, browser tests, and narrow-to-broad fresh verification |
| `rehla-release-operations` | Change workers, scheduler, health, observability, deployment, migrations, backup/restore, performance, accessibility, or release evidence |

Each skill has discriminating frontmatter, concise mandatory rules, a task checklist, stop conditions, and links to repository authorities. Skills do not duplicate entire specifications; they point to the authoritative maps and plans and state the non-obvious checks an agent can otherwise miss.

Create `.agents/README.md` as the discovery index. It requires `rehla-implementation-gate` first, followed by every specialist whose trigger matches the task. It explains that direct user instructions override a skill, but an agent may not mark a task complete without the plan's evidence.

## 11. Validation of Agent Skills

Each skill is checked with the system skill validator and a repository validator that confirms:

- valid frontmatter and folder naming;
- no unfinished template placeholders;
- every referenced repository path exists;
- trigger descriptions are distinct;
- the implementation gate links all eight specialist skills;
- localization and testing are mandatory for every package creation task;
- database, API, Admin, security, and release rules point to the correct authoritative contracts.

No subagent evaluation is required for initial creation because the user requested inline work. Behavioral examples in isolated temporary directories may be used where they test a meaningful decision rather than wording.

## 12. Audit Deliverables

The implementation produces:

1. a manifest covering every file in `docs/superpowers/plans/`;
2. a plan/package/task/status matrix;
3. an R01-R65 plan coverage matrix;
4. a cross-cutting concerns matrix covering localization, API, Admin, security, transactions, documents, jobs, accessibility, operations, and release;
5. an audit report listing every discovered gap, its correction, evidence, and residual deferred scope;
6. a single fresh command that runs package, API, semantic, inventory, plan-quality, and skill validation.

The final report may say the plans are complete only when every manifest row is inspected, all validators pass, no coverage row is orphaned, and the git diff is clean of formatting errors.

## 13. Scope Boundaries

This work updates specifications, plans, validators, coverage records, and `.agents` skills. It does not scaffold the Laravel application or claim production implementation. Phase 1 exclusions already approved by the product concept remain excluded and must stay explicit in the master plan and release gate.

Existing uncommitted transaction-contract corrections are completed and committed separately before the plan-governance implementation begins, preserving reviewable commit boundaries.
