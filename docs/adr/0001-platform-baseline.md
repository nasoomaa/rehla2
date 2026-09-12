# ADR 0001: Platform Baseline

## Status

Accepted

## Context

Rehla Phase 1 needs one deployable application with explicit domain ownership and deterministic enforcement. The product does not justify distributed services, duplicated databases, or infrastructure that is absent from the approved requirements.

## Decision

- Use Laravel 13 on PHP 8.5 as one modular monolith.
- Keep the 19 local Composer packages listed by `docs/architecture/rehla-package-map.json` under `packages/Rehla/<Package>`.
- Use one PostgreSQL 18 database. Integration, constraint, money, and concurrency tests use a database whose name ends in `_testing`; SQLite is not valid evidence for those tests.
- Keep mutable models, migrations, and table writes inside the owning package. Packages communicate through the surfaces declared by `rehla-package-contract-map.json`.
- Keep Web, Api, and Admin as presentation packages without direct business-table writes.
- Deliver asynchronous external effects through the PostgreSQL transactional Outbox after commit.
- Target an RPO of 15 minutes and an RTO of 4 hours for a mutually consistent database and blob restore.

## Consequences

Composer manifests and token-based architecture tests enforce package direction. A new service, database, package, or cross-package surface requires an approved contract change before code depends on it.
