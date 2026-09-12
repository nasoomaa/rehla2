---
name: rehla-implementation-gate
description: Use when implementing or changing any Rehla code, executable plan task, package contract, or acceptance evidence.
---

# Rehla Implementation Gate

## When to use

Use this first for every Rehla implementation task or executable-plan change. Resolve one legal plan and task before editing. Documentation-only prose outside executable plans may skip it when it cannot affect product, architecture, or evidence.

## Authorities

Read the relevant sources in this order:

1. [Product concept](../../../docs/REHLA-PROJECT-CONCEPT-AND-USER-JOURNEY.md).
2. Relevant files under `../../../specs/` and their governance.
3. Package, contract, table, and plan maps under `../../../docs/architecture/`.
4. [Laravel architecture](../../../docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md).
5. The selected file under `../../../docs/superpowers/plans/` and its exact task.

## Rules

- Confirm every prerequisite and open/closing cross-plan gate. Mark an earlier feature partial when its closing integration is scheduled later.
- Keep deferred domains deferred. Do not scaffold Cart, Inventory, Shipping, MultiCurrency, Refunds, drafts, orother excluded scope.
- Preserve existing user changes and authorization boundaries. Do not push, deploy, publish, ormutate external systems without authorization.
- Load [package development](../rehla-laravel-package-development/SKILL.md) for package work and [localization](../rehla-localization/SKILL.md) for every package task.
- Load [API contracts](../rehla-api-contracts/SKILL.md), [Filament Admin](../rehla-filament-admin/SKILL.md), [PostgreSQL integrity](../rehla-postgres-integrity/SKILL.md), [security/privacy](../rehla-security-and-privacy/SKILL.md), and [release operations](../rehla-release-operations/SKILL.md) only when their surface is affected.
- Load [testing and verification](../rehla-testing-and-verification/SKILL.md) whenever code or an executable plan changes.

## Workflow

1. Record plan ID, task heading, acceptance IDs, files, contracts, tables, actor/ability, transaction owner, external effects, recovery, and required specialist skills.
2. Check `git status --short`; distinguish pre-existing changes and constrain the diff.
3. Update acceptance rows to `red`, add the smallest test that fails for the intended reason, and record the RED command/output.
4. Implement only the selected task. Keep mutable Models and table writes inside the owner package.
5. Run focused, dependent, architecture, and fresh expanded verification; format before the final rerun.
6. Update evidence to `verified` only with command, test name/result, artifact path, and commit SHA.

## Verification

Run the selected plan’s commands plus `python3 -m scripts.docs_checks.run --group all` when documents orplans change. Use PostgreSQL for integration, money, constraint, and concurrency proof.

## Stop conditions

Stop before implementation when authorities conflict, prerequisites are unproved, a requested write has no owner contract, the database target is unsafe, or success would require inventing deferred product policy. Report the exact source and unresolved decision.

## Handoff evidence

Provide plan/task, acceptance IDs, changed files, RED evidence, focused and expanded results, architecture/security findings, recovery behavior, residual risk, and commit SHA. State `partial` when a declared cross-plan gate remains open.
