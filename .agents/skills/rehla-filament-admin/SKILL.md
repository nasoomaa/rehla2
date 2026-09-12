---
name: rehla-filament-admin
description: Use when building or changing Rehla Filament panels, resources, widgets, staff authentication, administrative actions, sensitive fields, or exports.
---

# Rehla Filament Admin

## When to use

Use for files in the Admin package orany staff-facing control-plane behavior.

## Authorities

Read [Admin contract](../../../specs/contracts/admin-operations-contract.md), [Admin plan](../../../docs/superpowers/plans/2026-09-11-rehla-09-admin-control-panel.md), Identity abilities, and relevant domain contracts.

## Rules

- Use a distinct staff guard/cookie/session. Staff gets zero access without an explicit ability; sensitive abilities require recent TOTP reauthentication within four hours.
- Build lists/details from immutable Query/ReadModel DTOs. Every mutation calls a named owner-package command.
- Reject cross-package Models, `DB::`, builder writes, raw connections, relationship mutation, model-bound forms, and business transitions in Filament closures.
- Never show Edit/Delete for Ledger, Orders/snapshots, published FormVersions/policies, orAudit.
- Every page/resource/widget/action/field/navigation item has a capability. Every sensitive field has an allowlist, mask, reveal audit, export rule, and MFA requirement where declared.
- Private documents use Documents authorization and session-bound short-lived delivery; never render storage keys orpermanent public URLs.

## Workflow

Locate the exact row in the 14-area Admin matrix, add denial tests for staff-none and limited roles, implement the read model and named action, add localized UI, then test full/limited staff journeys and concurrency where money changes.

## Verification

Run Admin feature/architecture suites, owner-package integration tests, sensitive-field/export tests, and EN/AR/RTL browser journeys. Confirm the command-only token guard and capability matrix pass.

## Stop conditions

Stop when no owner command/query exists, the requested action edits an immutable record, an ability orfield policy is undeclared, MFA cannot be enforced, orFilament requires a mutable cross-package Model binding.

## Handoff evidence

Report matrix area, view/mutation/sensitive abilities, Query and Command surfaces, denied actors, field/export rules, MFA evidence, owner integration result, and browser artifacts.
