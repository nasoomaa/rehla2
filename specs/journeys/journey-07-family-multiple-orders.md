# Journey 07: Family Multiple Independent Orders

## 1. Goal

An account owner manages multiple family member traveler profiles, funds their platform wallet once, and places multiple distinct, independent commercial orders for different beneficiaries (e.g. spouse, child, sibling) without relying on a shopping cart or multi-passenger bundle.

---

## 2. Actors

- **Customer (Account Owner / Family Head)**
- **Travelers Domain**
- **Wallet and Financial Ledger Domain**
- **Orders and Purchasing Domain**
- **Fulfillment Domain**

---

## 3. Preconditions

1. Customer has saved three distinct traveler profiles in their vault:
   - Traveler A: **Ahmed** (Passport: `P01234567`)
   - Traveler B: **Sarah** (Passport: `P02345678`)
   - Traveler C: **Mohammed** (Passport: `P03456789`)
2. Customer has a confirmed wallet balance of `75,000.00 SDG` (`7,500,000` minor units).
3. The desired service ("UAE 30-Day Tourist Visa") has an authoritative price of `25,000.00 SDG` (`2,500,000` minor units).

---

## 4. Trigger

Customer decides to purchase UAE tourist visas for all three family members.

---

## 5. Complete Happy-Path Sequence

### Order 1: Ahmed
1. **Customer navigates to UAE Visa and clicks "Order Now"**.
2. **Customer selects Ahmed** from the traveler list and proceeds to the application form.
3. **Customer completes application form** with Ahmed’s photo and details.
4. **Customer clicks "Submit Order"**:
   - System executes atomic purchase transaction.
   - Debits `25,000.00 SDG` from wallet; remaining balance becomes `50,000.00 SDG`.
   - Inserts Commercial Order `ORD-202609-1001` (Beneficiary: Ahmed, Price: `25,000.00 SDG`).
   - Inserts Service Execution `exec-1001` (Status: `received`).
5. **Customer views confirmation** and clicks **"Order Another Service"**.

---

### Order 2: Sarah
6. **Customer navigates to UAE Visa and clicks "Order Now"**.
7. **Customer selects Sarah** from the traveler list and proceeds to the application form.
8. **Customer completes application form** with Sarah’s personal photo and details.
9. **Customer clicks "Submit Order"**:
   - System executes atomic purchase transaction.
   - Debits `25,000.00 SDG` from wallet; remaining balance becomes `25,000.00 SDG`.
   - Inserts Commercial Order `ORD-202609-1002` (Beneficiary: Sarah, Price: `25,000.00 SDG`).
   - Inserts Service Execution `exec-1002` (Status: `received`).
10. **Customer views confirmation** and clicks **"Order Another Service"**.

---

### Order 3: Mohammed
11. **Customer navigates to UAE Visa and clicks "Order Now"**.
12. **Customer selects Mohammed** from the traveler list and proceeds to the application form.
13. **Customer completes application form** with Mohammed’s personal photo and details.
14. **Customer clicks "Submit Order"**:
   - System executes atomic purchase transaction.
   - Debits `25,000.00 SDG` from wallet; remaining balance becomes `0.00 SDG`.
   - Inserts Commercial Order `ORD-202609-1003` (Beneficiary: Mohammed, Price: `25,000.00 SDG`).
   - Inserts Service Execution `exec-1003` (Status: `received`).

---

### Verification: Independent Orders in Dashboard
15. **Customer navigates to "My Orders"**:
    - Browser displays three completely independent order cards:
      - Card 1: `ORD-202609-1001` — UAE Visa — **Ahmed** — `25,000.00 SDG` — Status: `Received`
      - Card 2: `ORD-202609-1002` — UAE Visa — **Sarah** — `25,000.00 SDG` — Status: `Received`
      - Card 3: `ORD-202609-1003` — UAE Visa — **Mohammed** — `25,000.00 SDG` — Status: `Received`
    - Wallet Balance displays: **`0.00 SDG`**.
16. **Independent Lifecycle Execution**:
    - Operational fulfillment for each family member proceeds independently:
      - Ahmed's visa may enter `processing`.
      - Sarah's visa may enter `action_required` (e.g. requesting a clearer photo).
      - Mohammed's visa may reach `completed`.
    - An action or delay affecting Sarah never blocks or alters Ahmed's or Mohammed's fulfillment.

---

## 6. Alternate Paths

### Path 7A: Different Services for Different Family Members
1. Customer purchases UAE Visa for Ahmed (`25,000 SDG`).
2. Customer purchases Umrah Permit for Sarah (`40,000 SDG`).
3. Each order uses the distinct service's dynamic form schema, pricing, and independent fulfillment SOP.

---

## 7. Failure Paths

### Path 7F-1: Attempting 4th Order with Exhausted Balance
1. Customer attempts to place a 4th order for another family member.
2. System detects `current_balance_minor = 0 < 2,500,000`.
3. System rejects submission with HTTP 422 `wallet.insufficient_balance`:
   `"Your wallet balance is insufficient to complete this order. Please top up your wallet and try again."`
4. Customer is prompted to initiate a new top-up request.

---

## 8. Recovery Behavior

- If one family member's visa is delayed or cancelled, the other family members' orders remain active and unaffected.

---

## 9. Final Observable State

1. **Database State**:
   - `orders`: Exactly 3 distinct records created, each with its own debit transaction reference and traveler snapshot.
   - `service_executions`: Exactly 3 distinct operational cases created.
   - `wallets`: Balance decremented by `75,000.00 SDG` (final balance `0.00 SDG`).
   - `wallet_ledger_entries`: 3 distinct debit entries recorded.
2. **User State**:
   - Customer monitors three distinct fulfillment timelines.

---

## 10. Cross-Domain Dependencies

- **Travelers**: Provides independent beneficiary profiles.
- **Wallet**: Deducts funds sequentially per purchase.
- **Orders & Purchasing**: Enforces "one traveler per order" constraint.
- **Fulfillment**: Manages isolated fulfillment cases.

---

## 11. External-System Interactions

- None. Entirely internal database transaction sequence.
