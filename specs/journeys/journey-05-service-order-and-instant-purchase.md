# Journey 05: Service Order and Instant Atomic Purchase

## 1. Goal

An authenticated customer purchases a travel service for a saved traveler by completing the dynamic application form, attaching verified documents, and executing an atomic checkout that debits the wallet balance, generates an immutable commercial order record, and initializes an operational service execution case.

---

## 2. Actors

- **Customer (Account Owner)**
- **Service Catalog Domain**
- **Application Forms Domain**
- **Travelers Domain**
- **Documents Domain**
- **Wallet and Financial Ledger Domain**
- **Orders and Purchasing Domain**
- **Fulfillment Domain**
- **Notifications Domain**
- **Audit Domain**

---

## 3. Preconditions

1. Customer is authenticated and possesses an active wallet with balance `50,000.00 SDG` (`5,000,000` minor units).
2. Service "UAE 30-Day Tourist Visa" is `active` with an authoritative price of `25,000.00 SDG` (`2,500,000` minor units).
3. Published Form Version #4 is active for the service.
4. A published fulfillment-policy version is active for the service.
5. Traveler profile "Ahmed Mohammed Osman" (Passport: `P01234567`) is saved in the customer's vault.

---

## 4. Trigger

Customer clicks **"Order Now"** on the service details page for "UAE 30-Day Tourist Visa".

---

## 5. Complete Happy-Path Sequence

1. **User initiates purchase flow**:
   - Customer clicks **"Order Now"**.
   - System performs preliminary balance sanity check: verifies `50,000 >= 25,000 SDG`.
   - UI proceeds to Step 1: **Select Traveler**.
2. **User selects traveler profile**:
   - System displays customer's saved travelers:
     - Card A: `Ahmed Mohammed Osman (Passport: P01234567)`
   - Customer selects Ahmed and clicks **"Continue to Application"**.
3. **System loads published form schema**:
   - System fetches active `FormVersion` #4 for UAE Visa.
   - UI renders the dynamic form fields:
     - Field 1 (`short_text`): `"Mother's Full Name"` (Required).
     - Field 2 (`image_upload`): `"Personal Photo on White Background"` (Required).
4. **User fills form and uploads documents**:
   - Mother's Name: `"Fatima Hassan"`.
   - User clicks upload on personal photo, selects `photo.jpg`.
   - Documents domain verifies JPEG magic bytes, ensures size < 10MB, saves to private storage, and returns `document_id = "doc-photo-99"`.
5. **User reviews order summary**:
   - UI renders checkout summary:
     - Service: `"UAE 30-Day Tourist Visa"`
     - Traveler: `"Ahmed Mohammed Osman (P01234567)"`
     - Price: `"25,000.00 SDG"`
     - Payment Method: `"Rehla Wallet (Current Balance: 50,000.00 SDG)"`
     - Remaining Balance after Purchase: `"25,000.00 SDG"`
6. **User clicks "Submit Order"**:
   - Browser generates a unique idempotency key: `Idempotency-Key: "uuid-order-8811"`.
   - Client sends `POST /api/v1/order-submissions` with opaque service/traveler IDs, accepted price `2500000`, accepted price-version ID, form-version ID, and answers.
7. **Purchasing orchestrator executes atomic transaction**:
   - Begins PostgreSQL transaction.
   - Inserts or locks the unique customer/key purchase attempt, closing the concurrent missing-row race.
   - Locks and verifies in order: service/current price version, published form pointer, published fulfillment-policy pointer, traveler, sorted documents, then wallet.
   - Re-verifies exact accepted price/version, validates answers against captured Form #4, captures the policy version, confirms ownership/clean documents, and checks balance.
   - Allocates the Order ID before creating its debit so the ledger reference is complete and immutable.
   - Calls `Wallet\Contracts\DebitWallet`:
     - Decrements `current_balance_minor` to `2,500,000` (`25,000.00 SDG`).
     - Inserts `wallet_ledger_entries` record:
       - `entry_type = 'debit'`
       - `amount_minor = 2500000`
       - `running_balance_minor = 2500000`
       - `reference_type = 'order_purchase'`
       - `reference_id = [order_id]`
       - `description = "Purchase of UAE 30-Day Tourist Visa for Ahmed Mohammed Osman"`
   - Inserts permanent record in `orders` table:
     - `order_reference = "ORD-202609-1001"`
     - `account_id = current_user_id`
     - `service_id = 1`
     - `price_paid_minor = 2500000`
     - `currency = 'SDG'`
     - `status = 'paid'`
     - `service_snapshot = { name: "UAE 30-Day Tourist Visa", price: 2500000, ... }`
     - `traveler_snapshot = { name: "Ahmed Mohammed Osman", passport: "P01234567", dob: "1990-08-20" }`
   - Calls `Fulfillment\Contracts\CreateExecution`:
     - Inserts `service_executions` record linked to order #1001 with initial status `received`.
     - Appends initial status changelog entry (`received`).
   - Appends purchase record to `audit_entries`.
   - Creates the in-app confirmation and enqueues `OrderSubmitted` for enabled external channels.
   - Stores completed order response in `idempotency_keys`.
   - Commits database transaction.
8. **Client receives confirmation**:
   - Server returns HTTP 201 Created with order reference `"ORD-202609-1001"` and execution ID.
   - Browser redirects to `/account/orders/ORD-202609-1001`.
   - Success screen renders:
     - Order Reference: **`ORD-202609-1001`**
     - Service: **UAE 30-Day Tourist Visa**
     - Beneficiary: **Ahmed Mohammed Osman**
     - Payment: **Paid (25,000.00 SDG)**
     - Execution Status: **Order Received (In Queue)**
   - Header wallet balance immediately updates to **`25,000.00 SDG`**.

---

## 6. Alternate Paths

### Path 5A: Customer Adds New Traveler During Checkout
1. At Step 1 (Select Traveler), user clicks **"+ Add New Traveler"**.
2. Modal opens; user enters traveler details and clicks "Save".
3. System normalizes passport, verifies global uniqueness, and saves traveler.
4. User selects newly created traveler and seamlessly proceeds to Step 2 (Form).

---

## 7. Failure Paths

### Path 5F-1: Insufficient Balance at Moment of Final Click
1. Customer had 50,000 SDG when opening the form, but placed another order in a separate browser tab, dropping balance to 10,000 SDG.
2. Customer clicks "Submit Order" for the 25,000 SDG visa.
3. Transaction locks wallet and evaluates `10,000 < 25,000 SDG`.
4. Transaction rolls back immediately.
5. Server returns HTTP 422 with code `wallet.insufficient_balance`:
   `"Your wallet balance is insufficient to complete this order. Please top up your wallet and try again."`
6. UI displays error with direct link: **"Top Up Wallet"**.

### Path 5F-2: Price Changed While Filling Form
1. Customer opens form when price is 25,000 SDG.
2. Platform administrator updates service price to 30,000 SDG.
3. Customer submits order with `accepted_price_minor = 2500000`.
4. Transaction evaluates authoritative catalog price (`3,000,000 != 2,500,000`).
5. Transaction aborts before debiting wallet.
6. Server returns HTTP 409 Conflict with code `service.price_changed`:
   `"The service price has been updated since opening the order. Please review the new price before proceeding."`
7. UI updates order summary to 30,000 SDG and prompts customer to re-confirm.

### Path 5F-3: Rapid Network Double Click (Idempotency in Action)
1. Customer clicks "Submit Order" twice in rapid succession.
2. Both requests arrive with `Idempotency-Key: "uuid-order-8811"`.
3. Request 1 acquires lock, debits wallet, creates order, and commits.
4. Request 2 acquires lock, finds cached result for `uuid-order-8811`, verifies identical payload fingerprint, and returns the existing order result (HTTP 200 OK).
5. Wallet is debited exactly once; exactly one order is created.

---

## 8. Recovery Behavior

- If balance is insufficient, the user is redirected to the Top-Up flow. Upon top-up approval, they can return and submit their order.
- If price changes, the user reviews the new price and re-submits with updated accepted price.

---

## 9. Final Observable State

1. **Database State**:
   - `wallets`: `balance_minor` decremented by `2500000` to `2500000` (25,000.00 SDG).
   - `wallet_ledger_entries`: 1 new debit entry referencing `ORD-202609-1001`.
   - `orders`: 1 permanent record with status `paid` and frozen snapshots.
   - `service_executions`: 1 new record with status `received`.
   - `outbox_messages`: 1 `OrderSubmitted` event queued.
2. **User State**:
   - Customer owns order `ORD-202609-1001` and monitors operational fulfillment.

---

## 10. Cross-Domain Dependencies

- **Purchasing**: Transaction orchestrator.
- **Catalog**: Authoritative price verification.
- **Forms**: Schema validation.
- **Travelers**: Ownership validation and snapshot creation.
- **Documents**: Attachment of uploaded photo.
- **Wallet**: Atomic debit and ledger recording.
- **Fulfillment**: Execution case instantiation.
- **Notifications**: Outbox event creation.
- **Audit**: Immutable audit entry.

---

## 11. External-System Interactions

- None during checkout. Transaction is strictly local to PostgreSQL.
