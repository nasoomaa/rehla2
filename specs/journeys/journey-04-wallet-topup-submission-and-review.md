# Journey 04: Wallet Top-Up Submission and Administrative Review

## 1. Goal

An authenticated customer funds their platform wallet by transferring money out-of-band to a platform bank account, submitting the transaction reference number and receipt proof, which operations staff review and approve, atomically crediting the customer's wallet balance.

---

## 2. Actors

- **Customer (Account Owner)**
- **Operations Reviewer (Staff)**
- **Top-Ups and Bank Transfers Domain**
- **Documents Domain**
- **Wallet and Financial Ledger Domain**
- **Notifications Domain**
- **Audit Domain**
- **External Commercial Bank Application (e.g. Bank of Khartoum / Bankak)**

---

## 3. Preconditions

1. Customer is authenticated and has an active wallet (`balance = 0.00 SDG`).
2. At least one platform bank account (e.g. Bank of Khartoum) is active in platform settings.
3. Reviewer staff has an active session with `topups.review` ability and verified TOTP MFA.

---

## 4. Trigger

Customer navigates to `/account/wallet` and clicks **"Top Up Balance"**.

---

## 5. Complete Happy-Path Sequence

### Phase 1: Customer Submission
1. **User opens Top-Up page**:
   - Browser requests `GET /account/wallet/top-up`.
   - System renders active platform bank accounts (Bank of Khartoum, Beneficiary Name: "Rehla Travel Services", Account Number: "1234567").
   - System displays the current minimum setting; initially: `"Minimum top-up amount: 5,000.00 SDG"`.
2. **User executes external transfer**:
   - User opens their mobile banking application (Bankak).
   - User transfers `50,000.00 SDG` to the platform's Bank of Khartoum account.
   - Bankak displays payment confirmation with transaction reference `"TXN-987654321"`.
   - User saves the electronic receipt screenshot to their device.
3. **User inputs transfer details on Rehla**:
   - User selects **Bank of Khartoum**.
   - User inputs Amount: `50,000` SDG.
   - User inputs Transaction Reference Number: `" TXN-987654321 "`.
4. **User uploads receipt file**:
   - User selects the saved screenshot (`receipt.png`).
   - Browser uploads file to `POST /api/v1/uploads` with classification `bank_receipt`.
   - Documents returns `document_id = "doc-rec-101"` in `pending_scan`; the browser polls the authenticated upload-status route until it becomes `clean`.
5. **User submits top-up request**:
   - User clicks **"Submit for Review"**.
   - Browser sends `POST /api/v1/top-ups` with bank account ID, amount `5000000` minor units, reference, and `document_id`.
6. **System validates and stores top-up request**:
   - Locks and snapshots the current minimum (`500000` initially), then verifies the submitted amount meets it.
   - Normalizes transaction reference to `"TXN987654321"`.
   - Checks database uniqueness constraint: asserts no top-up exists for `(bank_account_id = 2, normalized_reference = "TXN987654321")`.
   - Marks document `doc-rec-101` as `attached`.
   - Inserts `top_up_requests` record with status `under_review`.
7. **System displays confirmation to customer**:
   - UI redirects to `/account/wallet`.
   - Top-Up status card displays:
     - Reference: `"TXN-987654321"`
     - Amount: `"50,000.00 SDG"`
     - Status badge: `"Under Review"`
     - Current Wallet Balance: still `"0.00 SDG"`.

---

### Phase 2: Administrative Verification and Credit
8. **Reviewer opens administrative review queue**:
   - Staff logs into Admin Panel with TOTP MFA.
   - Staff navigates to **Top-Up Requests**.
   - System lists pending requests sorted by submission time ascending.
9. **Reviewer opens request details**:
   - Reviewer clicks request #401.
   - System renders customer name, bank name, claimed amount (`50,000.00 SDG`), reference (`TXN-987654321`), and submission timestamp.
   - Reviewer clicks to inspect receipt: Documents domain streams authorized image with security headers.
10. **Reviewer reconciles with internal bank records**:
    - Reviewer checks platform's Bank of Khartoum commercial statement.
    - Confirms that `50,000.00 SDG` was received under reference `TXN987654321`.
11. **Reviewer clicks "Approve Transfer"**:
    - Reviewer confirms approval in confirmation modal.
12. **System executes atomic approval transaction**:
    - Begins PostgreSQL database transaction.
    - Locks `top_up_requests` row #401 (`SELECT FOR UPDATE`).
    - Verifies current status is `under_review`.
    - Locks customer's `wallets` row (`SELECT FOR UPDATE`).
    - Calls `Wallet\Contracts\CreditWallet`:
      - Increases `current_balance_minor` from `0` to `5000000` (`50,000.00 SDG`).
      - Inserts `wallet_ledger_entries` row:
        - `entry_type = 'credit'`
        - `amount_minor = 5000000`
        - `running_balance_minor = 5000000`
        - `reference_type = 'top_up_approval'`
        - `reference_id = 401`
        - `description = "Wallet Top-Up via Bank of Khartoum (Ref: TXN-987654321)"`
    - Updates `top_up_requests` #401:
      - `status = 'approved'`
      - `reviewer_id = staff_user_id`
      - `decision_at = NOW()`
    - Appends record to `audit_entries` with reviewer ID, old state (`under_review`), and new state (`approved`).
    - Creates the in-app notification and enqueues `TopUpApproved` for enabled external channels.
    - Commits database transaction.
13. **Customer receives balance credit and alert**:
    - The atomically created in-app notification is visible immediately:
      `"Your wallet top-up of 50,000.00 SDG via Bank of Khartoum has been approved."`
    - Customer refreshes dashboard or receives real-time update:
      - Wallet Balance: **`50,000.00 SDG`**
      - Top-Up Request #401 status: **`Approved`**
      - Transaction ledger displays entry: `+ 50,000.00 SDG`.

---

## 6. Alternate Paths

### Path 4A: Administrative Rejection
1. Reviewer checks commercial bank portal and finds no matching transaction for `TXN-987654321`.
2. Reviewer clicks **"Reject Transfer"**.
3. Modal requires rejection reason: Reviewer selects `"Funds not received in platform account"`.
4. System executes atomic rejection transaction:
   - Updates `top_up_requests` #401:
     - `status = 'rejected'`
     - `rejection_reason = "Funds not received in platform account"`
     - `reviewer_id = staff_user_id`
     - `decision_at = NOW()`
   - Wallet balance is NOT modified (remains `0.00 SDG`).
   - Ledger is untouched (no debit or credit recorded).
   - Appends to `audit_entries`.
   - Creates the in-app rejection notification atomically and enqueues external-channel delivery when enabled.
   - Commits transaction.
5. Customer receives alert: `"Your top-up request of 50,000.00 SDG was rejected. Reason: Funds not received in platform account."`

---

## 7. Failure Paths

### Path 4F-1: Amount Below Minimum Threshold
1. Customer enters amount `4,000.00 SDG` (`400,000` minor units).
2. Validation check detects `amount_minor < 500,000`.
3. System halts with HTTP 422 `top_up.below_minimum`, includes current `minimum_amount_minor = 500000`, and displays `"The minimum top-up amount is 5,000 SDG."` for this initial-setting example.

### Path 4F-2: Reused Bank Transaction Reference Number
1. Customer (or another user) enters transaction reference `"TXN-987654321"` for Bank of Khartoum that was previously submitted.
2. System normalizes string to `"TXN987654321"`.
3. Database query detects existing row for `(bank_account_id = 2, normalized_reference = 'TXN987654321')`.
4. System rejects submission with HTTP 422 `top_up.reference_used`:
   `"This bank transaction reference number has already been used."`

### Path 4F-3: Concurrent Approval Attempts (Double-Credit Prevention)
1. Due to browser lag or two reviewers acting simultaneously, two `ApproveTopUp` commands arrive for request #401.
2. First command locks row, transitions status to `approved`, credits wallet, and commits.
3. Second command acquires row lock, inspects status, sees `status == 'approved'`.
4. System detects request is already approved, bypasses `CreditWallet`, and returns existing approval outcome without double-crediting.

---

## 8. Recovery Behavior

- If a receipt is unreadable before a terminal decision, staff keeps the request `under_review`; the customer uploads a clean replacement and replaces the receipt on that same request. The bank/reference pair remains reserved and the replacement is audited.
- After approval or rejection, receipt replacement is forbidden and the reference cannot be reused in a new request.
- On reference collision due to typographical error, the customer re-enters the exact reference from their banking app.

---

## 9. Final Observable State

1. **Database State (on Approval)**:
   - `top_up_requests`: status `approved`, `reviewer_id` set, `decision_at` set.
   - `wallets`: `current_balance_minor` incremented to `5000000` (50,000.00 SDG).
   - `wallet_ledger_entries`: 1 new credit entry with running balance `5000000`.
   - `audit_entries`: 1 immutable log entry.
   - `in_app_notifications`: 1 unread notification delivered to user.
2. **User State**:
   - Customer possesses `50,000.00 SDG` available for purchasing services.

---

## 10. Cross-Domain Dependencies

- **Top-Ups**: Manages requests and bank accounts.
- **Documents**: Stores and verifies transfer receipt.
- **Wallet**: Credits balance and appends immutable ledger entry.
- **Notifications**: Delivers approval/rejection messages.
- **Audit**: Records staff review decisions.

---

## 11. External-System Interactions

- **Commercial Bank Mobile Application (Bankak)**: External out-of-band payment channel.
