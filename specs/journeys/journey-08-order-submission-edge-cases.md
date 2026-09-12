# Journey 08: Order Submission Edge Cases and Concurrency Resilience

## 1. Goal

The platform deterministically handles complex concurrent submissions, network retries, mid-flight administrative price adjustments, and dynamic form version updates without race conditions, duplicate debits, negative balances, or orphaned database records.

---

## 2. Actors

- **Customer (Submitting concurrent or retried requests)**
- **Platform Administrator (Making live catalog updates)**
- **Orders and Purchasing Domain (Transaction Orchestrator)**
- **Wallet and Financial Ledger Domain**
- **Service Catalog Domain**
- **Application Forms Domain**

---

## 3. Preconditions

1. Customer has an active wallet with `25,000.00 SDG` (`2,500,000` minor units).
2. Service A ("UAE Visa") is priced at `25,000.00 SDG` (`2,500,000` minor units).
3. Service B ("Qatar Visa") is priced at `25,000.00 SDG` (`2,500,000` minor units).
4. Customer has saved travelers Ahmed and Sarah.

---

## 4. Scenario Sequences

### Scenario A: Concurrent Purchases with Balance Sufficient for Only One Order
1. **Trigger**: Customer opens two browser tabs. In Tab 1, customer prepares to buy UAE Visa (`25,000 SDG`) for Ahmed. In Tab 2, customer prepares to buy Qatar Visa (`25,000 SDG`) for Sarah.
2. **Concurrent Action**: Customer clicks "Submit Order" on both tabs at the exact same millisecond.
3. **Execution Sequence**:
   - Request 1 (Tab 1) and Request 2 (Tab 2) arrive concurrently at the application server.
   - Request 1 enters the database transaction first and executes `SELECT * FROM wallets WHERE id = 10 FOR UPDATE`.
   - The PostgreSQL row-level lock is granted to Request 1.
   - Request 2 attempts `SELECT * FROM wallets WHERE id = 10 FOR UPDATE` and blocks, waiting for Request 1 to commit or rollback.
   - Request 1 evaluates balance: `2,500,000 >= 2,500,000` (Sufficient).
   - Request 1 debits `2,500,000`, setting balance to `0`.
   - Request 1 creates Commercial Order `ORD-1001` and Execution `exec-1001`.
   - Request 1 commits its transaction and releases the wallet row lock.
   - Request 2 unblocks and acquires the lock.
   - Request 2 reads updated balance: `0 minor units`.
   - Request 2 evaluates `0 < 2,500,000` (Insufficient).
   - Request 2 rolls back its transaction immediately.
4. **Observable Outcome**:
   - Tab 1 displays: `"Order Confirmed! Reference: ORD-1001"`.
   - Tab 2 displays: `"Your wallet balance is insufficient to complete this order. Please top up your wallet and try again."`
   - **Critical Invariant**: Under no circumstance does the wallet balance drop below zero or enter a negative state.

---

### Scenario B: Price Changes While Customer is Filling the Application Form
1. **Trigger**: Customer opens the application form for UAE Visa at 10:00 AM when the price is `25,000.00 SDG`.
2. **Administrative Mutation**: At 10:05 AM, an administrator updates the UAE Visa price to `30,000.00 SDG` in the Service Catalog.
3. **Submission**: At 10:10 AM, the customer finishes typing and clicks "Submit Order" with `accepted_price_minor = 2500000`.
4. **Execution Sequence**:
   - Transaction begins and locks resources.
   - System queries Service Catalog: `SELECT price_minor FROM services WHERE id = 1`.
   - System discovers `price_minor = 3,000,000`.
   - System compares `accepted_price_minor (2,500,000) != authoritative_price (3,000,000)`.
   - Transaction rolls back immediately before calling `DebitWallet`.
5. **Observable Outcome**:
   - Server returns HTTP 409 Conflict with code `service.price_changed`.
   - UI renders a modal:
     `"The service price has been updated since opening the order. Previous Price: 25,000.00 SDG. Updated Price: 30,000.00 SDG. Please review and confirm the new price to proceed."`
   - Wallet balance is NOT debited.

---

### Scenario C: Form Version Published While Customer is Filling Application
1. **Trigger**: Customer opens the application form when Form Version #3 is active.
2. **Administrative Mutation**: Administrator publishes Form Version #4 (adding a mandatory field: "Place of Birth").
3. **Submission**: Customer clicks submit carrying `form_version_id = 3`.
4. **Execution Sequence**:
   - Transaction verifies active form version for service: discovers active version is #4.
   - Transaction detects version mismatch (`3 != 4`).
   - Transaction rolls back before debiting wallet.
5. **Observable Outcome**:
   - Server returns HTTP 409 Conflict with code `form.version_outdated`.
   - UI notifies customer: `"The application form for this service has been updated. Please complete the new requirements."`
   - UI reloads the form showing Version #4 with the additional field.

---

### Scenario D: Network Timeout and Idempotent Retry
1. **Trigger**: Customer clicks "Submit Order" with header `Idempotency-Key: "idemp-key-555"`.
2. **Event**: The server processes the request, debits the wallet, creates Order `ORD-1005`, but the customer's mobile cellular network drops before receiving the HTTP 201 response.
3. **Retry**: The mobile client reconnects 10 seconds later and automatically retries the exact same HTTP POST request with `Idempotency-Key: "idemp-key-555"`.
4. **Execution Sequence**:
   - Transaction locks `purchase_attempts` for `("account_10", "idemp-key-555")`.
   - Finds existing record with status `completed`.
   - Computes SHA-256 fingerprint of current payload and matches with stored fingerprint.
   - Transaction exits cleanly without executing `DebitWallet` or `CreateOrder`.
5. **Observable Outcome**:
   - Server returns HTTP 200 OK with the cached order details for `ORD-1005`.
   - Wallet is debited exactly once; exactly one order exists.

---

### Scenario E: Idempotency Key Reused with Different Payload
1. **Trigger**: A buggy client uses `Idempotency-Key: "idemp-key-555"` (which created Order `ORD-1005` for Ahmed), but sends a payload specifying traveler **Sarah**.
2. **Execution Sequence**:
   - Server locks `purchase_attempts` for `("account_10", "idemp-key-555")`.
   - Finds existing record.
   - Computes SHA-256 fingerprint of new payload; detects mismatch with stored fingerprint.
   - Transaction rolls back immediately.
3. **Observable Outcome**:
   - Server returns HTTP 409 Conflict with code `idempotency.key_reused`.
   - Message: `"The idempotency key has already been used with a different request payload."`
   - No debit occurs; no order is created.

---

## 5. Final Observable State

1. Under all concurrent, retried, and interrupted conditions:
   - Wallet balances remain non-negative (`>= 0`).
   - Ledger records match actual balance movements.
   - Commercial orders are never created without a successful debit.
   - Debits are never committed without an order.
