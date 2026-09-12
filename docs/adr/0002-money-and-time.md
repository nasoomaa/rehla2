# ADR 0002: Money and Time

## Status

Accepted

## Context

Wallet debits, bank top-ups, order snapshots, and reports require exact arithmetic and reproducible time boundaries.

## Decision

- Phase 1 supports SDG only, with scale 100.
- Store and calculate money as signed 64-bit integer minor units. Binary `float` and `double` values are forbidden for monetary state, parameters, DTOs, and calculations.
- Core supplies an immutable `Money` value object. Domain constraints decide where negative values are forbidden.
- The Wallet ledger is append-only and is the source of wallet balance changes. This decision does not claim a general accounting double-entry system.
- Persist timestamps in UTC. Core supplies a `Clock` abstraction returning `CarbonImmutable` so tests can control time.
- Apply `Africa/Khartoum` to customer display and reporting cohort boundaries, with an explicit UTC `as_of` cutoff for reproducible reports.

## Consequences

Money tests cover overflow, currency mismatch, subtraction below zero, and absence of floating-point APIs. Time-dependent behavior receives a Clock and never reads mutable wall time directly inside domain rules.
