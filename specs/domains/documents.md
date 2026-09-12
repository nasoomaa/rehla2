# Domain: Documents

## 1. Purpose

The Documents domain manages file uploads, metadata tracking, secure storage partitioning (private vs. public disks), file sanitization, virus/malware scanning, mime-type verification via magic bytes, authorized access gates, and retention lifecycles for sensitive customer artifacts.

---

## 2. Actors

- **Customer**: Uploads bank transfer receipts and application form attachments; downloads their own submitted files.
- **Operations Staff**: Views and downloads sensitive applicant documents and bank receipts using authorized gates; requires `documents.view_sensitive` for high-privilege documents.
- **System**: Enforces quarantine, detects magic bytes, checks malware signatures, streams files securely, and purges orphaned temporary uploads.

---

## 3. Concepts

- **Document Record**: Metadata tracking a stored file: ID, owner/scope, Storage Disk (`private` or `public`), Storage Key, Original Filename, Detected MIME Type, File Size, SHA-256 Checksum, canonical classification, Lifecycle Status, and retention metadata. Private classifications are `bank_receipt`, `passport_scan`, `identity_document`, `applicant_photo`, `supporting_document`, and `issued_document`; public classifications are `service_media` and `bank_logo`.
- **Upload Session**: A temporary staging container created when a user initiates an upload.
- **Private Storage Disk**: A secure storage system (object storage bucket or protected filesystem) strictly inaccessible from public internet gateways. All customer-uploaded documents reside here.
- **Public Storage Disk**: Publicly accessible storage used strictly for marketing assets, service banner images, and bank logos.
- **Scanning Pipeline**: The automated inspection mechanism that tests uploaded files for magic bytes integrity, decodability, polyglot structures, and malware signatures.

---

## 4. Invariants

1. **Private Storage by Default**: All customer-uploaded documents (passports, national IDs, bank receipts, personal photos, supporting papers) must be stored on the private disk. Permanent public URLs are strictly prohibited.
2. **Strict Whitelist of Formats**: Only three file formats are permitted for customer uploads:
   - PDF (`application/pdf`)
   - JPEG (`image/jpeg`)
   - PNG (`image/png`)
   All other formats (including DOCX, TIFF, ZIP, SVG) are rejected.
3. **Size Quotas**:
   - Images (`image/jpeg`, `image/png`): Maximum 10 MiB (10,485,760 bytes).
   - Documents (`application/pdf`): Maximum 20 MiB (20,971,520 bytes).
4. **Clean Status Prerequisite**: Only documents in `clean` status can be attached to a Top-Up Request or Service Order. Quarantined, pending, or rejected documents cannot be linked.
5. **Retention Rules**:
   - **Orphaned (Unattached) Temporary Uploads**: Retained for exactly 24 hours from upload time, then permanently purged.
   - **Rejected / Infected Uploads**: Retained in quarantine for exactly 30 days for security auditing, then permanently erased.
   - **Attached Documents**: Never pruned as orphans. Their retention follows the parent record's approved policy; Phase 1 retains them indefinitely until a deletion/retention policy is approved.
6. **Zero Public Disclosure**: Downloads of private documents must pass an explicit authorization check and be served via short-lived signed URLs (lifetime <= 15 minutes) or authorized proxy streaming with `X-Content-Type-Options: nosniff` and safe `Content-Disposition`.

---

## 5. State Model

### Document Lifecycle
```text
[Upload Initiated]
        │
        ▼
   Pending Scan
        │
        ▼
   Quarantined ─────────────► Rejected (Retained 30 days for audit)
        │
        ▼
      Clean ──► Attached (Follows parent retention; never orphan-pruned)
        │
        └─────► Unattached (Pruned after 24 hours)
```

- **Pending Scan**: File has landed in private staging storage; scanning and magic byte verification in progress.
- **Quarantined**: Potential anomaly detected; isolated from application attachment.
- **Clean**: Passed validation and malware inspection; available for attachment to orders or top-up requests.
- **Rejected**: Failed verification (e.g. extension mismatch, malware detected, polyglot exploit); blocked from attachment.
- **Attached**: Linked to a submitted Top-Up Request, Commercial Order, or Fulfillment Execution. Cannot be purged as an orphan.

---

## 6. Commands and Actions

### 6.1 UploadDocument
- **Preconditions**: Customer is authenticated.
- **Inputs**: File Binary, Original Filename, one allowed private classification.
- **Expected Outcome**: File stored in private staging bucket; document record created in `pending_scan` status; asynchronous scanning task scheduled.
- **Observable Behavior**: Returns HTTP 201 with `document_id`, filename, size, and `pending_scan`. The authenticated client polls the upload-status endpoint until `clean` or `rejected`.
- **Validation Rules**:
  - File size must be within quota (<= 10MB image, <= 20MB PDF).
  - Extension must be `.pdf`, `.jpg`, `.jpeg`, or `.png`.
- **Authorization**: Scoped to authenticated customer. Strict upload rate limiting (max 10 uploads per minute per account).
- **Failure Behavior**: File too large returns HTTP 413 Payload Too Large. Prohibited extension returns HTTP 422 with `document.unsupported_type`.

### 6.2 ProcessDocumentScan (System Action)
- **Preconditions**: Document exists in `pending_scan`.
- **Inputs**: Document ID.
- **Expected Outcome**:
  - Magic bytes inspected: verifies file header matches declared extension.
  - Image decodability checked (verifies image headers and pixel streams).
  - Malware engine signature scan executed.
  - Status first transitions to `quarantined` while deep inspection runs, then to exactly one terminal scan result: `clean` or `rejected`.
- **Observable Behavior**: Document status updated in database. If rejected, reason is logged to audit trail.

### 6.3 AttachDocumentsToEntity (System Command)
- **Preconditions**: Called inside a parent transaction (e.g. `SubmitOrder` or `SubmitTopUp`).
- **Public Contract**: `OwnedDocuments::assertCleanOwned(array $documentIds, string $ownerId, array $requiredClassifications): array` locks and returns immutable document references; the owning workflow attaches those references to its target within the same transaction.
- **Inputs**: Account ID, list of opaque Document IDs, required classification per reference, Target Entity Type, Target Entity ID.
- **Expected Outcome**: Documents verified to belong to Account ID and to be in `clean` status; transitioned to `attached`.
- **Failure Behavior**: If any document is not `clean`, does not belong to the account, or has already been attached to an incompatible record, checkout aborts with `document.invalid_attachment`.

### 6.4 StreamDocumentContent
- **Preconditions**: Caller is authorized (either the owning customer, or staff member with `documents.view_sensitive`).
- **Inputs**: Document ID.
- **Expected Outcome**: File streamed with security headers (`Content-Disposition: attachment; filename="..."`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`).
- **Observable Behavior**: Binary file stream delivered to client.
- **Authorization**: Ownership check (`account_id == document.account_id`) or staff ability check. Unauthorized access returns HTTP 403 or HTTP 404.

### 6.5 PruneOrphanDocuments (System Scheduled Job)
- **Preconditions**: Scheduled maintenance job (hourly).
- **Expected Outcome**: Identifies documents in `clean` or `pending_scan` status that have remained unattached for > 24 hours; deletes underlying storage blobs atomically and marks records expunged.
- **Invariants**: Will NEVER delete a document in `attached` status.

---

## 7. Business Rules

1. **Magic Bytes Validation**:
   - PDF: First 4 bytes must be `%PDF` (`0x25 0x50 0x44 0x46`).
   - JPEG: First 3 bytes must be `0xFF 0xD8 0xFF`.
   - PNG: First 8 bytes must be `0x89 0x50 0x4E 0x47 0x0D 0x0A 0x1A 0x0A`.
   Any mismatch between declared MIME type and magic bytes records a rejection reason and completes the quarantine pipeline as `rejected`.
2. **Race Condition Protection**: Attachment locks the document record with `SELECT FOR UPDATE` within the checkout transaction. The orphan pruning job checks attachment status within a transaction, preventing a file verified during checkout from being purged mid-flight.

---

## 8. Edge Cases

- **User Uploads File and Immediately Submits Order**: If status remains `pending_scan` or `quarantined`, checkout returns `document.verification_pending`; it does not wait inside the purchase transaction. The client polls and retries after a terminal scan result.
- **Tampered Extension**: A user renames an executable `.exe` or `.sh` script to `.png`. The magic bytes validator immediately detects non-PNG header bytes and flags the document as `rejected`.

---

## 9. Failure Behavior

- **File Too Large**: HTTP 413 Payload Too Large, code `document.file_too_large`.
- **Unsupported Type**: HTTP 422, code `document.unsupported_type`. Localized message: `"Only PDF, JPEG, and PNG files are allowed."`
- **Infected or Corrupt File**: HTTP 422, code `document.verification_failed`. Localized message: `"The uploaded file could not be verified or is corrupt."`

---

## 10. Cross-Domain Interactions

- **Top-Ups Domain**: Bank transfer receipts are uploaded through this domain, verified clean, and attached to top-up requests.
- **Application Forms Domain**: Declares file/image answer shapes and classifications without reading document state.
- **Purchasing & Orders Domain**: Validates and attaches required documents atomically during order submission.
- **Audit Domain**: Document rejections and staff accesses to private documents are recorded.
