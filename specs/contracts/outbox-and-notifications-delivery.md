# Contract: Transactional Outbox and Event Delivery

## 1. Responsibility

This contract defines atomic event capture and at-least-once dispatch to asynchronous external channels. In-app notifications are business records inserted in the same transaction as the event; they do not wait for the dispatcher.

## 2. Producer and consumer

- Producers are core domain commands that create a customer-visible event.
- The Notifications dispatcher claims outbox rows; Integrations adapters deliver versioned payloads.
- Producers perform no external network I/O in their database transaction.

## 3. Persistence contract

Each `outbox_messages` record contains:

| Field | Contract |
|---|---|
| `id` | Opaque primary identifier. |
| `event_name`, `payload_version`, `payload` | Versioned event envelope. |
| `deduplication_key` | Deterministic unique value such as `topup.approved:{top_up_id}:v1`; never includes a retry timestamp. |
| `status` | `available`, `locked`, `delivered`, or `dead_letter`. |
| `available_at` | UTC eligibility time. |
| `locked_at`, `locked_by` | Current claim metadata. |
| `lock_token` | Fresh unpredictable value generated for every claim. |
| `lease_expires_at` | UTC time after which another worker may replace the claim. |
| `attempts` | Claims performed; initial maximum is 5. |
| `last_error`, `last_trace_id` | Bounded sanitized diagnostic data; no stack trace or sensitive payload. |
| `delivered_at`, `created_at` | UTC timestamps. |

The producer inserts the in-app notification and external-channel outbox row inside the owning business transaction. A rollback removes both.

## 4. Claim protocol

In one short database transaction, the dispatcher selects eligible `available` rows and `locked` rows whose `lease_expires_at <= NOW()`, ordered by ID, using `FOR UPDATE SKIP LOCKED`. For each row it writes:

```text
status = locked
locked_by = current worker ID
lock_token = fresh random token
locked_at = NOW()
lease_expires_at = NOW() + configured lease duration
attempts = attempts + 1
```

The worker retains the returned `(id, worker_id, lock_token)` capability. Network delivery occurs after the claim transaction commits.

## 5. Fenced completion and failure

A success update is accepted only when all of these still match:

```sql
WHERE id = :message_id
  AND status = 'locked'
  AND locked_by = :worker_id
  AND lock_token = :lock_token
```

It sets `delivered`, `delivered_at`, and clears claim fields. A zero-row update means the worker lost its lease; it discards the result and must not overwrite the current owner's state.

A transient failure uses the same fence, sets `available_at` to an application-calculated UTC timestamp, records bounded safe diagnostic data, and clears claim fields. Attempts 1–4 use initial delays 30, 60, 120, and 240 seconds. Failure on attempt 5 moves the row to `dead_letter`, raises a monitoring alert, and appends an audit entry.

An operator with `notifications.replay` may replay a dead letter. Replay creates or resets a dispatch attempt under a documented, audited procedure and does not alter the original business record.

## 6. Delivery semantics

At-least-once delivery can produce a duplicate when the provider accepted a request but its response was lost. Adapters pass the deterministic deduplication key to providers that support idempotency. Consumers must tolerate repeated versioned events. The corpus does not claim exactly-once external delivery.

## 7. Acceptance

- Two workers cannot hold the same active lease.
- After lease expiry, a new worker/token can reclaim the row and the old token updates zero rows.
- A crash after provider acceptance may cause a retry without losing the event.
- Attempt 5 produces a dead letter, sanitized diagnostics, alert, and audit entry.
- In-app notification visibility is atomic with its business event even when all external channels are unavailable.
