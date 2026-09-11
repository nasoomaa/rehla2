# Product Overview: Rehla (رحلة)

## 1. Product Purpose

Rehla is an integrated digital travel services platform specifically tailored for Sudanese users. The platform unifies the entire lifecycle of discovering, applying for, funding, purchasing, and tracking travel services (such as visas, Umrah packages, tourist visits, and related travel offerings to Gulf countries including Saudi Arabia, UAE, Qatar, and Bahrain).

Historically, these workflows have been fragmented across disparate chat conversations (WhatsApp), informal phone calls, manual bank transfers, and unorganized photo exchanges, leading to lost records, disputed payments, repetitive data entry, and zero tracking transparency. Rehla eliminates these pain points by providing an organized, trackable, and verifiable single source of truth.

---

## 2. Target Users and Actors

### 2.1 Customer (Account Owner)
- Primary audience: Sudanese citizens inside Sudan or residing in diaspora.
- Acts as the financial principal: owns the wallet, initiates bank transfer top-up requests, and pays for orders.
- May purchase services for themselves or for family members and dependents.

### 2.2 Traveler
- The individual beneficiary of a travel service (e.g., the applicant on a visa or Umrah permit).
- Identified by name, date of birth, gender, and a globally unique Sudanese passport number.
- Does not require platform authentication or login credentials; managed entirely by the Account Owner.

### 2.3 Operations Staff (Reviewer / Fulfillment Officer)
- Internal operational team members responsible for:
  - Reviewing submitted bank transfer receipts and verifying funds.
  - Reviewing traveler application forms and attached documents.
  - Transitioning service execution statuses through standard operating procedures (SOP).
  - Requesting additional documentation or clarifications from customers.
  - Attaching approved visas or official permits upon completion.

### 2.4 Platform Administrator
- Super-privileged operational staff responsible for:
  - Configuring platform bank accounts.
  - Creating and publishing travel services, pricing, and requirement descriptions.
  - Designing and publishing dynamic application form schemas.
  - Assigning roles and granular abilities to staff members.
  - Reviewing the immutable operational audit log.

### 2.5 External Systems
- **Commercial Banks (Sudanese Banking Network)**: Bank of Khartoum (Bankak), Omdurman National Bank (O-Cash), Faisal Islamic Bank (Fawry), etc. Customers transfer funds externally and upload transfer reference receipts.
- **WhatsApp**: Serves strictly as an external messaging channel for informal inquiries via pre-filled deep links (zero platform transactional side-effects).
- **Communication Channels (Outbox Delivery)**: External notification delivery providers (e.g., SMS gateways or mailers) consuming the platform outbox.

---

## 3. Primary Use Cases

1. **Service Discovery & Assessment**: Users browse published travel services, inspect prerequisites, validity conditions, expected turnaround times, and current authoritative prices in Sudanese Pounds (SDG).
2. **Pre-Purchase Inquiry**: Users can launch WhatsApp with pre-filled service inquiry details to clarify questions without creating orders or debiting funds.
3. **Traveler Profile Vault**: Users save and maintain complete traveler profiles (family members) with validated passport details to eliminate redundant data entry on future orders.
4. **Wallet Top-Up via Bank Transfer**: Users specify an amount (minimum 5,000 SDG), select a platform bank account, perform an out-of-band transfer, and upload the transaction reference number and receipt for manual administrative verification.
5. **Instant One-Click Order & Atomic Debit**: Once wallet funds are confirmed, users select a service and traveler, complete the dynamic application form, upload required private documents, and submit the order. The system atomically debits the wallet, creates a permanent commercial order record, and generates an independent service execution record.
6. **Execution Tracking & Collaborative Document Exchange**: Customers track the progress of each order in real time. If staff mark an order as "Action Required", the customer uploads the requested documentation or corrections, automatically resuming fulfillment.
7. **Operational Administrative Control**: Operations staff verify top-ups, validate documents, progress execution lifecycles, and maintain an immutable audit trail.

---

## 4. Product Boundaries and Core Entities

Rehla strictly enforces separation among six core business concepts:

```text
┌─────────────┐       ┌─────────────┐
│   Service   │       │  Traveler   │
│ (What is    │       │ (Who is the │
│  purchased) │       │  beneficiary│
└──────┬──────┘       └──────┬──────┘
       │                     │
       └──────────┬──────────┘
                  ▼
         ┌─────────────────┐       ┌─────────────────┐
         │Commercial Order │◄──────┤     Wallet      │
         │ (Permanent legal│       │ (Pre-funded SDG │
         │  purchase record│       │  ledger account)│
         └────────┬────────┘       └────────▲────────┘
                  │                         │
                  ▼                         │
         ┌─────────────────┐       ┌────────┴────────┐
         │Service Execution│       │ Top-Up Request  │
         │ (Operational    │       │ (Proof of bank  │
         │  fulfillment)   │       │  transfer)      │
         └─────────────────┘       └─────────────────┘
```

1. **Service**: The catalog item with marketing descriptions, prerequisites, and dynamic form versioning.
2. **Traveler**: The saved identity profile of the traveling person.
3. **Wallet**: The balance container holding funds in Sudanese Pounds (SDG); debited on purchase.
4. **Top-Up Request**: The funding event where bank receipts are audited and approved to credit the wallet.
5. **Commercial Order**: The immutable historical purchase contract capturing price paid, currency, debit transaction reference, and immutable snapshots of service and traveler metadata.
6. **Service Execution**: The operational workflow tracking application processing, document verification, customer action requests, and final permit delivery.

**Critical Principle**: Financial status is NEVER conflated with operational fulfillment status. A commercial order is permanently "Paid" at creation, while the associated service execution independently transitions through operational lifecycle states (`Received`, `Under Review`, `In Processing`, `Action Required`, `Action Received`, `Completed`, `Cancelled`).

---

## 5. Major Capabilities

1. **Bilingual Presentation**: English (`en`) as primary display language, with full Arabic (`ar`) support including Right-to-Left (RTL) layout.
2. **Dynamic Form Versioning**: Service forms are versioned. Publishing a new version immediately applies to new submissions while existing orders retain their historical form schema and responses.
3. **Strict Document Privacy Pipeline**: Sensitive documents (passports, national IDs, bank receipts) are stored on private disks, quarantined upon upload, verified for magic bytes and malware, and only accessible via authenticated, short-lived signed URLs.
4. **Immutable Append-Only Wallet Ledger**: Balances are calculated from append-only credit and debit entries using integer minor units in SDG. Historical ledger rows are never updated or deleted. This is a wallet ledger contract, not a claim that Phase 1 implements a general accounting double-entry system.
5. **Concurrency-Safe Purchasing**: Submission uses scoped idempotency keys, re-verifies wallet balance and authoritative prices inside a serialized database transaction, and guarantees zero partial state.
6. **Comprehensive Metric Analytics**: 12 core business metrics computed over `Africa/Khartoum` timezone cohorts to track turnaround times, approval rates, and retention.

---

## 6. Terminology and Ubiquitous Language

- **Account Owner (Customer)**: The authenticated entity possessing a platform account, managing a wallet, and submitting orders.
- **Traveler**: A person for whom travel services are ordered. Belongs to an Account Owner.
- **Normalized Passport Number**: A passport number with Unicode whitespace and hyphens removed and ASCII letters uppercased. Remaining punctuation is rejected; the result must match `^[A-Z0-9]{6,12}$` and is globally unique.
- **Wallet**: An Account Owner's financial balance represented as integer minor units of SDG (scale 100).
- **Minor Unit (`amount_minor`)**: 1 SDG = 100 minor units (piastres). 5,000 SDG = 500,000 minor units.
- **Top-Up Request**: A formal submission by a customer claiming external bank transfer of funds, verified by staff before crediting the wallet.
- **Transaction Reference Number**: The alphanumeric identifier generated by the customer's commercial bank for an electronic funds transfer. Must be unique per platform bank account.
- **Commercial Order**: The permanent legal record of a purchase. Cannot be modified or deleted.
- **Service Snapshot**: The frozen copy of service metadata (name, price, currency) captured at the exact moment of order submission.
- **Traveler Snapshot**: The frozen copy of traveler details (full name, date of birth, gender, passport number, issue/expiry dates) captured at the exact moment of order submission.
- **Service Execution**: The operational case record tracking the fulfillment of a purchased service.
- **Customer Action Request**: A formal operational request from staff requiring the customer to submit additional documents or corrected information.
- **Published Form Version**: An immutable JSON schema defining the fields, validation rules, and document requirements for a service at a point in time.
- **Transactional Outbox**: An append-only table within the primary database capturing domain events atomically with business state changes for reliable at-least-once delivery.

---

## 7. Assumptions

1. **Currency**: All transactions, top-ups, orders, and balances operate strictly in Sudanese Pounds (SDG).
2. **Sudanese Scope**: All travelers in Phase 1 are Sudanese citizens holding Sudanese passports; nationality and passport-issuing country fields are omitted.
3. **Out-of-Band Banking**: Bank transfers occur entirely outside the platform via commercial bank applications (e.g., Bankak, O-Cash, Fawry). Rehla does not integrate with closed proprietary banking APIs in Phase 1; verification is performed by authorized operations staff.
4. **Timezone**: All business reporting cohorts, operational SLAs, and customer-facing timestamps are anchored to `Africa/Khartoum` (UTC+2), while internal storage is strictly UTC.

---

## 8. Constraints

1. **No Float Arithmetic**: Floating-point numbers are strictly forbidden for currency, prices, amounts, or balances. All monetary values are represented as integer minor units (`amount_minor`).
2. **Global Passport Uniqueness**: A passport number can only be registered once across the entire system. No two travelers (even under different account owners) may share the same normalized passport number.
3. **One Traveler per Order**: Every order and service execution represents exactly one service for exactly one traveler. Group orders are decomposed into multiple individual orders.
4. **No Order Drafts**: Abandoning an application form creates no database draft, reserves no inventory, and holds no balance. Submissions are atomic.
5. **No Direct Transfer-to-Order Payment**: Services cannot be purchased directly via bank transfer. Bank transfers only fund the wallet; orders are debited strictly from the wallet.

---

## 9. Invariants

1. **Balance Non-Negativity**: A wallet balance can never become negative under any circumstance (`balance >= 0`).
2. **Atomic Purchase Consistency**: An order submission either completely succeeds (debit created, order created, execution created, outbox event generated) or completely fails with no state change.
3. **Single Credit per Top-Up**: A single bank transfer review can never credit a wallet more than once, regardless of concurrent or repeated approval attempts.
4. **Bank Reference Uniqueness**: A transaction reference number cannot be reused for the same platform bank account.
5. **Historical Immutability**:
   - Ledger entries cannot be updated or deleted.
   - Commercial orders and their metadata snapshots cannot be updated or deleted.
   - Published form versions cannot be updated or deleted.
   - Audit log entries cannot be updated or deleted.
6. **Document Privacy**: Private documents (passports, bank receipts, supporting files) are never served through permanent public URLs.

---

## 10. Security and Authorization Model

1. **Authentication Channels**:
   - **Customer Web**: Standard HTTP session cookies with strict CSRF protection.
   - **Customer REST API**: Personal Access Tokens (Bearer tokens) via Sanctum; scoped rate limits.
   - **Admin Operations**: Independent session cookie with isolated authentication guard; mandatory Time-based One-Time Password (TOTP) Multi-Factor Authentication (MFA) for high-privilege operational capabilities.
2. **Authorization Enforcement**:
   - **Deny by Default**: Access is blocked unless an explicit ability or policy rule permits it.
   - **Strict Ownership Isolation**: Customers may only view or operate on travelers, wallets, orders, top-ups, and documents belonging to their authenticated account (`account_id`).
   - **Granular Staff Abilities**: Operational abilities are segregated (e.g., `services.manage`, `topups.review`, `executions.transition`, `audit.view`). No operational staff member is granted broad unrestricted access by default.
3. **Document Security**: Uploaded files undergo extension validation, MIME type detection via magic bytes, image decode validation, and malware scanning before being marked `clean`. Quarantined or rejected files cannot be attached to orders or top-up requests.

---

## 11. Important Non-Functional Requirements

1. **Auditability**: Every financial movement and critical operational decision (top-up approval/rejection, status transition, document rejection, role modification) must record an immutable audit entry with actor identity, timestamp, decision rationale, and correlation ID.
2. **Idempotency and Deduplication**: Checkout uses a customer-scoped idempotency key. Top-up submission deduplicates the platform-bank/reference pair. Replayed terminal review decisions return the recorded outcome without a second financial effect.
3. **Performance**: Order checkout transactions must execute within 500ms under standard database conditions. Public catalog views must render within 200ms.
4. **Availability & Recovery**: Target Recovery Point Objective (RPO) is 15 minutes; target Recovery Time Objective (RTO) is 4 hours. Automated point-in-time recovery coordinates database state and private object storage blobs.

---

## 12. Explicit Out-of-Scope Behavior (Phase 1)

The following capabilities are explicitly excluded from Phase 1:
- Shopping cart, item quantities, or multi-item bundles.
- Physical inventory, stock tracking, or physical shipping.
- Multi-currency transactions (strictly SDG).
- Multi-nationality support (strictly Sudanese passports).
- Direct bank transfer payment for an order (without wallet top-up).
- Split payments (e.g., partial wallet + partial transfer).
- Incomplete order draft persistence.
- Group bookings / multiple travelers in a single order.
- Third-party travel agency or airline marketplace.
- Automated wallet effects upon execution cancellation. Refund and compensation behavior is unspecified until an approved product policy defines it.
