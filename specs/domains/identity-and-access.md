# Domain: Identity and Access Management

## 1. Purpose

The Identity and Access Management domain owns customer accounts, staff user profiles, authentication mechanisms across Web, API, and Admin surfaces, session management, personal access tokens, role definitions, granular abilities, and security enforcement policies (including MFA).

---

## 2. Actors

- **Customer (Account Owner)**: Registers, signs in, manages account settings, logs out, and uses personal access tokens.
- **Staff Member (Reviewer / Fulfillment Officer / Admin)**: Authenticates via the administrative control plane, verifies identity via TOTP MFA, and exercises granted operational abilities.
- **System**: Enforces rate limits, session invalidation, and ability checks.

---

## 3. Concepts

- **User Account**: The central identity record containing email, full name, phone number, password hash, status (`active`, `suspended`), preferred language (`en` or `ar`), and timestamps.
- **Staff Profile**: Operational metadata linked to an administrative user, defining departmental affiliation, active status, and MFA configuration.
- **Role**: A named collection of granular abilities (e.g., `Reviewer`, `Fulfillment Officer`, `Platform Administrator`).
- **Ability (Permission)**: An atomic authorization grant representing a specific action or visibility scope (e.g., `topups.review`, `services.manage`, `travelers.view_sensitive`, `audit.view`).
- **Customer Session**: An encrypted HTTP session cookie used on Customer Web.
- **Admin Session**: An isolated HTTP session cookie bounded to the administrative control plane guard.
- **Personal Access Token (PAT)**: A cryptographically secure Bearer token issued to customers for REST API interaction.
- **TOTP MFA**: Time-based One-Time Password configuration required for staff holding sensitive financial or governance abilities.

---

## 4. Invariants

1. **Email Uniqueness**: Email addresses must be normalized (lowercased, whitespace stripped) and unique across all active accounts.
2. **Session Guard Isolation**: Customer sessions and Admin sessions must use separate cookies and distinct session stores; a customer session can never authenticate an administrative request, and vice versa.
3. **Deny-by-Default**: In the absence of an explicit role grant or ability assignment, all administrative and sensitive operations are forbidden.
4. **Least Privilege**: Staff users receive only explicitly assigned abilities; new staff profiles possess zero permissions upon creation.
5. **MFA Enforcement for High-Risk Abilities**: Any staff account possessing `topups.review`, `access.manage`, or `audit.view` cannot execute those abilities without an active, verified TOTP MFA session.
6. **Account Ownership Immutability**: The link between an account and its historical records (orders, wallet, travelers) is permanent and cannot be transferred to another user.

---

## 5. State Model

### 5.1 Account Status
```text
[Created] ──► Active ──► Suspended
                ▲           │
                └───────────┘
```
- **Active**: Account can sign in, browse services, manage travelers, top up wallet, and submit orders.
- **Suspended**: Account cannot sign in or perform any write actions. Existing orders remain preserved.

### 5.2 Staff MFA State
```text
[Created] ──► Disabled ──► Setup Pending ──► Enabled
                             ▲                 │
                             └─────────────────┘
```

---

## 6. Commands and Actions

### 6.1 RegisterCustomer
- **Preconditions**: Email is not registered to an active account.
- **Inputs**: Full Name, Email, Password, Password Confirmation, Preferred Language (`en` or `ar`).
- **Expected Outcome**: New customer account created, default wallet provisioned, notification preferences initialized.
- **Observable Behavior**: On Web: customer is authenticated and redirected to dashboard. On API: HTTP 201 Created returned with account profile and initial Bearer token.
- **State Changes**: New account record inserted with status `active`.
- **Validation Rules**:
  - Full Name: required, min 3 chars, max 100 chars.
  - Email: required, valid RFC 5322 email format, max 255 chars.
  - Password: min 8 chars, containing at least one uppercase, one lowercase, and one number.
  - Preferred Language: `en` or `ar` (defaults to `en`).
- **Authorization**: Public endpoint; strict IP-based and subnet rate limiting (max 5 requests per minute).
- **Failure Behavior**: Validation failure returns HTTP 422 with field errors. Duplicate email returns HTTP 422 with generic error `"An account with this email already exists."`
- **Side Effects**: Emits `CustomerRegistered` domain event; triggers wallet initialization.

### 6.2 AuthenticateCustomer
- **Preconditions**: Account exists and is in `active` status.
- **Inputs**: Email, Password.
- **Expected Outcome**: Verified identity credentials.
- **Observable Behavior**:
  - On Web: Session created, HTTP redirect to requested page.
  - On API: HTTP 200 OK with `token` (plain text Bearer token) and user profile.
- **Validation Rules**: Email and password required.
- **Authorization**: Public endpoint; strict rate limiting (max 5 failed attempts per 5 minutes before temporary lockout).
- **Failure Behavior**: If credentials mismatch or account is suspended: HTTP 401 Unauthorized with code `auth.invalid_credentials`.

### 6.3 AuthenticateStaff
- **Preconditions**: User is an active staff member.
- **Inputs**: Email, Password, TOTP Token (if MFA enabled or required by role).
- **Expected Outcome**: Administrative session initialized.
- **Observable Behavior**: Redirect to administrative control plane overview.
- **Authorization**: Staff login endpoint; lockout after 3 failed attempts.
- **Failure Behavior**: HTTP 401 Unauthorized or HTTP 403 Forbidden with code `auth.staff_access_denied`.

### 6.4 RevokeCustomerToken
- **Preconditions**: Caller is authenticated with Bearer token.
- **Inputs**: Token ID or current token indicator.
- **Expected Outcome**: Targeted token is revoked and deleted.
- **Observable Behavior**: HTTP 200 OK or HTTP 204 No Content. Subsequent requests using the token receive HTTP 401.

### 6.5 UpdateProfile
- **Preconditions**: Customer is authenticated.
- **Inputs**: Full Name, Preferred Language.
- **Expected Outcome**: Profile updated.
- **Observable Behavior**: Immediate reflection on UI / API response.
- **Validation Rules**: Full name (3-100 chars), language (`en` or `ar`). Email cannot be changed via simple profile update.

---

## 7. Business Rules

1. **Password Policy**: Passwords must contain minimum 8 characters, at least 1 uppercase letter, 1 lowercase letter, and 1 numeric digit.
2. **Account Deletion / Erasure**: Accounts cannot be permanently erased if they possess financial ledger entries, commercial orders, or top-up records. They may only be deactivated or suspended to preserve audit integrity.
3. **Cross-Interface Isolation**: An administrator cannot use their staff session to execute customer actions (e.g. buying a visa for themselves) without switching to a verified customer account.

---

## 8. Edge Cases

- **Concurrent Logins from Multiple Devices**: Permitted for customers; each mobile/API device receives its own Bearer token.
- **Account Suspension Mid-Session**: Active sessions and tokens for a suspended account are invalidated on the very next request; all pending write operations are blocked.
- **Email Case Sensitivity**: Registration with `Ahmed@Example.com` must collide with `ahmed@example.com` and be rejected.

---

## 9. Failure Behavior

- **Invalid Credentials**: Returns standard error `auth.invalid_credentials` with localized message `"The provided credentials do not match our records."`
- **Suspended Account**: Returns `auth.account_suspended` with message `"Your account has been suspended. Please contact support."`
- **Rate Limit Exceeded**: Returns HTTP 429 Too Many Requests with `Retry-After` header.

---

## 10. Cross-Domain Interactions

- **Wallet Domain**: When `CustomerRegistered` occurs, the Wallet domain initializes an empty wallet for the user.
- **Audit Domain**: All staff logins, failed staff login attempts, role reassignments, and account suspensions write immutable entries to the Audit domain.
