# Journey 03: Traveler Profile Management and Passport Vault

## 1. Goal

An authenticated customer saves and manages beneficiary traveler profiles (e.g. self, spouse, children) within their personal account vault, ensuring passport numbers are validated, normalized, and globally unique across the platform to enable seamless, error-free reuse across future orders.

---

## 2. Actors

- **Customer (Account Owner)**
- **Travelers Domain**
- **Identity Domain**

---

## 3. Preconditions

1. Customer is authenticated with an active account session.
2. Customer is on the account portal.

---

## 4. Trigger

Customer navigates to `/account/travelers` and clicks **"+ Add Traveler"**.

---

## 5. Complete Happy-Path Sequence

1. **User opens Travelers section**:
   - The browser requests `GET /account/travelers`.
   - System renders the traveler vault listing current saved family members.
2. **User clicks "+ Add Traveler"**:
   - System displays the traveler modal / form.
   - Form renders fields: Full Name, Date of Birth, Gender (`male` / `female`), Passport Number, Passport Issue Date, Passport Expiry Date.
   - No nationality or country field is displayed (Sudanese scope).
3. **User inputs traveler data**:
   - Full Name: `"Ahmed Mohammed Osman"`
   - Date of Birth: `"1990-08-20"`
   - Gender: `"male"`
   - Passport Number: `" p 012-345 67 "` (raw input with spaces and hyphens)
   - Passport Issue Date: `"2022-03-01"`
   - Passport Expiry Date: `"2027-02-28"`
4. **User submits form**:
   - User clicks **"Save Traveler"**.
   - Form sends `POST /api/v1/travelers` (or Web form submission).
5. **System executes normalization function**:
   - Raw passport string `" p 012-345 67 "` is transformed:
     - Strips leading/trailing spaces.
     - Strips internal spaces and hyphens.
     - Uppercases all characters.
     - Result: `"P01234567"`.
6. **System executes format and date validation**:
   - Tests normalized passport against `^[A-Z0-9]{6,12}$` (Passes: 9 alphanumeric characters).
   - Verifies Date of Birth is in the past.
   - Verifies Passport Issue Date is after Date of Birth.
   - Verifies Passport Expiry Date is after Passport Issue Date.
7. **System verifies global passport uniqueness**:
   - Queries database for `normalized_passport_number = 'P01234567'`.
   - Confirms zero existing records across the entire platform.
8. **System creates traveler record**:
   - Inserts record into `travelers` table:
     - `account_id = current_user_id`
     - `full_name = "Ahmed Mohammed Osman"`
     - `date_of_birth = "1990-08-20"`
     - `gender = "male"`
     - `passport_number = "P01234567"` (normalized)
     - `passport_issue_date = "2022-03-01"`
     - `passport_expiry_date = "2027-02-28"`
9. **System renders updated traveler list**:
   - Modal closes.
   - Traveler card appears on UI:
     - Name: `"Ahmed Mohammed Osman"`
     - Passport Number: `"P01234567"`
     - Expiry: `"2027-02-28"`
   - Success toast appears: `"Traveler profile saved successfully."`

---

## 6. Alternate Paths

### Path 3A: Updating Traveler Following Passport Renewal
1. User clicks **"Edit"** on Ahmed’s traveler card.
2. Form opens populated with current data.
3. User enters new passport number `"P09876543"`, new issue date `"2027-03-01"`, and new expiry date `"2032-02-28"`.
4. User clicks **"Update Traveler"**.
5. System normalizes new passport, verifies uniqueness, and updates the profile.
6. The traveler profile is updated for future orders.
7. **Critical Invariant**: Any historical commercial orders placed under Ahmed's previous passport `"P01234567"` remain completely unchanged.

---

## 7. Failure Paths

### Path 3F-1: Duplicate Passport Number (Global Collision)
1. User attempts to save a traveler with passport number `"P01234567"`.
2. Another user (or the same user earlier) has already registered `"P01234567"` on the platform.
3. System detects database collision on `normalized_passport_number`.
4. System halts creation and returns HTTP 422 with code `traveler.passport_conflict` and user-facing message:
   `"This passport number is already registered to another traveler."`
5. **Privacy Invariant**: The system never discloses WHO owns the conflicting passport number or which account registered it.

### Path 3F-2: Invalid Date Sequence
1. User enters Passport Expiry Date (`2021-01-01`) earlier than Passport Issue Date (`2022-01-01`).
2. System validation halts with HTTP 422: `"Passport expiry date must be after the issue date."`

### Path 3F-3: Invalid Passport Format
1. User enters invalid passport `"123"` (less than 6 characters) or special characters `"P0123#56"`.
2. System validation rejects input: `"Passport number must be between 6 and 12 alphanumeric characters."`

---

## 8. Recovery Behavior

- On passport collision, the user inspects the entered number for typos. If the traveler is already in their vault, they select the existing profile.
- On validation failure, inline error indicators guide the user to supply valid dates and format.

---

## 9. Final Observable State

1. **Database State**:
   - `travelers`: 1 new row created with normalized passport `P01234567` associated with `account_id`.
2. **User State**:
   - Traveler profile is saved and immediately selectable in future order flows.

---

## 10. Cross-Domain Dependencies

- **Travelers**: Manages profiles and enforces global uniqueness.
- **Identity**: Enforces user ownership policy.
- **Orders**: Subsequent purchases query this profile to freeze `TravelerSnapshot`.

---

## 11. External-System Interactions

- None. Operations are performed locally within the database.
