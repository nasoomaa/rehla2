# Domain: Service Catalog

## 1. Purpose

The Service Catalog domain manages travel services offered on the platform (e.g. UAE Visa, Saudi Tourist Visa, Umrah Permit, Qatar Visit). It owns service descriptions, current pricing, informational prerequisites/requirements, marketing imagery, display ordering, fulfillment duration estimates, and active/inactive status.

---

## 2. Actors

- **Customer / Public Guest**: Browses published services, views descriptions, prerequisites, and authoritative prices.
- **Service Administrator**: Creates new services, updates pricing, manages marketing content, sets fulfillment duration estimates, reorders services, and publishes or deactivates services.

---

## 3. Concepts

- **Service**: A distinct travel offering with a unique slug, bilingual name, short description, detailed description, prerequisites, expected duration, display order, and status.
- **Service Price**: The current cost to purchase the service, stored in integer minor units of Sudanese Pounds (`amount_minor`, scale 100).
- **Price Version**: An immutable price event recording a positive version, amount, effective timestamp, and staff author. The service's current-price pointer may advance; prior versions are never closed by mutation.
- **Service Requirements (Informational)**: Bulleted prerequisite rules that users must read prior to applying (e.g., "Passport must have at least 6 months validity", "Personal photograph on white background").
- **Expected Duration**: An estimate of operational fulfillment time (e.g., "3 to 5 business days").
- **Service Media**: Public marketing photographs or banner images associated with the service.

---

## 4. Invariants

1. **Authoritative Active Price**: Every active service must have exactly one current authoritative price in Sudanese Pounds (SDG).
2. **Historical Price Immutability**: Historical price versions can never be updated or deleted. A price change inserts a new version and atomically advances the service's current-price pointer.
3. **Deactivation Non-Destructiveness**: Deactivating a service prevents new orders from being placed, but never invalidates, modifies, or erases historical orders or in-flight executions.
4. **Display Order Uniqueness**: In storefront views, services are sorted deterministically according to an explicit integer `display_order` followed by creation date.
5. **No Negative or Zero Prices**: Service prices must be positive integers (`amount_minor > 0`). Free services are not supported in Phase 1.

---

## 5. State Model

### Service Lifecycle
```text
[Created] ──► Draft ──► Active (Published) ◄──► Inactive (Deactivated)
```
- **Draft**: Under preparation by staff; invisible to customers, cannot be ordered.
- **Active**: Visible in public catalog; available for ordering.
- **Inactive**: Hidden from public catalog or marked unavailable; existing orders continue fulfillment, but new order submissions are rejected.

---

## 6. Commands and Actions

### 6.1 CreateService
- **Preconditions**: User has `services.manage` ability.
- **Inputs**: Name (EN/AR), Slug, Short Description (EN/AR), Detailed Description (EN/AR), Initial Price (`amount_minor`), Expected Duration (EN/AR), Requirements List (EN/AR), Display Order.
- **Expected Outcome**: New service created in `draft` status.
- **Observable Behavior**: Service appears in administrative service list.
- **Validation Rules**:
  - Name: required, 3-100 characters.
  - Slug: required, unique, URL-safe alphanumeric and hyphens.
  - Price: required, positive integer minor units (`amount_minor > 0`).
  - Requirements: non-empty list.
- **Authorization**: Staff with `services.manage`.
- **Failure Behavior**: Duplicate slug or invalid price returns HTTP 422.

### 6.2 UpdateServicePrice
- **Preconditions**: User has `services.manage` ability. Service exists.
- **Inputs**: Service ID, New Price (`amount_minor`), Reason for Change.
- **Expected Outcome**: Current active price updated; new price history entry recorded with effective timestamp.
- **Observable Behavior**: Immediate update of storefront service price. In-flight order submissions initiated under the old price will be caught by price validation upon final submission.
- **State Changes**: Inserts a new immutable price version and atomically advances the current-price pointer.
- **Validation Rules**: New price must be positive integer and different from current price.
- **Side Effects**: Emits `ServicePriceUpdated` event.

### 6.3 PublishService
- **Preconditions**: Service is in `draft` or `inactive` status and its bilingual catalog content, positive current price, requirements, and media references are valid.
- **Inputs**: Service ID.
- **Expected Outcome**: Service status becomes `active`.
- **Observable Behavior**: Service immediately becomes visible on the public website and customer REST API.
- **Failure Behavior**: Incomplete catalog content is rejected with stable field errors. Ordering readiness is evaluated separately across Catalog, Forms, and Fulfillment policy.

### 6.4 DeactivateService
- **Preconditions**: Service is currently `active`.
- **Inputs**: Service ID.
- **Expected Outcome**: Status becomes `inactive`.
- **Observable Behavior**: Service disappears from public browsing or displays "Currently Unavailable". Subsequent order submissions fail with `service.unavailable`.

---

## 7. Business Rules

1. **Currency Exclusivity**: All prices are expressed strictly in Sudanese Pounds (SDG).
2. **Authoritative Timestamp**: The valid price for any order is the price active in the database at the exact microsecond the checkout transaction commits.
3. **Public Media Separation**: Service banner images are stored on public storage disks and served directly without authentication gates.

---

## 8. Edge Cases

- **Price Change During Application Form Completion**: If a user opens the application form when the price is 25,000 SDG, and the price is updated to 30,000 SDG before they click "Submit Order", the submission must be halted, alerting the user to review and confirm the new price.
- **Service Deactivation During Order Completion**: If a service is deactivated while a customer is filling out the form, final submission is rejected with an explicit notification that the service is no longer offered.

---

## 9. Failure Behavior

- **Service Unavailable**: If ordering an inactive service, returns HTTP 422 with code `service.unavailable` and user message: `"This service is currently unavailable for ordering."`
- **Price Mismatch**: If the submitted accepted price does not match the current authoritative price, returns HTTP 409 Conflict with code `service.price_changed` and details containing old vs new price.

---

## 10. Cross-Domain Interactions

- **Forms Domain**: Forms may publish immutable schemas for a catalog service without creating a reverse Catalog dependency.
- **Orders & Purchasing Domain**: Checkout composes ordering readiness: active service/current price, published form version, and published fulfillment policy. A visible service that lacks either dependency returns `service.unavailable` for ordering.
- **Audit Domain**: Service creation, price revisions, and status toggles append immutable audit entries.
