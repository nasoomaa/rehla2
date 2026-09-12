---
name: rehla-release-operations
description: Use when changing Rehla health checks, workers, scheduler, observability, deployment artifacts, migrations, backup, restore, performance, or release gates.
---

# Rehla Release Operations

## When to use

Use for plan 10 operational work orany change to production processes, readiness, alerts, artifacts, migration rollout, backup/restore, performance, orrelease evidence.

## Authorities

Read [release plan](../../../docs/superpowers/plans/2026-09-11-rehla-10-operations-security-release.md), operational reliability spec, architecture, plan contract, and acceptance register.

## Rules

- Preserve the gate order: localization/accessibility E2E; health/workers/scheduler/observability; artifact/migration/backup/restore; security/performance/final R01–R65.
- Use host-native process definitions for web, queue, and scheduler with limited users, graceful stop, retry/backoff, and lease recovery.
- Build an immutable artifact from one clean commit and lockfiles, record SHA-256, runtime versions, and file manifest, and never build dependencies on the production host.
- Use expand/backfill/contract migrations with resumable backfill and count/checksum verification. Avoid destructive down migrations after production data.
- Encrypt PostgreSQL and private-blob backups, bind them with a signed consistency manifest, and rehearse restore to RPO 15 minutes and RTO 4 hours.
- Release only after secret/license/dependency audits, security matrix, upload abuse, CSP/HSTS/CORS, PostgreSQL load/concurrency, query/latency and queue/outbox budgets, and zero unresolved critical/high findings.

## Workflow

Require evidence from the preceding gate, add a failing operational proof, implement scripts/docs/config, rehearse failure/restart/restore, then run a fresh-directory install against empty PostgreSQL and close acceptance rows.

## Verification

Record liveness/readiness failure modes, worker and scheduler overlap/restart, alert injection, artifact checksum, upgrade and restore reports, all 19 package/root/browser suites, OpenAPI equality, security/performance scans, and acceptance verifier.

## Stop conditions

Stop release when any earlier plan is incomplete, artifact provenance differs, a migration is not upgrade-safe, DB/blob backup points differ, RPO/RTO fails, an alert is unproved, a critical/high finding remains, orany in-scope row lacks evidence.

## Handoff evidence

Provide commit/artifact SHA, runtimes, process definitions, migration/backup/restore reports, RPO/RTO, security/license/secret findings, load budgets, clean-room commands/results, acceptance coverage, and launch decision owner.
