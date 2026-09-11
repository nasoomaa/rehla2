# Contract: Storage and Document Pipeline

## 1. Responsibility

This contract defines the interface and lifecycle guarantees for physical storage partitioning (private vs. public disks), file sanitization, virus/malware inspection, magic byte verification, authorized download streaming, and retention management across the Rehla platform.

---

## 2. Producer and Consumer

- **Producer**: Documents Domain (`packages/Rehla/Documents`).
- **Consumers**:
  - `TopUps`: Bank transfer receipts.
  - `Forms` & `Purchasing`: Application form uploads (passports, photos, statements).
  - `Fulfillment`: Customer action submissions and final issued visas/permits.
  - `Catalog`: Marketing banners and service promotional images (public).
  - `Web`, `Api`, `Admin`: Upload endpoints and secure streaming viewers.

---

## 3. Storage Disks and Boundaries

| Disk Identifier | Visibility | Target Content | Direct URL Access | Delivery Protocol |
|---|---|---|---|---|
| `private` | Strictly Private | Passports, IDs, Receipts, Application Attachments, Issued Visas | Forbidden | Authorized Stream or Signed Temporary URL (<= 15 min) |
| `public` | Public CDN / Web | Service marketing banners, Bank logos | Permitted | Direct CDN URL with cache headers |

---

## 4. File Specifications and Quotas

| Classification | Allowed MIME Types | Allowed Extensions | Max File Size | Magic Bytes Verification |
|---|---|---|---|---|
| `passport_scan` | `application/pdf`, `image/jpeg`, `image/png` | `.pdf`, `.jpg`, `.jpeg`, `.png` | 10 MiB (image) / 20 MiB (PDF) | Checked against file headers |
| `bank_receipt` | `application/pdf`, `image/jpeg`, `image/png` | `.pdf`, `.jpg`, `.jpeg`, `.png` | 10 MiB (image) / 20 MiB (PDF) | Checked against file headers |
| `applicant_photo`| `image/jpeg`, `image/png` | `.jpg`, `.jpeg`, `.png` | 10 MiB | Checked against file headers |
| `identity_document`, `supporting_document`, `issued_document` | `application/pdf`, `image/jpeg`, `image/png` | `.pdf`, `.jpg`, `.jpeg`, `.png` | 20 MiB | Checked against file headers |
| `service_media` | `image/jpeg`, `image/png` (public) | `.jpg`, `.jpeg`, `.png` | 5 MiB | Standard image check |
| `bank_logo` | `image/jpeg`, `image/png` (public) | `.jpg`, `.jpeg`, `.png` | 5 MiB | Standard image check |

---

## 5. Scanning and Sanitization Protocol

Every file, including staff-managed public media, enters the scanning pipeline before publication or attachment:

```text
Upload ──► Private Staging ──► Magic Byte Verification ──► Image Decoder / Antivirus
                                         │                              │
                                   Mismatch: Reject               Infected: Reject
                                         │                              │
                                         └──────────────┬───────────────┘
                                                        ▼
                                                  Status: Clean
```

1. **Magic Bytes Check**:
   - `PDF`: File must begin with `%PDF-` (`0x25, 0x50, 0x44, 0x46, 0x2D`).
   - `JPEG`: File must begin with `0xFF, 0xD8, 0xFF`.
   - `PNG`: File must begin with `0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A`.
   - Any extension mismatch (e.g. executable renamed to `.png`) fails immediately.
2. **Image Stream Verification**:
   - For JPEG and PNG: Validates that image decompression succeeds without memory corruption or truncated streams. Strips EXIF metadata containing GPS/camera metadata before marking clean.
3. **Malware / Antivirus Signature**:
   - Scans against ClamAV / signature database. Any match transitions file to `rejected` and isolates it in quarantine.

---

## 6. Retention and Pruning Rules

- **Orphan Pruning (24-Hour Rule)**:
  - Any document in `clean` or `pending_scan` status that remains unattached to a parent entity (TopUp, Order, Execution) for more than 24 hours is considered abandoned.
  - The hourly scheduler deletes the storage blob and marks the database record purged.
- **Rejected File Retention (30-Day Audit Rule)**:
  - Files marked `rejected` are isolated in a quarantine bucket for exactly 30 days to facilitate security analysis and abuse tracking, after which they are permanently expunged.
- **Attached Document Retention**:
  - Once attached, a document can never be deleted by the orphan cleaner. Its retention follows the parent policy; Phase 1 retains it indefinitely until an approved retention policy says otherwise.

---

## 7. Secure Streaming and Delivery Contract

When an authorized consumer requests access to a private document:

### Internal Contract Method:
```php
DocumentStreamResponse getAuthorizedStream(string $documentId, AuthenticatedActor $actor);
```

### Security Headers Enforced on Output:
```http
HTTP/1.1 200 OK
Content-Type: application/pdf
Content-Disposition: attachment; filename="document-a1b2c3.pdf"
Content-Security-Policy: default-src 'none'
X-Content-Type-Options: nosniff
Cache-Control: private, no-cache, no-store, must-revalidate
Expires: 0
```

---

## 8. Failure and Error Semantics

- **Quota Exceeded**: Returns HTTP 413 Payload Too Large with code `document.file_too_large`.
- **Invalid Header / Corrupt File**: Returns HTTP 422 with code `document.verification_failed` and message: `"The uploaded file is corrupt or does not match its declared type."`
- **Unauthorized Retrieval**: Returns HTTP 404 Not Found if the document belongs to another account, or HTTP 403 Forbidden if staff lacks `documents.view_sensitive`.
