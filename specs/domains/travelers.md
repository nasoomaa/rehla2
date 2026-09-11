# Domain: Travelers

## 1. Purpose

The Travelers domain manages beneficiary traveler profiles associated with customer accounts. It encapsulates traveler biographical data, passport details, strict normalization and validation, ownership scoping, and global passport uniqueness invariants across the entire platform.

---

## 2. Actors

- **Customer (Account Owner)**: Creates, views, updates, and selects traveler profiles within their personal account vault.
- **Operations Staff**: Views traveler details associated with orders and service executions; views sensitive passport numbers only when granted the `travelers.view_sensitive` ability.
- **System**: Enforces global passport uniqueness, normalizes passport strings, and generates immutable traveler snapshots upon order creation.

---

## 3. Concepts

- **Traveler Profile**: A persistent record belonging to an Account Owner representing an individual beneficiary. Contains:
  - Full Name (one required Unicode value as written in official records).
  - Date of Birth.
  - Gender (`male`, `female`).
  - Raw Passport Number (as entered by user).
  - Normalized Passport Number (computed authoritative representation).
  - Passport Issue Date.
  - Passport Expiry Date.
- **Normalized Passport Number**: Remove Unicode whitespace and hyphens, convert ASCII letters to uppercase, reject any remaining punctuation, then validate against `^[A-Z0-9]{6,12}$`.
- **Traveler Snapshot**: An immutable, serialized snapshot of the traveler’s exact biographical data at the time of order submission, stored permanently in the Commercial Order record.

---

## 4. Invariants

1. **Account Ownership**: Every traveler profile must belong to exactly one authenticated Customer Account (`account_id`).
2. **Global Passport Uniqueness**: The `normalized_passport_number` must be unique across the entire platform. No two travelers—whether belonging to the same account or different accounts—may share the same normalized passport number.
3. **Passport Mandatory**: A traveler profile cannot exist without a valid normalized passport number.
4. **Sudanese Scope (No Country / Nationality Fields)**: In Phase 1, all travelers are assumed to be Sudanese citizens holding Sudanese passports; nationality and issuing country fields are strictly omitted.
5. **Historical Decoupling**: Updating a traveler’s profile (e.g. following passport renewal) updates future usage only. All past orders and active service executions retain their original historical snapshot unchanged.
6. **Privacy on Collision**: When a customer attempts to add or update a passport number already registered to another account, the system must reject the request without revealing whether the passport belongs to another customer or any information about that account.

---

## 5. State Model

Traveler profiles are persistent biographical entities. They do not maintain complex operational state machines:
```text
[Created] ──► Active ──► [Updated for future orders]
```
- Profiles are queried and referenced during order checkout.
- Deleting a traveler profile with active or past orders is prohibited (or soft-deactivated) to maintain relational integrity and auditability.

---

## 6. Commands and Actions

### 6.1 AddTraveler
- **Preconditions**: Customer is authenticated; account is active.
- **Inputs**: Full Name, Date of Birth, Gender, Passport Number, Passport Issue Date, Passport Expiry Date.
- **Expected Outcome**: Traveler profile created and linked to calling account.
- **Observable Behavior**: Traveler appears in customer’s saved travelers list.
- **Validation Rules**:
  - Full Name: required, 3 to 100 characters.
  - Date of Birth: required, valid date in the past (minimum age 0, maximum age 120).
  - Gender: `male` or `female`.
  - Passport Number: required string; after normalization must match `^[A-Z0-9]{6,12}$`.
  - Passport Issue Date: required, date in the past, after Date of Birth.
  - Passport Expiry Date: required, date after Passport Issue Date.
  - Global Uniqueness: `normalized_passport_number` must not already exist in database.
- **Authorization**: Authenticated customer operating on their own account.
- **Failure Behavior**:
  - Validation failure returns HTTP 422 with field errors.
  - Duplicate passport returns HTTP 422 with code `traveler.passport_conflict` and user-friendly message: `"This passport number is already registered to a traveler."`

### 6.2 UpdateTraveler
- **Preconditions**: Customer is authenticated; traveler belongs to calling customer.
- **Inputs**: Traveler ID, Full Name, Date of Birth, Gender, Passport Number, Issue Date, Expiry Date.
- **Expected Outcome**: Traveler profile updated for future use.
- **Observable Behavior**: Saved profile reflects new information immediately. Existing past orders are unaffected.
- **Validation Rules**: Same rules as AddTraveler. If passport number changed, the new normalized value must not collide with any existing traveler.
- **Authorization**: Customer ownership policy check (`account_id == traveler.account_id`). Access by another customer returns HTTP 404 (preventing existence enumeration).

### 6.3 ListTravelers
- **Preconditions**: Customer is authenticated.
- **Inputs**: Pagination parameters (page, per_page).
- **Expected Outcome**: Paginated list of travelers belonging strictly to the customer.
- **Observable Behavior**: Returns list with traveler IDs, names, dates of birth, masked or unmasked passport numbers.
- **Authorization**: Scoped strictly to authenticated `account_id`.

### 6.4 GetOwnedTravelerSnapshot (System Query)
- **Preconditions**: Called during order checkout by Purchasing domain.
- **Inputs**: Account ID, Traveler ID.
- **Expected Outcome**: Validated snapshot DTO containing frozen traveler attributes.
- **Failure Behavior**: If traveler does not exist or does not belong to the account, checkout aborts with `traveler.not_found`.

---

## 7. Business Rules

1. **Passport Normalization Function**:
   Given input string `S`:
   - Strip all leading and trailing whitespace.
   - Remove internal spaces, hyphens, underscores, slashes, and periods.
   - Convert all characters to uppercase ASCII.
   - Validate that the resulting string contains only characters `A-Z` and `0-9` and has length between 6 and 12 characters inclusive.
2. **Passport Expiry Warnings**: While traveler creation accepts valid future expiry dates, individual services may impose minimum passport validity (e.g. 6 months remaining). This check is performed during service form validation, not traveler profile persistence.

---

## 8. Edge Cases

- **Concurrent Registration of Same Passport**: Two different users simultaneously submit an order or profile with the same passport number. Database unique constraint on `normalized_passport_number` guarantees exactly one transaction succeeds, while the other rolls back and receives `traveler.passport_conflict`.
- **Renewed Passport**: A traveler renews their passport and receives a new passport number. The account owner updates the traveler profile with the new number. Future orders use the new number; past orders preserve the old passport snapshot.

---

## 9. Failure Behavior

- **Passport Conflict**: Code `traveler.passport_conflict`, HTTP 422. Message: `"This passport number is already registered to a traveler."`
- **Unauthorized Access / Accessing Another Account's Traveler**: HTTP 404 Not Found (avoids confirming existence of third-party traveler records).
- **Invalid Date Range**: Code `traveler.invalid_dates`, HTTP 422. Message: `"Passport expiry date must be after the issue date."`

---

## 10. Cross-Domain Interactions

- **Identity Domain**: Every traveler is tied to a verified `account_id`.
- **Purchasing & Orders Domain**: Checkout extracts an immutable `TravelerSnapshot` via `GetOwnedTravelerSnapshot`.
- **Fulfillment Domain**: Fulfillment views display the historical traveler snapshot captured at submission.
- **Audit Domain**: Administrative queries accessing unmasked sensitive traveler data log access events.
