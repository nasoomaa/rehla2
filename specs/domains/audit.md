# Domain: Audit and Compliance

## 1. Purpose

The Audit and Compliance domain provides a permanent, tamper-evident historical log of all sensitive operational, financial, and administrative decisions made on the platform. It establishes accountability by recording who performed an action, what was altered, when the event occurred, why the decision was taken, and the network context of the actor.

---

## 2. Actors

- **Security Auditor / Platform Administrator**: Queries and inspects audit logs using the `audit.view` ability.
- **System**: Appends immutable audit entries during sensitive operations across all business domains.

---

## 3. Concepts

- **Audit Entry**: An immutable record tracking an operational decision. Contains:
  - Entry ID (sequential 64-bit integer or UUID).
  - Actor Type (`staff`, `customer`, `system`).
  - Actor ID (ID of the staff member or customer).
  - Action Name (e.g. `topup.approved`, `topup.rejected`, `execution.status_changed`, `execution.cancelled`, `service.price_updated`, `role.assigned`).
  - Target Entity Type (e.g. `TopUpRequest`, `ServiceExecution`, `Service`, `UserAccount`).
  - Target Entity ID.
  - Old State Snapshot (JSON representation of values before change).
  - New State Snapshot (JSON representation of values after change).
  - Business Rationale / Reason (text description or staff explanation).
  - Correlation ID (UUID linking related sub-actions across a single request).
  - Client IP Address and User-Agent.
  - Timestamp (UTC).

---

## 4. Invariants

1. **Strict Immutability**: Audit entries are permanently append-only. The database schema, triggers, and application role permissions must prevent `UPDATE` and `DELETE` statements on the audit table under all conditions.
2. **Mandatory Sensitive Action Coverage**: The following actions MUST generate an audit entry:
   - Approving or rejecting a top-up request.
   - Transitioning a service execution status.
   - Cancelling a service execution.
   - Updating a service price.
   - Publishing a new application form version.
   - Deactivating a service or platform bank account.
   - Suspending or reactivating a customer account.
   - Assigning roles or abilities to staff members.
3. **Data Sanitization and Secret Protection**: Passwords, raw credentials, and session tokens must be scrubbed before persisting state snapshots.
4. **Access Control**: Viewing audit logs requires explicit possession of the `audit.view` ability and an active verified TOTP MFA session.

---

## 5. State Model

Audit entries are immutable chronological facts:
```text
[Operation Occurs] ──► Audit Entry Appended (Permanent & Immutable Forever)
```
- No transitions, no archiving deletions, no modifications.

---

## 6. Commands and Actions

### 6.1 AppendAuditEntry (Internal System Contract)
- **Preconditions**: Called within the database transaction of the sensitive business operation.
- **Inputs**: Actor Type, Actor ID, Action Name, Entity Type, Entity ID, Old State (JSON), New State (JSON), Reason (text), Correlation ID, IP Address, User-Agent.
- **Expected Outcome**: New audit row inserted.
- **Failure Behavior**: If the audit insert fails, the enclosing business transaction must fail and roll back.

### 6.2 QueryAuditLog
- **Preconditions**: Staff member has `audit.view` ability and verified MFA.
- **Inputs**: Filter parameters (date range, actor ID, action name, entity type, entity ID), pagination parameters.
- **Expected Outcome**: Filtered, paginated audit records.
- **Observable Behavior**: Administrative audit table displays chronological event trail.
- **Authorization**: Denied by default; requires `audit.view`. Unauthorized staff receive HTTP 403 Forbidden.

---

## 7. Business Rules

1. **Trace Attempt**: trace_id: unique identifier for one HTTP request or one queued-job attempt; changes on retry. It belongs in telemetry and bounded diagnostics and is never copied from an untrusted client header.
2. **Business Correlation**: correlation_id: stable identifier for one logical business operation across retries, audit, notifications, and outbox. HTTP accepts a valid `X-Correlation-ID` or creates one; queued jobs restore it from their envelope. Every related audit entry stores it.
3. **Actor Accountability**: When an administrative action is triggered, the audit entry captures the specific staff member's ID (`actor_id`), completely prohibiting generic or anonymous administrative writes.

---

## 8. Edge Cases

- **Direct Database Modification Attempts**: If an operator with raw database access attempts `UPDATE audit_entries SET ...`, a database-level trigger raises an exception: `"Audit entries are immutable and cannot be updated."`
- **Rapid Successive Transitions**: If an execution transitions twice in 30 seconds, two independent audit entries are appended with exact microsecond timestamps.

---

## 9. Failure Behavior

- **Unauthorized Audit View**: HTTP 403 Forbidden with code `audit.access_denied`.
- **Database Trigger Violation**: Database aborts query with SQLSTATE error code.

---

## 10. Cross-Domain Interactions

- **All Domains**: Identity, Catalog, Forms, Top-Ups, Wallet, Orders, and Fulfillment invoke `AppendAuditEntry` to record critical milestones.
