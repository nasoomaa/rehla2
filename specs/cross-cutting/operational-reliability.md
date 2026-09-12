# Cross-Cutting Specification: Operational Reliability

## 1. Transaction boundaries

Wallet movements, top-up decisions, checkout, execution transitions, audit entries, in-app notifications, and required outbox records commit atomically with their owning business operation. External network calls never occur inside those database transactions. Retries must use the idempotency or deduplication rule defined by the owning contract.

## 2. Recovery objectives

The Phase 1 targets are RPO 15 minutes and RTO 4 hours. A recovery exercise is successful only when it restores a mutually consistent database and private/public blobs, verifies referential checksums, and proves that authorized document retrieval and financial reconciliation work after restore.

## 3. Telemetry and alerts

- trace_id: unique identifier for one HTTP request or one queued-job attempt; changes on retry.
- correlation_id: stable identifier for one logical business operation across retries, audit, notifications, and outbox.

Structured telemetry records both identifiers where applicable, plus checkout latency/conflicts, top-up review age, wallet invariant failures, execution age by state, document scan failures, outbox available/locked/dead-letter depth, delivery attempts, and authorization failures. Alerts must cover wallet inconsistency, repeated review conflicts, expired outbox leases, dead letters, restore failure, and sustained performance-budget breaches without exposing sensitive payloads.

## 4. Performance and compatibility

- Public catalog reads target a 200 ms server response under the documented standard load profile.
- Checkout transaction work targets 500 ms under the documented standard database profile.
- Deployments preserve backward compatibility for active API versions, stored form schemas, captured fulfillment policies, and queued outbox payload versions.
- Database changes use expand/migrate/contract sequencing where concurrent old and new application versions may run.

These values are service objectives measured by an agreed load profile, not permission to skip correctness or security checks.

## 5. Acceptance

- Failure injection at each checkout write proves complete rollback.
- A restore drill demonstrates the RPO/RTO targets and blob/database consistency.
- An expired outbox lease can be reclaimed, while the former worker cannot acknowledge the reclaimed message.
- Monitoring detects a dead-letter record and gives staff an audited replay path.
