# Test Vectors: Idempotency and Deduplication

## 1. Scope and Rule Being Proven

This suite proves the deterministic behavior of idempotency keys during checkout, deduplication keys in the transactional outbox, and bank reference uniqueness in top-up processing.

---

## 2. Invariants Under Test

1. **Scoped Idempotency**: An `Idempotency-Key` is scoped strictly to the authenticated `account_id`.
2. **Identical Replay**: Resubmitting a request with the same key and identical payload returns the exact original successful response without re-executing business side-effects (zero additional wallet debits).
3. **Payload Fingerprint Mismatch**: Resubmitting with an existing key but a different payload triggers HTTP 409 Conflict with code `order.idempotency_conflict`.
4. **Outbox Deduplication**: Duplicate event emissions sharing the same `deduplication_key` are rejected by PostgreSQL unique constraints.
5. **Bank Reference Deduplication**: The same normalized bank reference number cannot be submitted twice for the same destination bank account.

---

## 3. Order Checkout Idempotency Test Vectors

| Case ID | Account ID | Idempotency Key | Payload Fingerprint (SHA-256) | Existing DB State | Expected HTTP Status | Expected Outcome |
|---|---|---|---|---|---|---|
| **IDP-01** | `acc_10` | `"key-alpha"` | `hash("svc1:trav12:2500000:v4")` | None | **201 Created** | Order created; wallet debited once |
| **IDP-02** | `acc_10` | `"key-alpha"` | `hash("svc1:trav12:2500000:v4")` | Completed (`ORD-1001`) | **200 OK** | Returns existing `ORD-1001`; zero debit |
| **IDP-03** | `acc_10` | `"key-alpha"` | `hash("svc1:trav99:2500000:v4")` | Completed (`ORD-1001`) | **409 Conflict** | Rejected: `order.idempotency_conflict` |
| **IDP-04** | `acc_10` | `"key-alpha"` | `hash("svc2:trav12:3000000:v1")` | Completed (`ORD-1001`) | **409 Conflict** | Rejected: `order.idempotency_conflict` |
| **IDP-05** | `acc_20` | `"key-alpha"` | `hash("svc1:trav88:2500000:v4")` | None for `acc_20` | **201 Created** | Independent account; order created |
| **IDP-06** | `acc_10` | `""` (Omitted) | Any | N/A | **422 Unprocessable Entity** | `request.idempotency_key_required` |

---

## 4. Bank Reference Deduplication Test Vectors

| Case ID | Target Bank Account ID | Raw Reference | Normalized Reference | Existing DB Reference for Bank | Expected Result | Rejection Code |
|---|---|---|---|---|---|---|
| **REF-01** | Bank #1 | `"BOK-123456"` | `"BOK123456"` | None | **Valid** | Top-up request accepted |
| **REF-02** | Bank #1 | `" bok-123-456 "`| `"BOK123456"` | `"BOK123456"` exists | **Invalid** | `top_up.reference_used` |
| **REF-03** | Bank #1 | `"bok 123456"` | `"BOK123456"` | `"BOK123456"` exists | **Invalid** | `top_up.reference_used` |
| **REF-04** | Bank #2 (Different Bank)| `"BOK-123456"` | `"BOK123456"` | Exists on Bank #1 only | **Valid** | Allowed (Scoped per bank account) |
| **REF-05** | Bank #1 | `"BOK-123457"` | `"BOK123457"` | `"BOK123456"` exists | **Valid** | Distinct reference string |

---

## 5. Transactional Outbox Deduplication Vectors

| Case ID | Event Name | Generated Deduplication Key | Existing Outbox Keys | Expected DB Outcome |
|---|---|---|---|---|
| **OUT-01** | `topup.approved` | `topup_approved:401` | None | Row inserted with status `available` |
| **OUT-02** | `topup.approved` | `topup_approved:401` | `topup_approved:401` | Unique constraint violation (`23505`); duplicate insert blocked |
| **OUT-03** | `order.submitted`| `order_submitted:ORD-1001`| None | Row inserted with status `available` |
| **OUT-04** | `order.submitted`| `order_submitted:ORD-1001`| `order_submitted:ORD-1001` | Unique constraint violation; duplicate blocked |
