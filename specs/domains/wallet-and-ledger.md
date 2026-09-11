# Domain: Wallet and Financial Ledger

## 1. Purpose

The Wallet and Financial Ledger domain owns customer pre-funded balances, transaction ledger accounting, currency representation, balance invariants, and financial reconciliation. It guarantees absolute consistency, non-negativity of funds, concurrency safety, and permanent immutability of financial records.

---

## 2. Actors

- **Customer**: Views current balance, reviews detailed transaction ledger history, and authorizes order debits.
- **Operations Staff**: Views wallet transactions for auditing and dispute investigation; cannot directly alter balances outside approved top-up workflows.
- **System**: Enforces atomic debits, credits, integer minor unit precision, append-only invariants, and periodic ledger reconciliation.

---

## 3. Concepts

- **Wallet**: A customer’s financial balance container, linked 1-to-1 with an Account Owner. Stores `currency` (`SDG`), `current_balance_minor`, and status (`active`, `frozen`).
- **Integer Minor Units (`amount_minor`)**: All monetary values are represented as non-negative 64-bit integers with a scale of 100:
  - 1 SDG = 100 minor units.
  - 5,000.00 SDG = 500,000 minor units.
  - Floating-point representations are strictly prohibited across all data models, calculations, and APIs.
- **Wallet Ledger Entry**: An immutable financial entry representing a balance movement. Contains:
  - ID.
  - Wallet ID.
  - Entry Type (`credit` for balance additions, `debit` for balance deductions).
  - Amount Minor (`amount_minor > 0`).
  - Running Balance Minor (balance after this entry committed).
  - Source / Reference Type (`top_up_approval`, `order_purchase`, `administrative_adjustment`).
  - Reference ID (e.g. `top_up_id`, `order_id`).
  - Description (English and Arabic human-readable narrative).
  - Created Timestamp (UTC).
- **Reconciliation Run**: A scheduled system check that computes the sum of all historical ledger entries for a wallet and asserts mathematical equality with `current_balance_minor`.

---

## 4. Invariants

1. **Non-Negative Balance Invariant**: A wallet balance can never become negative under any circumstance (`current_balance_minor >= 0`). This rule is enforced by database-level check constraints and pessimistic locking.
2. **Immutable Append-Only Ledger**: Wallet ledger entries are strictly immutable. Once inserted, a ledger row can never be updated, deleted, or re-ordered by any application code or administrative operation.
3. **Correction by Offset Only**: If an erroneous balance transaction occurs, it cannot be edited or erased; it must be corrected by inserting a new compensating ledger entry with an explanatory audit reason.
4. **Single Currency Exclusivity**: The wallet operates exclusively in Sudanese Pounds (`SDG`). Multi-currency wallets are not supported in Phase 1.
5. **Atomic Ledger-Wallet Synchronization**: Updating the cached `current_balance_minor` and inserting the corresponding `WalletLedgerEntry` must always occur in the same database transaction.
6. **Unique Event References**: A specific external event (e.g. Top-Up ID #401) can produce at most one credit entry in the ledger. Duplicate credit attempts for the same top-up are rejected by database unique constraints.

---

## 5. State Model

### Wallet Status
```text
[Initialized on Register] ──► Active ◄──► Frozen
```
- **Active**: Can receive top-up credits and authorize service debits.
- **Frozen**: Administratively locked (e.g. pending fraud investigation). Cannot be debited or credited.

---

## 6. Commands and Actions

### 6.1 InitializeWallet (System Action)
- **Preconditions**: Customer account created; wallet does not exist.
- **Inputs**: Account ID.
- **Expected Outcome**: New wallet initialized with `current_balance_minor = 0`, status `active`, currency `SDG`.
- **Observable Behavior**: Customer sees 0.00 SDG initial balance on dashboard.

### 6.2 CreditWallet (System Contract Command)
- **Preconditions**:
  - Called within an enclosing database transaction (by TopUps domain).
  - Wallet is `active`.
  - Amount is a positive integer (`amount_minor > 0`).
  - Reference Type and Reference ID provided.
- **Inputs**: Wallet ID (or Account ID), Amount Minor, Reference Type (`top_up_approval`), Reference ID, Description (EN/AR).
- **Expected Outcome**:
  - Wallet row locked (`SELECT FOR UPDATE`).
  - Idempotency verified (asserts no existing entry exists for this Reference Type and Reference ID).
  - `current_balance_minor` incremented by `amount_minor`.
  - `WalletLedgerEntry` inserted with entry_type `credit` and new running balance.
- **Observable Behavior**: Immediate balance increase. User receives notification.
- **Failure Behavior**: If duplicate reference detected, aborts with `wallet.duplicate_credit_reference`.

### 6.3 DebitWallet (System Contract Command)
- **Preconditions**:
  - Called within an enclosing database transaction (by Purchasing domain).
  - Wallet is `active`.
  - Amount is a positive integer (`amount_minor > 0`).
  - Reference Type (`order_purchase`) and Reference ID provided.
- **Inputs**: Wallet ID (or Account ID), Amount Minor, Reference Type, Reference ID, Description (EN/AR).
- **Expected Outcome**:
  - Wallet row locked (`SELECT FOR UPDATE`).
  - Verifies `current_balance_minor >= amount_minor`.
  - `current_balance_minor` decremented by `amount_minor`.
  - `WalletLedgerEntry` inserted with entry_type `debit` and new running balance.
- **Observable Behavior**: Immediate balance decrease. Commercial order receives transaction reference.
- **Failure Behavior**: If `current_balance_minor < amount_minor`, transaction rolls back immediately with code `wallet.insufficient_balance`.

### 6.4 GetBalanceAndLedger
- **Preconditions**: Customer is authenticated or staff has `wallets.view`.
- **Inputs**: Account ID, Pagination parameters.
- **Expected Outcome**: Current balance formatted in SDG, plus chronological ledger entries.
- **Observable Behavior**: UI displays current balance (e.g. `25,000.00 SDG`) and transaction table.
- **Authorization**: Scoped to authenticated customer or authorized staff.

### 6.5 RunReconciliation (System Scheduled Action)
- **Preconditions**: Scheduled maintenance or administrative audit.
- **Inputs**: Wallet ID (or batch of wallets).
- **Expected Outcome**:
  - Computes `computed_balance = SUM(credit amounts) - SUM(debit amounts)`.
  - Compares `computed_balance == current_balance_minor`.
  - If equal: reconciliation pass logged.
  - If unequal: critical alert triggered, wallet automatically frozen, error logged to Audit.

---

## 7. Business Rules

1. **Monetary Formatting Standard**:
   - Stored: `amount_minor = 2500000`
   - Formatted English: `"25,000.00 SDG"`
   - Formatted Arabic: `"25,000.00 ج.س"`
2. **Pessimistic Locking Ordering**: Whenever a transaction involves wallet balance modification, the wallet row must be locked using `SELECT FOR UPDATE` before evaluating balance sufficiency. This guarantees serialized execution and prevents race conditions.

---

## 8. Edge Cases

- **Two Concurrent Orders with Insufficient Total Balance**:
  - User has `25,000.00 SDG` in wallet.
  - User submits Order A (cost `25,000.00 SDG`) and Order B (cost `25,000.00 SDG`) at the exact same millisecond.
  - Database row lock ensures Order A locks the wallet, debits `25,000.00 SDG`, drops balance to `0.00 SDG`, and commits.
  - Order B acquires the lock next, evaluates `0.00 < 25,000.00`, and immediately fails with `wallet.insufficient_balance`. Under no circumstance can both succeed.
- **Wallet Inactive or Suspended**: If account is suspended or wallet is frozen, any debit attempt fails with `wallet.frozen`.

---

## 9. Failure Behavior

- **Insufficient Balance**: Code `wallet.insufficient_balance`, HTTP 422. Localized message: `"Your wallet balance is insufficient to complete this order. Please top up your wallet and try again."`
- **Duplicate Credit**: Code `wallet.duplicate_credit_reference`, HTTP 409 Conflict.
- **Wallet Frozen**: Code `wallet.frozen`, HTTP 403 Forbidden. Message: `"Your wallet is currently frozen. Please contact customer support."`

---

## 10. Cross-Domain Interactions

- **Top-Ups Domain**: Top-up approval triggers `CreditWallet`.
- **Purchasing & Orders Domain**: Order checkout triggers `DebitWallet` inside the atomic purchase transaction.
- **Reporting Domain**: Reads ledger entries to compute financial volume metrics.
- **Audit Domain**: Balance adjustments, freezes, and reconciliation discrepancies are recorded in the audit log.
