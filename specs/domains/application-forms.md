# Domain: Application Forms

## 1. Purpose

The Application Forms domain owns dynamic service application form definitions, form schemas, field configurations, drafting workflows, and form versioning. It guarantees that customers provide all requisite data and files for a service, while ensuring historical submissions remain permanently bound to the exact schema version active when submitted.

---

## 2. Actors

- **Service Administrator**: Creates form drafts, configures field parameters and validation rules, tests schemas, and publishes new immutable form versions.
- **Customer**: Fetches the currently published form version for a service, populates fields, attaches document IDs, and submits responses.
- **System**: Validates customer responses against the published form version schema before checkout.

---

## 3. Concepts

- **Form Draft**: An editable administrative specification of an application form attached to a specific service.
- **Form Version**: A published, frozen, immutable snapshot of an application form schema, identified by a sequential integer version number (e.g. `v1`, `v2`) and a cryptographic SHA-256 checksum.
- **Form Schema**: A declarative JSON definition listing all form fields, labels, help text, ordering, required/optional flags, allowed options, and validation rules.
- **11 Supported Field Types**:
  1. `short_text`: Single-line text (e.g. "Mother's Maiden Name", "Passport Place of Issue").
  2. `long_text`: Multi-line text (e.g. "Residential Address in Sudan").
  3. `email`: Email address format validation.
  4. `phone`: Telephone number with country dialing prefix validation.
  5. `number`: Numeric value (integer or decimal with min/max bounds).
  6. `date`: Calendar date with constraints (e.g. past dates, future dates).
  7. `dropdown`: Single selection from a predefined dropdown list.
  8. `radio`: Single selection from visible radio choices.
  9. `checkbox`: Boolean agreement or multiple choice selection.
  10. `file_upload`: Upload of a private document (e.g. PDF of bank statement, national ID).
  11. `image_upload`: Upload of an image (e.g. passport personal photo on white background).

---

## 4. Invariants

1. **Published Version Immutability**: Once a form version is published, it is permanently immutable. It cannot be edited, overwritten, re-ordered, or deleted by any administrative action or direct database query.
2. **Historical Order Preservation**: Modifying a form schema never alters historical orders or service execution records. Existing orders retain their historical form version reference and raw submitted responses.
3. **Sequential Version Numbering**: Form versions for a given service increment strictly monotonically (`1, 2, 3...`).
4. **Draft / Published Separation**: Draft modifications are isolated and completely invisible to customers until published.
5. **Exact Type and Field Integrity**: Every field response submitted by a customer must conform precisely to the types and constraints defined in the published schema.

---

## 5. State Model

### Form Versioning Lifecycle
```text
[New Version Started] ──► Draft ──► Published (Immutable Forever)
                            │
              [Subsequent modifications create a new Draft]
```
- **Draft**: Open for editing in the administrative panel; not exposed to customers.
- **Published**: Read-only; actively served to new customer order flows; previous version is archived for historical reference.

---

## 6. Commands and Actions

### 6.1 CreateFormDraft
- **Preconditions**: Service exists; user has `forms.draft` ability.
- **Inputs**: Service ID, Base Version ID (optional, to clone from existing version).
- **Expected Outcome**: New editable form draft instantiated for the service.
- **Observable Behavior**: Draft appears in administrative form builder.
- **Validation Rules**: Only one active draft may exist per service at a time.
- **Authorization**: Staff with `forms.draft`.

### 6.2 UpdateFormDraft
- **Preconditions**: Draft exists and is not published; user has `forms.draft`.
- **Inputs**: Draft ID, Field Definitions List (labels in EN/AR, field types from the 11 supported, display order, is_required boolean, helper text in EN/AR, options array if applicable, validation rules).
- **Expected Outcome**: Draft updated with new field layout.
- **Validation Rules**:
  - Field type must be one of the 11 supported types.
  - Field keys must be unique within the form.
  - Dropdown and radio fields must specify at least two options.
- **Failure Behavior**: Invalid schema returns HTTP 422 with field-level details.

### 6.3 PublishFormVersion
- **Preconditions**: Draft exists, is syntactically and semantically valid; user has `forms.publish`.
- **Inputs**: Draft ID.
- **Expected Outcome**:
  - New immutable `FormVersion` record created with next version number (`current + 1`).
  - Cryptographic checksum computed and locked.
  - Marked as active form version for the service.
  - Draft is marked archived.
- **Observable Behavior**: The public and API endpoints for `GET /api/v1/services/{service}/application-form` immediately return the new version.
- **Side Effects**: Emits `FormVersionPublished` event; writes to Audit log.

### 6.4 ValidateFormSubmission (System Query)
- **Preconditions**: Form version exists and is active.
- **Inputs**: Form Version ID, Customer Answers Payload (key-value map of field responses and document IDs).
- **Expected Outcome**: Successful validation confirmation or structured error list.
- **Observable Behavior**:
  - Rejects missing required fields.
  - Rejects unrecognized field keys not defined in schema.
  - Rejects type mismatches (e.g. non-date in date field, non-numeric in number field).
  - Validates document IDs (verifies files exist, are marked `clean`, and belong to current customer).

---

## 7. Business Rules

1. **Schema Checksum Verification**: Canonical JSON recursively sorts object keys, preserves array order and string contents, and uses UTF-8 without insignificant whitespace. Every published version stores the full lowercase SHA-256 of these bytes; mismatch raises `form.schema_integrity_failed` and an integrity alert.
2. **Independent Snapshots**: When a customer submits an order, their responses are frozen as JSON alongside the exact `form_version_id`.
3. **No Phantom Fields**: Responses containing fields not declared in the target version schema are rejected immediately.

---

## 8. Edge Cases

- **Customer Submits with Stale Version**: A customer loads Version 3 and takes 20 minutes to fill it out. During that time, staff publishes Version 4 (e.g. adding a new mandatory field). Upon clicking submit, the system detects `form_version_id` mismatch, halts checkout, and instructs the user to refresh and complete the updated requirements.
- **File Upload Verification**: If a field requires an image or file upload, the customer must submit a valid `document_id` corresponding to a file previously uploaded, scanned, and verified clean under their account.

---

## 9. Failure Behavior

- **Schema Validation Errors**: Returns HTTP 422 with structured errors detailing the field key, failed validation rule, and localized human-readable message.
- **Stale Form Version**: Returns HTTP 409 Conflict with code `form.version_outdated` and the ID of the new authoritative version.
- **Unverified Document Reference**: Returns HTTP 422 with code `form.invalid_document_attachment`.

---

## 10. Cross-Domain Interactions

- **Service Catalog Domain**: Services require an active published Form Version to be published.
- **Documents Domain**: Application form file/image fields reference clean document IDs managed by the Documents domain.
- **Purchasing & Orders Domain**: `SubmitOrder` validates customer answers against the active `FormVersion` before executing the payment transaction.
- **Fulfillment Domain**: Operational execution views display customer answers formatted against the historical `FormVersion` schema.
