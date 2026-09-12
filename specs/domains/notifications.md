# Domain: Notifications and Outbox

## 1. Purpose

The Notifications and Outbox domain manages customer communication, atomic in-app notification records, and reliable asynchronous external-channel dispatch. It uses a Transactional Outbox for external delivery while keeping business workflows isolated from network latency and channel failure.

---

## 2. Actors

- **Customer**: Views in-app notification list, unread notification counter, and marks notifications as read.
- **System**: Atomically writes the in-app notification and any required external-channel Outbox payload during the business transaction.
- **Background Worker**: Polls the Outbox table, claims batches using pessimistic locks, dispatches messages to delivery channels, and records delivery status.

---

## 3. Concepts

- **In-App Notification**: A user-facing message visible within the customer portal and REST API. Contains:
  - Notification ID.
  - Account ID.
  - Notification Type (`topup_approved`, `topup_rejected`, `order_confirmed`, `execution_status_updated`, `action_required`, `service_completed`).
  - Title (EN/AR).
  - Body / Message (EN/AR).
  - Target Link / Reference (e.g. `/account/orders/ORD-1001`).
  - Read Status (`unread`, `read`).
  - Read Timestamp (UTC).
  - Created Timestamp (UTC).
- **Outbox Message**: A persistent queue record in PostgreSQL ensuring at-least-once delivery. Contains:
  - Message ID.
  - Event Name.
  - Payload Version.
  - Payload (JSON).
  - Deduplication Key (deterministic unique string supporting duplicate suppression).
  - Status (`available`, `locked`, `delivered`, `dead_letter`).
  - Available At (timestamp for scheduled/retry execution).
  - Locked At (timestamp when worker claimed message).
  - Locked By (worker instance identifier).
  - Lock Token (new opaque token for each claim).
  - Lease Expires At (UTC timestamp after which the claim can be replaced).
  - Attempts Count (integer, max 5).
  - Last Error (diagnostic text if delivery failed).
  - Delivered At (timestamp when delivery confirmed).

---

## 4. Invariants

1. **Atomic Notification Write**: The in-app record and every required external-channel Outbox record are inserted in the same transaction as the business state change. If no external channel is enabled, no redundant Outbox row is required.
2. **Zero External I/O Inside Business Transactions**: No external network calls (SMS APIs, email gateways, WhatsApp webhooks) may ever be executed inside a database transaction.
3. **At-Least-Once Delivery**: All outbox messages are guaranteed to be attempted at least once. If an external channel fails, the message remains in the outbox for exponential backoff retries.
4. **Deduplication Idempotency**: Every outbox message possesses a deterministic unique `deduplication_key`. Adapters should use it when supported, but at-least-once delivery still permits duplicates after an ambiguous provider response.
5. **Customer Ownership**: Customers can only view and mutate the read status of their own notifications (`account_id == notification.account_id`).
6. **Dead-Letter Containment**: After reaching the maximum retry threshold (5 attempts), a message transitions to `dead_letter` without halting worker queues.

---

## 5. State Model

### 5.1 In-App Notification State
```text
[Created] ──► Unread ──► Read
```

### 5.2 Outbox Message State
```text
[Inserted in Tx] ──► Available
                        │
                        ▼
                     Locked (Worker holds lease)
                        │
                        ├──────────────────────────┐
                        ▼                          ▼
                    Delivered                 Retry Backoff
              (Terminal Success)                   │
                                                   ▼
                                               Available
                                                   │
                                     (If attempts >= 5)
                                                   │
                                                   ▼
                                              Dead Letter
```

---

## 6. Commands and Actions

### 6.0 RecordRegistrationWelcome (Identity Port Adapter)
- **Boundary**: Notifications implements `RegistrationNotificationRecorder` owned by Identity.
- **Inputs**: Account ID, Locale, Correlation ID.
- **Expected Outcome**: Writes the welcome in-app notification and an Outbox row for each enabled asynchronous channel in the caller's registration transaction.
- **Failure Atomicity**: The adapter performs no external I/O and never commits independently. Any failure propagates so the registration transaction rolls back.

### 6.1 RecordNotificationEvent (Internal System Contract)
- **Preconditions**: Called within an enclosing database transaction by a business domain.
- **Inputs**: Event Name, Deduplication Key, Recipient Account ID, In-App Data (Title EN/AR, Body EN/AR, Link), External Dispatch Data (channel targets).
- **Expected Outcome**:
  - Inserts record into `in_app_notifications` table with status `unread`.
  - Inserts an `outbox_messages` row with status `available` for each enabled asynchronous external channel.
- **Observable Behavior**: In-app notification immediately becomes visible as soon as the enclosing transaction commits.

### 6.2 ClaimOutboxBatch (Worker Action)
- **Preconditions**: Executed by background queue worker.
- **Inputs**: Batch Size (e.g. 50), Worker ID, Lease Duration (e.g. 60 seconds).
- **Expected Outcome**:
  - Queries eligible rows where `(status = 'available' AND available_at <= NOW()) OR (status = 'locked' AND lease_expires_at <= NOW())`, ordered by ID with the batch limit and `FOR UPDATE SKIP LOCKED`.
  - Claims available or expired rows and updates each with `status = 'locked'`, `locked_at = NOW()`, `locked_by = worker_id`, a fresh random `lock_token`, `lease_expires_at`, and `attempts = attempts + 1`.
- **Observable Behavior**: Locks batch safely across multiple concurrent worker processes without row contention.

### 6.3 MarkMessageDelivered (Worker Action)
- **Preconditions**: External adapter confirms message delivered or accepted.
- **Inputs**: Message ID, Worker ID, Lock Token.
- **Expected Outcome**: Updates only a row still locked by that worker/token. Zero updated rows means the lease was lost and the worker must discard its result.

### 6.4 HandleDeliveryFailure (Worker Action)
- **Preconditions**: External dispatch threw network or provider error.
- **Inputs**: Message ID, Worker ID, Lock Token, bounded safe error code/message, Trace ID.
- **Expected Outcome**:
  - If `attempts < 5`: sets `status = 'available'`, computes exponential backoff `available_at = NOW() + (2^attempts * 15 seconds)`.
  - If `attempts >= 5`: sets `status = 'dead_letter'`, logs error to Audit, alerts monitoring.

### 6.5 ListCustomerNotifications
- **Preconditions**: Customer is authenticated.
- **Inputs**: Pagination parameters, filter (`all`, `unread_only`).
- **Expected Outcome**: Returns paginated notifications with unread count.
- **Authorization**: Scoped to authenticated customer.

### 6.6 MarkNotificationRead
- **Preconditions**: Customer is authenticated; notification belongs to customer.
- **Inputs**: Notification ID.
- **Expected Outcome**: Updates `status = 'read'`, `read_at = NOW()`. Idempotent (calling repeatedly produces no error).

---

## 7. Business Rules

1. **Standard Notification Events**:
   - `topup.approved`: `"Your wallet top-up of {amount} SDG has been approved."`
   - `topup.rejected`: `"Your wallet top-up was rejected. Reason: {reason}"`
   - `order.submitted`: `"Your order {order_ref} for {service_name} has been received."`
   - `execution.action_required`: `"Action required for order {order_ref}: {instructions}"`
   - `execution.action_received`: `"Your submitted documents have been received and are under review."`
   - `execution.completed`: `"Your order {order_ref} is complete. Your visa is ready for download."`
   - `execution.cancelled`: `"Your order {order_ref} has been cancelled. Reason: {reason}"`
2. **Worker Crash Recovery and Fencing**: An expired lease can be reclaimed with a new token. Every success/failure update compares message ID, `locked` status, worker ID, and token, so the former worker cannot acknowledge or reschedule the reclaimed row.

---

## 8. Edge Cases

- **Multiple Outbox Workers Running Concurrently**: `FOR UPDATE SKIP LOCKED` prevents simultaneous claims. At-least-once delivery can still repeat an external send after a crash or ambiguous provider response; channel idempotency and deterministic keys reduce that risk.
- **External Network Failure**: If external SMS or email gateways go offline, Outbox rows remain persisted in PostgreSQL. When the external provider recovers, workers resume delivery in chronological sequence.

---

## 9. Failure Behavior

- **Dead Letter Exceeded**: After 5 failures the message enters `dead_letter`. `last_error` stores only a bounded sanitized code/message and trace ID; full stack details belong in access-controlled logs.
- **Notification Not Found / Unauthorized**: HTTP 404 Not Found when marking a non-existent or foreign notification as read.

---

## 10. Cross-Domain Interactions

- **Identity Domain**: Calls the Identity-owned `RegistrationNotificationRecorder` port synchronously; Notifications implements it and records the welcome notification in the registration transaction. A later `CustomerRegistered` analytics event is optional and does not drive required messages.
- **Top-Ups Domain**: Approval/rejection actions create atomic in-app records and required external deliveries.
- **Orders Domain**: Purchase submission creates its in-app confirmation and required external deliveries.
- **Fulfillment Domain**: Status transitions and customer action requests create in-app records and required external deliveries.
- **Audit Domain**: Dead-letter occurrences and worker anomalies write to the audit log.
