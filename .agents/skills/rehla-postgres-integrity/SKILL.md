---
name: rehla-postgres-integrity
description: Use when changing Rehla migrations, constraints, transactions, row locks, idempotency, ledger/history immutability, reconciliation, or concurrency behavior.
---

# Rehla PostgreSQL Integrity

## When to use

Use for database schema, transactional domain Actions, financial changes, immutable history, Outbox claims, cleanup fencing, orconcurrency tests.

## Authorities

Read [table ownership](../../../docs/architecture/table-ownership.json), contract map transaction rules, selected domain spec, plan task, and architecture PostgreSQL gates.

## Rules

- Integration, constraint, money, and concurrency evidence uses real PostgreSQL with a database name ending `_testing`; SQLite cannot prove acceptance.
- Only the table owner creates migrations and writes rows. Cross-package writes call owner commands on the caller’s transaction/connection when specified.
- Money uses integer SDG minor units. Ledger, Audit, Orders/snapshots, published FormVersions/policies, and status history use database-level immutability proof.
- Put uniqueness/check constraints and lock order in PostgreSQL. Test affected-row outcomes and translate expected conflicts to stable domain errors.
- Keep external I/O after commit via Outbox. Fence leases and cleanup claims with fresh tokens; stale workers cannot acknowledge orpurge.
- Use forward-only corrections after real immutable data. Never hide an invariant in Eloquent-only validation.

## Workflow

Identify transaction owner and tables, write SQL-level RED tests, implement migration/constraint/lock/idempotency, add two-connection race and failure injection, then prove rollback and reconciliation.

## Verification

Run focused PostgreSQL tests outside runner-wrapped transactions, concurrent processes/connections, direct SQL mutation tests, owner/consumer integration, and architecture/table checks. Record PostgreSQL version and safe database target.

## Stop conditions

Stop if the target database is not demonstrably testing, a table owner is ambiguous, a transaction spans network I/O, lock ordering is undefined, orthe only proposed proof uses SQLite orone sequential connection.

## Handoff evidence

Provide table owner/migration, constraints/indexes/triggers, transaction owner and lock order, race/failure-injection outcomes, rollback/reconciliation counts, database name/version, and recovery migration.
