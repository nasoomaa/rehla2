# Domain: Top-Ups and Bank Transfers

## 1. Purpose

The Top-Ups and Bank Transfers domain manages the funding pipeline for customer wallets. It owns platform bank account definitions, customer top-up request submissions, external bank transaction reference verification, receipt document association, administrative review workflows, and idempotent balance crediting.

---

## 2. Actors

- **Customer**: Inspects active platform bank accounts, performs external bank transfers, submits top-up requests with receipts and transaction reference numbers, and tracks review status.
- **Operations Reviewer (Staff)**: Inspects pending top-up requests, verifies receipt images against internal commercial bank statements, and approves or rejects requests with documented reasons.
- **Platform Administrator**: Manages platform bank accounts and the auditable minimum top-up setting.

---

## 3. Concepts

- **Platform Bank Account**: An official bank account owned by Rehla to receive customer transfers. Contains Bank Name, Beneficiary Name, Account Number, public Bank Logo Media, Sort Order, and Status (`active`, `inactive`).
- **Minimum Top-Up Setting**: The positive integer threshold evaluated at submission. Its initial value is `500000` minor units and every change records actor, old/new values, reason, and UTC timestamp.
- **Top-Up Request**: A formal submission by a customer declaring a transfer. Contains:
  - Request ID.
  - Account ID.
  - Platform Bank Account ID.
  - Claimed Amount Minor and the minimum setting value evaluated at submission.
  - Transaction Reference Number (bank transaction ID).
  - Normalized Transaction Reference Number (uppercase, stripped).
  - Receipt Document ID (clean private document).
  - Submission Timestamp.
  - Review Status (`under_review`, `approved`, `rejected`).
  - Reviewer Staff ID (if decided).
  - Decision Timestamp (if decided).
  - Rejection Reason (mandatory if rejected).
- **Transaction Reference Number**: The unique transaction sequence code issued by the customer's banking application (e.g. Bank of Khartoum / Bankak, O-Cash, Fawry).

---

## 4. Invariants

1. **Configurable Minimum Top-Up Threshold**: The initial minimum is 5,000.00 SDG (`500000` minor units). Authorized staff may change it; each submission atomically evaluates and snapshots the current positive threshold.
2. **Bank Reference Uniqueness**: The combination of `(bank_account_id, normalized_reference)` must be globally unique across all top-up requests. A transaction reference number cannot be submitted twice for the same bank account.
3. **Receipt Document Mandatory**: A top-up request must be accompanied by exactly one verified `clean` document ID representing the bank transfer receipt.
4. **Single Credit Invariant**: An approved top-up request can credit the user’s wallet exactly once. Concurrent, repetitive, or replayed approval commands must never produce duplicate credits.
5. **Rejection Safety**: Rejecting a top-up request records the decision, reviewer ID, timestamp, and rejection reason, but produces zero changes to the wallet balance.
6. **Bank Account Historical Preservation**: Deactivating a platform bank account hides it from new customer top-ups, but preserves all historical top-up requests and audit records linked to it.
7. **Reference Permanence**: The unique bank/reference pair is reserved by its original request in every state. Correcting an unreadable receipt replaces the receipt on that same `under_review` request and never creates a second request with the reference.
8. **Transactional Decision Effects**: Each approval or rejection commits the Top-Up domain state, Audit entry, in-app notification, and required Outbox rows in the same PostgreSQL transaction; approval also commits exactly one Wallet credit. External network I/O is forbidden inside the transaction.

---

## 5. State Model

### Top-Up Request Lifecycle
```text
[Submitted by Customer]
          │
          ▼
    Under Review
          │
          ├─────────────────────────┐
          ▼                         ▼
      Approved                   Rejected
 (Credits Wallet atomically)  (Records reason; balance unchanged)
```

- **Under Review**: Request created; awaiting review by authorized staff.
- **Approved**: Verified by staff; wallet credited with claimed amount; terminal state.
- **Rejected**: Denied by staff with explanatory reason; terminal state.

---

## 6. Commands and Actions

### 6.1 SubmitTopUpRequest
- **Preconditions**: Customer is authenticated; platform bank account exists and is `active`; receipt document exists, is `clean`, and belongs to customer.
- **Inputs**: Bank Account ID, Amount (`amount_minor`), Transaction Reference Number, Receipt Document ID.
- **Expected Outcome**:
  - Reference normalized (spaces and hyphens stripped, uppercase).
  - Validates `(bank_account_id, normalized_reference)` uniqueness.
  - Document status transitioned to `attached`.
  - Top-up request record inserted with status `under_review`.
- **Observable Behavior**: Top-up request appears in customer’s top-up list and admin review queue.
- **Validation Rules**:
  - Amount: positive integer greater than or equal to the locked current minimum setting.
  - Transaction Reference Number: non-empty string, min 4 chars, max 50 chars.
- **Authorization**: Scoped to authenticated customer. Write rate limit applied (max 5 submissions per hour).
- **Failure Behavior**:
  - Below minimum: HTTP 422 with `top_up.below_minimum`.
  - Duplicate reference: HTTP 422 with code `top_up.reference_used` and message: `"This bank transaction reference number has already been used."`

### 6.2 ApproveTopUpRequest
- **Preconditions**: Staff member has `topups.review`, TOTP was verified within four hours, and the request is `under_review`.
- **Inputs**: Top-Up Request ID.
- **Expected Outcome**:
  - Locks top-up row (`SELECT FOR UPDATE`).
  - Evaluates current status: if already `approved`, returns existing success result idempotently.
  - If status is `rejected`, aborts with `top_up.already_decided`.
  - Inside same database transaction, calls Wallet domain `CreditWallet` with amount and reference.
  - Updates top-up status to `approved`, sets `reviewer_id` and `decision_at = NOW()`.
  - Appends Audit log entry.
  - Creates the in-app notification and enqueues external-channel delivery in the Outbox.
- **Observable Behavior**: Customer wallet balance immediately reflects credited amount; customer receives in-app notification.
- **Failure Behavior**: Concurrent approval calls serialize cleanly; second call detects `approved` status and returns success without double-crediting.

### 6.3 RejectTopUpRequest
- **Preconditions**: Staff member has `topups.review`, TOTP was verified within four hours, and the request is `under_review`.
- **Inputs**: Top-Up Request ID, Rejection Reason (EN/AR).
- **Expected Outcome**:
  - Locks top-up row.
  - Updates status to `rejected`, sets `reviewer_id`, `decision_at = NOW()`, and `rejection_reason`.
  - Appends Audit log entry.
  - Creates the in-app notification and enqueues external-channel delivery in the Outbox.
  - Wallet balance is left untouched.
- **Validation Rules**: Rejection reason is mandatory (min 10 chars, max 500 chars).
- **Observable Behavior**: Request marked rejected on customer dashboard; customer sees rejection reason.

### 6.4 ManageBankAccount (Admin Command)
- **Preconditions**: Staff has `banks.manage` ability.
- **Inputs**: Bank Name, Beneficiary Name, Account Number, Logo Document ID, Sort Order, Status (`active` or `inactive`).
- **Expected Outcome**: Bank account created or updated.

### 6.5 ReplaceTopUpReceipt
- **Preconditions**: The customer owns the request; it remains `under_review`; replacement document belongs to that customer and is `clean` with classification `bank_receipt`.
- **Inputs**: Top-Up Request ID, Replacement Receipt Document ID.
- **Expected Outcome**: The same request and normalized reference remain in place; the replacement becomes the current receipt and an audit entry records old/new document IDs. The former receipt remains historically linked and follows attached-document retention.
- **Failure Behavior**: A decided request returns HTTP 409 `top_up.already_decided`; a foreign request returns HTTP 404.

### 6.6 ConfigureMinimumTopUp
- **Preconditions**: Staff has `topups.settings.manage` and a TOTP verification no older than four hours.
- **Inputs**: Positive `minimum_amount_minor`, mandatory reason.
- **Expected Outcome**: The setting changes atomically and an immutable audit entry records actor, old/new values, reason, and timestamp. Existing requests retain their submitted amount and captured threshold.

---

## 7. Business Rules

1. **Transaction Reference Normalization**:
   Given bank reference string `R`:
   - Strip all leading and trailing whitespace.
   - Remove internal hyphens, spaces, and slashes.
   - Convert to uppercase ASCII.
   - Enforce database constraint `UNIQUE(bank_account_id, normalized_reference)`.
2. **Review Turnaround Tracking**: Every submission records exact UTC timestamp `submitted_at`. Decision records `decision_at`. The duration `decision_at - submitted_at` feeds the platform turnaround SLA metric.

---

## 8. Edge Cases

- **Duplicate Submission via Network Glitch**: Customer double-clicks submit. First request acquires lock and inserts record. Second request hits database unique constraint on `(bank_account_id, normalized_reference)` and is rejected with `top_up.reference_used`.
- **Two Reviewers Process Same Request Simultaneously**: Reviewer A and Reviewer B open the same top-up in the admin panel. Reviewer A clicks Approve. The database locks the row, executes the credit, and commits. Reviewer B clicks Reject a moment later. Reviewer B's transaction acquires the lock, observes status is already `approved`, and halts with `top_up.already_decided`.

---

## 9. Failure Behavior

- **Reference Already Used**: HTTP 422, code `top_up.reference_used`. Localized message: `"This bank transaction reference number has already been used."`
- **Amount Below Minimum**: HTTP 422, code `top_up.below_minimum`; response metadata includes the currently required `minimum_amount_minor`.
- **Missing Receipt**: HTTP 422, code `top_up.receipt_required`. Message: `"A clear bank transfer receipt must be attached."`
- **Already Decided**: HTTP 409 Conflict, code `top_up.already_decided`. Message: `"This top-up request has already been reviewed."`

---

## 10. Cross-Domain Interactions

- **Documents Domain**: Uploads receipt image, verifies `clean` status, and marks it `attached`.
- **Wallet Domain**: Approval triggers atomic `CreditWallet` call inside the review transaction.
- **Notifications Domain**: Creates atomic in-app results and Outbox records for enabled external channels.
- **Audit Domain**: Review decisions, reviewer IDs, reasons, and timestamps are recorded in the audit log.
