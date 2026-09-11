# Domain: Notifications and Outbox

## 1. Purpose

The Notifications and Outbox domain manages customer communication, in-app notification tracking, and reliable asynchronous event dispatching. It implements the Transactional Outbox pattern to guarantee that notification events are captured atomically with business database transactions, preventing lost events while isolating business workflows from external network latencies and channel failures.

---

## 2. Actors

- **Customer**: Views in-app notification list, unread notification counter, and marks notifications as read.
- **System (Transactional Outbox)**: Atomically writes notification payloads to the Outbox table during business transactions.
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
  - Deduplication Key (unique string preventing duplicate external sends).
  - Status (`available`, `locked`, `delivered`, `dead_letter`).
  - Available At (timestamp for scheduled/retry execution).
  - Locked At (timestamp when worker claimed message).
  - Locked By (worker instance identifier).
  - Attempts Count (integer, max 5).
  - Last Error (diagnostic text if delivery failed).
  - Delivered At (timestamp when delivery confirmed).

---

## 4. Invariants

1. **Transactional Outbox Atomic Write**: Whenever a business event produces a notification, the Outbox record must be inserted inside the exact same database transaction as the business state change.
2. **Zero External I/O Inside Business Transactions**: No external network calls (SMS APIs, email gateways, WhatsApp webhooks) may ever be executed inside a database transaction.
3. **At-Least-Once Delivery**: All outbox messages are guaranteed to be attempted at least once. If an external channel fails, the message remains in the outbox for exponential backoff retries.
4. **Deduplication Idempotency**: Every outbox message possesses a unique `deduplication_key`. External adapters use this key to prevent duplicate customer messages during network retries.
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

### 6.1 AppendOutboxMessage (Internal System Contract)
- **Preconditions**: Called within an enclosing database transaction by a business domain.
- **Inputs**: Event Name, Deduplication Key, Recipient Account ID, In-App Data (Title EN/AR, Body EN/AR, Link), External Dispatch Data (channel targets).
- **Expected Outcome**:
  - Inserts record into `in_app_notifications` table with status `unread`.
  - Inserts record into `outbox_messages` table with status `available`.
- **Observable Behavior**: In-app notification immediately becomes visible as soon as the enclosing transaction commits.

### 6.2 ClaimOutboxBatch (Worker Action)
- **Preconditions**: Executed by background queue worker.
- **Inputs**: Batch Size (e.g. 50), Worker ID, Lease Duration (e.g. 60 seconds).
- **Expected Outcome**:
  - Queries `outbox_messages` where `status = 'available'` AND `available_at <= NOW()` ORDER BY `id` ASC LIMIT `batch_size` FOR UPDATE SKIP LOCKED.
  - Updates matched records: `status = 'locked'`, `locked_at = NOW()`, `locked_by = worker_id`, `attempts = attempts + 1`.
- **Observable Behavior**: Locks batch safely across multiple concurrent worker processes without row contention.

### 6.3 MarkMessageDelivered (Worker Action)
- **Preconditions**: External adapter confirms message delivered or accepted.
- **Inputs**: Message ID.
- **Expected Outcome**: Updates message: `status = 'delivered'`, `delivered_at = NOW()`, `locked_by = NULL`.

### 6.4 HandleDeliveryFailure (Worker Action)
- **Preconditions**: External dispatch threw network or provider error.
- **Inputs**: Message ID, Error Message.
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
2. **Worker Crash Recovery**: If a worker process crashes while holding locked messages, the lease expires after 60 seconds (`locked_at < NOW() - INTERVAL '60 seconds'`). A recovery scheduler unlocks expired messages back to `available`.

---

## 8. Edge Cases

- **Multiple Outbox Workers Running Concurrently**: PostgreSQL `FOR UPDATE SKIP LOCKED` guarantees that no two workers can claim the same outbox row simultaneously, completely preventing duplicate execution within the platform.
- **External Network Failure**: If external SMS or email gateways go offline, Outbox rows remain persisted in PostgreSQL. When the external provider recovers, workers resume delivery in chronological sequence.

---

## 9. Failure Behavior

- **Dead Letter Exceeded**: When a message fails 5 times, it is placed into `dead_letter` status with the full error stack trace captured in `last_error`.
- **Notification Not Found / Unauthorized**: HTTP 404 Not Found when marking a non-existent or foreign notification as read.

---

## 10. Cross-Domain Interactions

- **Identity Domain**: Listens for `CustomerRegistered` to send welcome messages.
- **Top-Ups Domain**: Approval/rejection actions enqueue outbox notifications.
- **Orders Domain**: Purchase submission enqueues confirmation outbox notification.
- **Fulfillment Domain**: Every status transition and customer action request enqueues an outbox notification.
- **Audit Domain**: Dead-letter occurrences and worker anomalies write to the audit log.
