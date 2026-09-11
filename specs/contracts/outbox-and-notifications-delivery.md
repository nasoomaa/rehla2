# Contract: Transactional Outbox and Event Delivery

## 1. Responsibility

This contract defines the transactional boundary, queue semantics, persistence schema, worker locking model, and delivery failure protocols for asynchronous event distribution across the Rehla platform. It guarantees that domain events are written atomically with business state changes and dispatched at-least-once to communication channels without compromising transaction response times.

---

## 2. Producer and Consumer

- **Producers**: All core domain packages creating domain events (`Identity`, `TopUps`, `Purchasing`, `Orders`, `Fulfillment`).
- **Consumers**: Notifications Dispatcher Worker (`packages/Rehla/Notifications`), External Messaging Adapters (`packages/Rehla/Integrations`).

---

## 3. Outbox Table Schema Contract

All domain producers insert records into the `outbox_messages` table:

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `BIGSERIAL` | Primary Key | Monotonically increasing sequence identifier. |
| `event_name` | `VARCHAR(100)` | NOT NULL | Canonical event name (e.g. `order.submitted`). |
| `payload_version` | `INTEGER` | NOT NULL, Default `1` | Schema version of the JSON payload. |
| `payload` | `JSONB` | NOT NULL | Complete event attributes and recipient details. |
| `deduplication_key`| `VARCHAR(150)` | NOT NULL, UNIQUE | Idempotency hash preventing double delivery. |
| `status` | `VARCHAR(20)` | NOT NULL, Default `available` | `available`, `locked`, `delivered`, `dead_letter`. |
| `available_at` | `TIMESTAMPTZ` | NOT NULL | Scheduled or retry execution threshold. |
| `locked_at` | `TIMESTAMPTZ` | NULL | Timestamp when worker acquired row lock. |
| `locked_by` | `VARCHAR(100)` | NULL | Unique identifier of claiming worker instance. |
| `attempts` | `SMALLINT` | NOT NULL, Default `0` | Number of dispatch attempts executed. |
| `last_error` | `TEXT` | NULL | Captured error message or stack trace if failed. |
| `delivered_at` | `TIMESTAMPTZ` | NULL | UTC timestamp upon confirmed delivery. |
| `created_at` | `TIMESTAMPTZ` | NOT NULL | Creation timestamp (inside enclosing transaction). |

---

## 4. Producer Contract (`AppendOutboxMessage`)

Producers MUST call the outbox append method within their primary database transaction:

```php
interface OutboxContract
{
    public function append(
        string $eventName,
        string $deduplicationKey,
        array $payload,
        int $payloadVersion = 1,
        ?\DateTimeInterface $availableAt = null
    ): OutboxMessageId;
}
```

### Invariants:
1. Producer must supply a deterministic `deduplication_key` (e.g. `topup_approved:{top_up_id}:{timestamp}`).
2. Producer must NOT invoke any HTTP client or queue socket within the transaction.
3. If the transaction rolls back, the outbox record is rolled back automatically.

---

## 5. Consumer Worker Lock and Polling Protocol

Background workers poll the outbox using PostgreSQL pessimistic row locking:

### Polling Query:
```sql
SELECT id, event_name, payload, attempts, deduplication_key
FROM outbox_messages
WHERE status = 'available'
  AND available_at <= NOW()
ORDER BY id ASC
LIMIT 50
FOR UPDATE SKIP LOCKED;
```

### Claiming Action:
```sql
UPDATE outbox_messages
SET status = 'locked',
    locked_at = NOW(),
    locked_by = :worker_id,
    attempts = attempts + 1
WHERE id IN (:claimed_ids);
```

---

## 6. Retry, Backoff, and Dead-Letter Semantics

1. **Successful Delivery**:
   Upon positive receipt or channel acceptance:
   ```sql
   UPDATE outbox_messages
   SET status = 'delivered',
       delivered_at = NOW(),
       locked_by = NULL
   WHERE id = :message_id;
   ```
2. **Transient Delivery Failure**:
   If delivery fails and `attempts < 5`:
   - Calculates exponential backoff: `delay_seconds = (2 ^ attempts) * 15`.
   - Backoff schedule:
     - Attempt 1: 30 seconds.
     - Attempt 2: 60 seconds.
     - Attempt 3: 120 seconds.
     - Attempt 4: 240 seconds.
   ```sql
   UPDATE outbox_messages
   SET status = 'available',
       available_at = NOW() + INTERVAL ':delay_seconds seconds',
       locked_at = NULL,
       locked_by = NULL,
       last_error = :error_message
   WHERE id = :message_id;
   ```
3. **Exhausted Retries (Dead-Letter)**:
   If delivery fails on Attempt 5 (`attempts >= 5`):
   ```sql
   UPDATE outbox_messages
   SET status = 'dead_letter',
       locked_at = NULL,
       locked_by = NULL,
       last_error = :error_message
   WHERE id = :message_id;
   ```
   An alert is dispatched to platform monitoring, and an entry is written to `audit_entries`.

---

## 7. Crash Recovery (Lease Lock Expiry)

If an outbox worker terminates abruptly (SIGKILL, container preemption) while holding locked messages:
- The lease threshold is defined as **60 seconds**.
- A scheduled watchdog task runs every 60 seconds:
  ```sql
  UPDATE outbox_messages
  SET status = 'available',
      locked_at = NULL,
      locked_by = NULL
  WHERE status = 'locked'
    AND locked_at < NOW() - INTERVAL '60 seconds';
  ```
- This ensures orphaned messages are reclaimed automatically without manual administrative intervention.
