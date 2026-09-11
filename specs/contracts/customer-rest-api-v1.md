# Contract: Customer REST API (v1)

## 1. Responsibility

This contract defines the public and customer-facing REST API surface (`/api/v1`) for the Rehla platform. It governs authentication, catalog browsing, traveler vault management, wallet queries, bank account listings, top-up submissions, file uploads, atomic order checkout, execution tracking, and notification management.

---

## 2. Producer and Consumer

- **Producer**: Rehla Backend Application (`packages/Rehla/Api`).
- **Consumers**: Rehla's customer Web client and first-party native mobile applications.

---

## 3. General Conventions and Headers

- **Base URL**: `/api/v1`
- **Content-Type**: `application/json` (except `/uploads` which accepts `multipart/form-data`).
- **Accept**: `application/json`
- **Authorization**: `Authorization: Bearer <token>` for all protected endpoints.
- **Idempotency**: `Idempotency-Key: <unique-uuid-or-string>` required on `POST /order-submissions`.
- **Locale Header**: `Accept-Language: en` or `Accept-Language: ar` (controls error and content localization).
- **Identifiers**: Every ID in a request or response is an opaque JSON string.
- **Standard Error Response**: `application/problem+json` format:
  ```json
  {
    "type": "https://rehla.sd/errors/validation-failed",
    "title": "Unprocessable Entity",
    "status": 422,
    "code": "form.validation_failed",
    "message": "The given data was invalid.",
    "errors": {
      "answers.mother_name": ["The mother name field is required."]
    },
    "trace_id": "req-8f4b2a9e-10c3"
  }
  ```

---

## 4. Endpoint Specifications

### 4.1 Authentication & Profile

#### `POST /api/v1/auth/register`
- **Auth**: Public. Strict rate limit: 5 req/min.
- **Inputs**:
  ```json
  {
    "name": "Ahmed Ibrahim",
    "email": "ahmed@example.com",
    "password": "Password123",
    "password_confirmation": "Password123",
    "locale": "en"
  }
  ```
- **Outputs** (HTTP 201 Created):
  ```json
  {
    "user": {
      "id": "usr_101",
      "name": "Ahmed Ibrahim",
      "email": "ahmed@example.com",
      "locale": "en",
      "created_at": "2026-09-11T20:00:00Z"
    },
    "token": "1|q6wE8rT...pat_token"
  }
  ```
- **Errors**: HTTP 422 `auth.email_exists`, `auth.weak_password`.

#### `POST /api/v1/auth/login`
- **Auth**: Public. Strict rate limit: 5 failed req/5 min.
- **Inputs**: `email`, `password`.
- **Outputs** (HTTP 200 OK): `user` object and Bearer `token`.
- **Errors**: HTTP 401 `auth.invalid_credentials`, HTTP 403 `auth.account_suspended`.

#### `POST /api/v1/auth/logout`
- **Auth**: Bearer token required.
- **Outputs**: HTTP 204 No Content. Revokes calling token.

#### `GET /api/v1/me` & `PATCH /api/v1/me`
- **Auth**: Bearer token.
- **Inputs for PATCH**: `name`, `locale`.

---

### 4.2 Service Catalog

#### `GET /api/v1/services`
- **Auth**: Public.
- **Outputs** (HTTP 200 OK):
  ```json
  {
    "data": [
      {
        "id": "svc_1",
        "name": "UAE 30-Day Tourist Visa",
        "slug": "uae-30-day-visa",
        "short_description": "Single-entry tourist visa for the United Arab Emirates.",
        "price_minor": 2500000,
        "formatted_price": "25,000.00 SDG",
        "currency": "SDG",
        "expected_duration": "3-5 business days",
        "image_url": "https://cdn.rehla.sd/media/services/uae.jpg"
      }
    ]
  }
  ```

#### `GET /api/v1/services/{service_slug}`
- **Auth**: Public.
- **Outputs**: Comprehensive service details, prerequisites array, detailed requirements, current price, and WhatsApp inquiry pre-filled deep-link metadata.

#### `GET /api/v1/services/{service_slug}/application-form`
- **Auth**: Public.
- **Outputs** (HTTP 200 OK):
  ```json
  {
    "service_id": "svc_1",
    "price_version_id": "price_7",
    "form_version_id": "form_4",
    "schema_checksum": "sha256:7f83b1657ff1fc...",
    "fields": [
      {
        "key": "mother_name",
        "label": "Mother's Full Name",
        "type": "short_text",
        "required": true,
        "helper_text": "Enter mother's name as stated in official records."
      },
      {
        "key": "personal_photo",
        "label": "Personal Photo (White Background)",
        "type": "image_upload",
        "required": true,
        "helper_text": "Recent photograph, max 10MB JPEG/PNG."
      }
    ]
  }
  ```

---

### 4.3 Travelers Vault

#### `GET /api/v1/travelers`
- **Auth**: Bearer token (Customer).
- **Outputs**: Paginated array of travelers belonging to calling account.

#### `GET /api/v1/travelers/{id}`
- **Auth**: Bearer token (Customer ownership enforced).
- **Outputs**: One owned traveler. A missing or foreign ID returns HTTP 404.

#### `POST /api/v1/travelers`
- **Auth**: Bearer token (Customer).
- **Inputs**:
  ```json
  {
    "full_name": "Sarah Mohammed",
    "date_of_birth": "1994-05-15",
    "gender": "female",
    "passport_number": "P01234567",
    "passport_issue_date": "2022-01-10",
    "passport_expiry_date": "2027-01-09"
  }
  ```
- **Validation**:
  - `passport_number` normalized: stripped of spaces/dashes, uppercase, `^[A-Z0-9]{6,12}$`.
  - Must not collide with any existing traveler on the platform.
- **Outputs** (HTTP 201 Created): Created traveler profile.
- **Errors**: HTTP 422 `traveler.passport_conflict`.

#### `PATCH /api/v1/travelers/{id}`
- **Auth**: Bearer token (Customer ownership enforced).
- **Outputs**: HTTP 200 OK. If non-owner: HTTP 404 Not Found.

---

### 4.4 Wallet & Top-Ups

#### `GET /api/v1/wallet`
- **Auth**: Bearer token (Customer).
- **Outputs** (HTTP 200 OK):
  ```json
  {
    "currency": "SDG",
    "balance_minor": 5000000,
    "formatted_balance": "50,000.00 SDG",
    "status": "active"
  }
  ```

#### `GET /api/v1/wallet/entries`
- **Auth**: Bearer token (Customer). Paginated transaction ledger history.

#### `GET /api/v1/bank-accounts`
- **Auth**: Bearer token (Customer).
- **Outputs**: Active platform bank accounts plus the current `minimum_top_up_minor` setting used by submission validation.

#### `GET /api/v1/top-ups` and `GET /api/v1/top-ups/{id}`
- **Auth**: Bearer token (Customer ownership enforced).
- **Outputs**: Paginated owned requests or one request including captured minimum, current receipt status, review status, and decision reason. A foreign ID returns HTTP 404.

#### `POST /api/v1/top-ups`
- **Auth**: Bearer token (Customer).
- **Inputs**:
  ```json
  {
    "bank_account_id": "bank_2",
    "amount_minor": 5000000,
    "transaction_reference": "BOK-987654321",
    "receipt_document_id": "doc-uuid-101"
  }
  ```
- **Validation**: `amount_minor` meets the current configurable minimum; `transaction_reference` is unique for the target bank account.
- **Outputs** (HTTP 201 Created):
  ```json
  {
    "id": "topup_401",
    "amount_minor": 5000000,
    "formatted_amount": "50,000.00 SDG",
    "bank_name": "Bank of Khartoum",
    "transaction_reference": "BOK-987654321",
    "status": "under_review",
    "submitted_at": "2026-09-11T20:10:00Z"
  }
  ```
- **Errors**: HTTP 422 `top_up.below_minimum`, HTTP 422 `top_up.reference_used`.

#### `PUT /api/v1/top-ups/{id}/receipt`
- **Auth**: Bearer token (Customer ownership enforced).
- **Inputs**: `receipt_document_id` referencing an owned clean `bank_receipt`.
- **Behavior**: Replaces the receipt on the same `under_review` request without changing its bank/reference pair. Decided requests return HTTP 409 `top_up.already_decided`.

---

### 4.5 Document Uploads

#### `POST /api/v1/uploads`
- **Auth**: Bearer token (Customer).
- **Content-Type**: `multipart/form-data`.
- **Inputs**: `file` and a private classification defined by the Documents domain.
- **Validation**: PDF <= 20MB, JPEG/PNG <= 10MB; magic bytes inspection.
- **Outputs** (HTTP 201 Created):
  ```json
  {
    "document_id": "doc-a1b2c3d4",
    "filename": "passport_scan.pdf",
    "size_bytes": 1048576,
    "status": "pending_scan",
    "expires_at": "2026-09-12T20:15:00Z"
  }
  ```

#### `GET /api/v1/uploads/{document_id}`
- **Auth**: Bearer token (Customer ownership enforced).
- **Outputs**: Scan status (`pending_scan`, `quarantined`, `clean`, or `rejected`) and a safe rejection code when terminal. Clients poll this route before attaching the document.

#### `GET /api/v1/documents/{id}/content`
- **Auth**: Bearer token (Owning customer).
- **Outputs**: Streamed binary file with `X-Content-Type-Options: nosniff`.

---

### 4.6 Order Submission & Tracking

#### `POST /api/v1/order-submissions`
- **Auth**: Bearer token (Customer).
- **Mandatory Header**: `Idempotency-Key: <unique-string>`.
- **Inputs**:
  ```json
  {
    "service_id": "svc_1",
    "traveler_id": "trav_12",
    "accepted_price_minor": 2500000,
    "accepted_price_version_id": "price_7",
    "form_version_id": "form_4",
    "answers": {
      "mother_name": "Fatima Hassan",
      "personal_photo": "doc-a1b2c3d4"
    }
  }
  ```
- **Behavior**: Executes atomic purchase transaction (debits wallet, creates order, creates execution).
- **Outputs** (HTTP 201 Created on first call, HTTP 200 on identical replay):
  ```json
  {
    "order_reference": "ORD-202609-1001",
    "service_name": "UAE 30-Day Tourist Visa",
    "traveler_name": "Sarah Mohammed",
    "price_paid_minor": 2500000,
    "formatted_price": "25,000.00 SDG",
    "currency": "SDG",
    "execution_id": "exec-9988",
    "execution_status": "received",
    "created_at": "2026-09-11T20:20:00Z"
  }
  ```
- **Errors**:
  - HTTP 409 `service.price_changed` (if database price != `accepted_price_minor`).
  - HTTP 422 `wallet.insufficient_balance`.
  - HTTP 409 `order.idempotency_conflict` (if key reused with different payload).
  - HTTP 409 `form.version_outdated`.
  - HTTP 422 `service.fulfillment_policy_missing`.
  - HTTP 422 `request.idempotency_key_required` when the header is absent.

#### `GET /api/v1/orders` & `GET /api/v1/orders/{order_ref}`
- **Auth**: Bearer token (Customer). Returns order summary, frozen snapshots, and execution progress.

#### `POST /api/v1/executions/{execution_id}/actions/{action_id}/responses`
- **Auth**: Bearer token (Customer).
- **Inputs**: `customer_notes`, `document_ids` (array of clean documents).
- **Behavior**: Transitions execution from `action_required` to `action_received`.

---

### 4.7 Notifications

#### `GET /api/v1/notifications`
- **Auth**: Bearer token (Customer). Paginated notifications with unread count.

#### `POST /api/v1/notifications/{id}/read`
- **Auth**: Bearer token (Customer). Marks notification read.

---

## 5. Idempotency and Retry Protocol

Clients must supply an `Idempotency-Key` (UUIDv4 recommended) on `POST /order-submissions`.
1. **Network Timeout / Client Drop**: If a client times out waiting for response, it resends the exact request with the same `Idempotency-Key`.
2. **Matching Payload**: Returns HTTP 200 OK with the original order outcome. Zero additional debits.
3. **Mismatched Payload**: If the client alters `traveler_id` or `service_id` using the same key, server returns HTTP 409 Conflict with code `order.idempotency_conflict`.

---

## 6. Rate Limiting Policy

- **Authentication Endpoints**: 5 requests per minute per IP.
- **Uploads Endpoint**: 10 requests per minute per account.
- **Order Submissions**: 10 requests per minute per account.
- **General Read APIs**: 60 requests per minute per account/IP.
- Rate-limit headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`.
