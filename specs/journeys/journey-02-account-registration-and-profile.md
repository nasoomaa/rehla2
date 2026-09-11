# Journey 02: Account Registration and Profile Management

## 1. Goal

A new user registers an account on Rehla, establishes secure credentials, automatically provisions an active wallet, sets their preferred language, and accesses their customer dashboard.

---

## 2. Actors

- **Customer (New User)**
- **Identity and Access Domain**
- **Wallet and Financial Ledger Domain**
- **Notifications Domain**

---

## 3. Preconditions

1. The user has a valid, accessible email address not registered by any active or suspended Rehla account.
2. The platform is online and accepting new registrations.

---

## 4. Trigger

The user clicks **"Sign Up"** on the Rehla header or navigates to `/register`.

---

## 5. Complete Happy-Path Sequence

1. **User opens registration form**:
   - The browser requests `GET /register`.
   - The system displays the registration interface with fields: Full Name, Email, Password, Confirm Password, and Preferred Language toggle (`en` / `ar`).
2. **User inputs registration details**:
   - Name: `"Mohammed Osman"`
   - Email: `"mohammed.osman@example.com"`
   - Password: `"SecretPass2026!"`
   - Confirm Password: `"SecretPass2026!"`
   - Preferred Language: `"en"`
3. **User submits registration**:
   - User clicks **"Create Account"**.
   - Form sends `POST /register` (or `POST /api/v1/auth/register`).
4. **System executes input validation**:
   - Verifies email syntax according to RFC 5322.
   - Verifies password meets complexity rules (>= 8 chars, uppercase, lowercase, numeric).
   - Normalizes email to lowercase.
   - Checks database: asserts no user exists with `mohammed.osman@example.com`.
5. **System creates user identity record**:
   - Password is encrypted using Argon2id / bcrypt.
   - User record is inserted into `users` table with status `active`.
6. **System initializes default wallet**:
   - A synchronous in-process `CustomerRegistered` handler provisions the empty wallet inside the registration transaction; failure rolls back the user record.
   - Currency is set to `SDG`.
   - `current_balance_minor` is initialized to `0`.
   - Status is set to `active`.
7. **System creates session and tokens**:
   - For Web: Creates an authenticated HTTP session cookie.
   - For API: Generates initial Sanctum Bearer token.
8. **System records welcome notification**:
   - Creates the welcome in-app notification atomically and inserts a `CustomerRegistered` outbox message only for enabled asynchronous external channels.
9. **System commits transaction**:
   - PostgreSQL transaction commits cleanly.
10. **System redirects to customer account dashboard**:
    - Browser redirects to `/account`.
    - Dashboard renders greeting: `"Welcome, Mohammed Osman"`.
    - Dashboard displays wallet balance: `"0.00 SDG"`.
    - Dashboard displays empty travelers vault: `"No travelers saved yet. Add a traveler to order services faster."`

---

## 6. Alternate Paths

### Path 2A: Existing Customer Login
1. User navigates to `/login`.
2. User enters registered email and password.
3. System verifies credentials against password hash.
4. System sets session cookie and redirects to `/account`.

### Path 2B: Customer Updates Profile Name or Language
1. User navigates to `/account/profile`.
2. User toggles preferred language from `en` to `ar` and clicks **"Save Changes"**.
3. System updates `preferred_language` to `ar`.
4. Page re-renders in Arabic with `dir="rtl"` layout.

---

## 7. Failure Paths

### Path 2F-1: Duplicate Email Address
1. User attempts to register with an email already present in the database (`mohammed.osman@example.com`).
2. Validation query detects collision.
3. System halts execution; creates no user, no wallet, and no session.
4. System returns HTTP 422 with localized message: `"An account with this email address already exists. Please sign in."`
5. Form highlights email field in red; passwords fields are cleared.

### Path 2F-2: Password Complexity Failure
1. User enters a weak password: `"123456"`.
2. System rejects input with HTTP 422: `"Password must be at least 8 characters and include uppercase, lowercase, and numbers."`

### Path 2F-3: Suspended Account Login
1. A suspended user attempts to log in via `/login`.
2. System verifies password, but checks `status == 'suspended'`.
3. System invalidates attempt and returns HTTP 403 Forbidden with code `auth.account_suspended` and message: `"Your account has been suspended. Please contact customer support."`

---

## 8. Recovery Behavior

- On duplicate email, the user can click **"Sign In"**. Password recovery is outside this journey unless separately specified.
- On validation failure, field-level error messages direct the user to correct the specific fields without leaving the page.

---

## 9. Final Observable State

1. **Database State**:
   - `users`: 1 record created with status `active`.
   - `wallets`: 1 record created with `balance_minor = 0`, status `active`, currency `SDG`.
   - `in_app_notifications`: 1 welcome message created.
2. **User State**:
   - User is authenticated with an active session.
   - User is on dashboard viewing balance `0.00 SDG`.

---

## 10. Cross-Domain Dependencies

- **Identity**: Manages credentials, tokens, and profiles.
- **Wallet**: Provisions the initial 0.00 SDG wallet.
- **Notifications**: Enqueues initial welcome in-app message.
- **Audit**: Logs customer registration event with IP and timestamp.

---

## 11. External-System Interactions

- None. Registration is entirely self-contained.
