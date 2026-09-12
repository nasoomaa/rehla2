---
name: rehla-laravel-package-development
description: Use when creating or changing a local Rehla Laravel Composer package, provider, dependency, public contract, or package resource.
---

# Rehla Laravel Package Development

## When to use

Use for files under `packages/Rehla/<Package>` or any change to package dependency, public surface, provider registration, migration placement, or package generator.

## Authorities

Read [package map](../../../docs/architecture/rehla-package-map.json), [contract map](../../../docs/architecture/rehla-package-contract-map.json), [table ownership](../../../docs/architecture/table-ownership.json), [plan contract](../../../docs/architecture/rehla-plan-contract.json), and the selected task.

## Rules

- The package root contains only `composer.json`, `README.md`, `src/`, and `tests/`. Put runtime `config/database/resources/routes/openapi` under `src/`.
- Put the provider at `src/Providers/<Package>ServiceProvider.php`; load each runtime resource from its real `src/` path.
- Composer requires and PHP imports must match the package map. Communicate only through contract-map Actions, Queries, Contracts, and immutable DTOs.
- Never expose mutable Eloquent Models across packages. Only the table owner writes its tables; consumers call owner commands.
- Every package includes the bilingual files and parity test required by [localization](../rehla-localization/SKILL.md), even when messages are empty.
- Document public contracts, transaction participation, authorization, error codes, owned tables, tests, and recovery in README.

## Workflow

Resolve owner plan/task, add a failing discovery/boundary test, create the minimum package files, wire the provider, generate exact Composer dependencies from maps, implement the public surface, then test consumers and boundary bypasses.

## Verification

Run the package tests, consumer tests, root Architecture suite, `composer dump-autoload`, package discovery, static analysis, formatter, and `python3 -m scripts.docs_checks.run --group package`.

## Stop conditions

Stop when the requested dependency is absent from the package map, a needed surface is absent from the contract map, a table has another owner, or the change would create a cycle. Update and review the authoritative maps first.

## Handoff evidence

Report package owner task, Composer edge changes, public surfaces, tables, provider/resource loading, translation proof, focused/consumer/architecture results, and recovery notes.
