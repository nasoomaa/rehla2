# Domain: Orders and Purchasing

## 1. Purpose

The Orders and Purchasing domain coordinates the atomic checkout pipeline, idempotency evaluation, and permanent commercial order record management. It acts as the transaction orchestrator uniting service verification, traveler validation, document attachment, wallet debiting, and service execution instantiation into a single, bulletproof, atomic unit of work.

---

## 2. Actors

- **Customer**: Submits an order with service ID, traveler ID, accepted price, form version ID, dynamic field responses, and document IDs. Views their past commercial orders.
- **Operations Staff**: Views commercial orders, paid amounts, payment timestamps, and associated snapshots (view-only; cannot alter financial records).
- **System (Purchasing Orchestrator)**: Locks resources, verifies claims, debits the wallet, creates the commercial order, and spawns the execution record inside a unified PostgreSQL transaction.

---

## 3. Concepts

- **Commercial Order**: The permanent legal record of a purchase. Contains:
  - Order Reference Number (human-friendly alphanumeric code, e.g. `ORD-202609-1001`).
  - Account ID (buyer).
  - Service ID.
  - Price Paid Minor (`amount_minor` in SDG).
  - Currency (`SDG`).
  - Debit Transaction Reference (linking to Wallet Ledger Entry).
  - Service Snapshot (JSON copy of service name, description, duration, and price at purchase time).
  - Traveler Snapshot (JSON copy of traveler name, DOB, gender, passport number, issue/expiry dates).
  - Form Version ID used.
  - Service Price Version ID used.
  - Fulfillment Policy Version ID used by the execution.
  - Status (permanently `paid`).
  - Created Timestamp (UTC).
- **Scoped Idempotency Key**: A unique string provided in the `Idempotency-Key` HTTP header, scoped to the authenticated `account_id`.
- **Request Fingerprint**: A SHA-256 hash of canonical service ID, traveler ID, accepted price and `accepted_price_version_id`, `form_version_id`, `fulfillment_policy_version_id`, ordered answers, and referenced document IDs.
- **Purchase Attempt**: A `purchase_attempts` row scoped by unique `(account_id, idempotency_key)` containing the canonical fingerprint, status, the three captured version IDs, and the stored response/result references. It is inserted or locked before all other checkout resources.
- **Captured Versions**: The accepted price version, form version, and fulfillment policy version are part of both the fingerprint and the successful stored result.
- **One Traveler per Order**: A single commercial order represents exactly one service for exactly one traveler.

---

## 4. Invariants

1. **Atomic Purchase Guarantee**: A purchase submission must succeed entirely or leave zero partial state. There can never exist a wallet debit without a commercial order, nor a commercial order without an initialized service execution record.
2. **Permanent Order Immutability**: Once created, a Commercial Order record, its snapshots, and its financial debit reference can never be modified or deleted by any administrative action or database update.
3. **No Commercial Drafts**: An abandoned application form creates no record, no hold on funds, and no order draft. The platform maintains zero incomplete commercial drafts.
4. **Authoritative Moment-of-Submission Verification**: The submitted `accepted_price` must match the service’s authoritative price in the database at the exact microsecond of transaction execution. If the price changed, checkout aborts.
5. **Strict Ownership Validation**: The selected traveler profile and all attached document IDs must belong strictly to the authenticated customer account submitting the order.
6. **Idempotency Guarantee**:
   - Resubmitting with the same idempotency key and identical payload returns the existing order result (HTTP 200/201).
   - Resubmitting with the same idempotency key but a different payload returns HTTP 409 Conflict with code `idempotency.key_reused`.
7. **Transactional Effects**: The Order state, debit, Execution, Audit entry, in-app notification, required Outbox rows, and completed purchase attempt commit in the same PostgreSQL transaction. External network I/O is forbidden inside the transaction.

---

## 5. State Model

### Commercial Order Lifecycle
```text
[SubmitOrder Atomic Transaction]
               │
               ▼
              Paid (Permanent Immutable Legal State)
```
- A Commercial Order enters the database in the `paid` state and remains in that state permanently.
- Commercial status is NEVER transitioned to operational fulfillment statuses like "In Processing" or "Action Required". Those lifecycles are owned entirely by the Fulfillment domain.

---

## 6. Commands and Actions

### 6.1 SubmitOrder
- **Preconditions**: Customer is authenticated; `Idempotency-Key` header provided; account is active.
- **Inputs**:
  - Idempotency Key (header).
  - Service ID.
  - Traveler ID.
  - Accepted Price Minor (`amount_minor`) and Accepted Price Version ID.
  - Form Version ID.
  - Form Answers (key-value dictionary).
  - Document IDs (list of clean document IDs referenced in answers).
- **Expected Outcome (Atomic Transaction Execution Order)**:
  1. Begin PostgreSQL database transaction.
  2. Insert the pending idempotency attempt under unique `(account_id, idempotency_key)`, or lock/read the existing row. A matching completed fingerprint returns its response; a mismatch returns HTTP 409. The unique insert resolves the missing-row race.
  3. Lock the service and current price-version pointer. Assert active status and exact accepted price/version.
  4. Lock the published form-version pointer. Assert the submitted form version is current and run Forms shape validation, receiving extracted opaque document references and required classifications.
  5. Through Catalog's `PublishedFulfillmentPolicyReader`, lock the published fulfillment-policy pointer and capture its immutable version.
  6. Lock the traveler, verify ownership, and build the immutable traveler snapshot.
  7. Call `OwnedDocuments::assertCleanOwned` with the extracted IDs, account ID, and required classifications; Documents locks references in ascending opaque-ID order and verifies existence, ownership, `clean` status, classification, and attachment eligibility.
  8. Lock the customer wallet and verify sufficient balance.
  9. Allocate the Order ID before the debit, then call `DebitWallet` with that Order ID as its immutable reference; obtain `ledger_entry_id`.
  10. Insert the immutable Commercial Order with service, traveler, price, form, policy, and debit snapshots; attach the documents.
  11. Call the Purchasing-owned `ExecutionCreator` port. Its Fulfillment implementation creates the service execution with the captured policy version inside this transaction.
  12. Insert audit, in-app notification, and required outbox records.
  13. Store the order response in the idempotency attempt and commit.
- **Observable Behavior**: Returns HTTP 201 Created with order reference, summary, paid amount, and execution tracking ID. Customer wallet is debited; order appears in "My Orders".
- **Validation Rules**: All fields mandatory. Accepted price must match database price. Form responses must pass schema rules.

### 6.2 GetOrderDetails
- **Preconditions**: Caller is the owning customer or authorized staff member (`orders.view`).
- **Inputs**: Order ID (or Reference Number).
- **Expected Outcome**: Returns commercial order details, frozen service snapshot, frozen traveler snapshot, debit reference, and current execution link.
- **Authorization**: Scoped to authenticated customer. Unauthorized customer receives HTTP 404.

### 6.3 ListCustomerOrders
- **Preconditions**: Customer is authenticated.
- **Inputs**: Pagination parameters and optional execution-status filter. Commercial order status is always `paid`.
- **Expected Outcome**: Chronological list of commercial orders placed by the customer, sorted by creation date descending.

---

## 7. Business Rules

1. **Lock Sequencing Protocol**:
   To prevent database deadlocks under heavy concurrent load, the transaction must acquire row locks in a strictly defined order:
   1. Idempotency record lock.
   2. Service/current-price pointer.
   3. Published form pointer.
   4. Published fulfillment-policy pointer.
   5. Traveler row.
   6. Document rows in ascending opaque-ID order.
   7. Wallet row.
   Any command acquiring more than one of these resources follows this order.
2. **Order Reference Generation**:
   Order references follow the deterministic format:
   `ORD-YYYYMM-XXXX` (where `YYYYMM` is current year/month and `XXXX` is a sequential or cryptographic alphanumeric code).

---

## 8. Edge Cases

- **Price Change Mid-Form**: Customer opened form at 25,000 SDG. Admin increases price to 30,000 SDG. Customer submits with `accepted_price = 25,000 SDG`. Transaction detects mismatch with current authoritative price (30,000 SDG), rolls back immediately, and returns HTTP 409 Conflict with `service.price_changed`. Customer is shown the updated price and must re-confirm.
- **Balance Insufficiency at Final Click**: Customer had 50,000 SDG, but placed another order 5 seconds earlier reducing balance to 10,000 SDG. Customer now clicks submit on a 25,000 SDG order. `DebitWallet` evaluates `10,000 < 25,000`, rolls back, and returns `wallet.insufficient_balance`.
- **Rapid Network Resubmission**: Customer clicks submit twice due to mobile lag. Both requests carry the identical `Idempotency-Key`. The first request processes and commits. The second request locks the idempotency record, detects matching fingerprint and completed state, and immediately returns the created order without executing a second debit.

---

## 9. Failure Behavior

- **Price Changed**: HTTP 409 Conflict, code `service.price_changed`.
- **Insufficient Balance**: HTTP 422, code `wallet.insufficient_balance`.
- **Idempotency Key Conflict**: HTTP 409 Conflict, code `idempotency.key_reused`. Message: `"The idempotency key has already been used with a different request payload."`
- **Form Version Outdated**: HTTP 409 Conflict, code `form.version_outdated`.
- **Fulfillment Policy Missing**: HTTP 422, code `service.fulfillment_policy_missing`.
- **Traveler Ownership Violation**: HTTP 404 Not Found, code `traveler.not_found`.
- **Invalid Document Attachment**: HTTP 422, code `document.invalid_attachment`; no debit, Order, or Execution is written.

---

## 10. Cross-Domain Interactions

- **Service Catalog**: Authoritative pricing and availability verified.
- **Application Forms**: Shape validation and schema snapshotting; it does not authorize document state.
- **Travelers**: Ownership validation and `TravelerSnapshot` generation.
- **Documents**: `OwnedDocuments::assertCleanOwned` authorizes and attaches clean owned references with required classifications.
- **Wallet**: Atomic debiting and ledger synchronization.
- **Fulfillment**: Implements `ExecutionCreator` owned by Purchasing and spawns the independent operational record without a reverse Purchasing dependency.
- **Notifications**: Creates the in-app confirmation atomically and queues enabled external channels through Outbox.
- **Audit**: Log entry recorded for commercial purchase transaction.
