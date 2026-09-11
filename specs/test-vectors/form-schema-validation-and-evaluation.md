# Test Vectors: Application Form Schema Validation

## 1. Scope and Rule Being Proven

This suite proves the deterministic validation of customer submitted answers against published Form Version schemas across all **11 supported field types**, enforcing type safety, required/optional boundaries, option constraints, and document ownership checks.

---

## 2. Invariants Under Test

1. If `required: true`, the field must be present and non-empty.
2. If `required: false` and value is omitted or null, validation succeeds.
3. Every field answer must match its declared schema type.
4. Option-based fields (`dropdown`, `radio`) must only accept values declared in `options`.
5. Document upload fields (`file_upload`, `image_upload`) must supply a valid `document_id` belonging to the customer in `clean` status.
6. Undeclared fields (phantom fields not in the published schema) are rejected.

---

## 3. Field Types Test Vectors Table

| Case ID | Field Type | Declared Schema Constraints | Submitted Answer Payload | Expected Result | Rejection Reason / Code |
|---|---|---|---|---|---|
| **FRM-01** | `short_text` | `required: true, min: 3, max: 50` | `"Fatima Hassan"` | Valid | Meets text length constraints |
| **FRM-02** | `short_text` | `required: true, min: 3, max: 50` | `"AB"` | Invalid | `form.text_too_short` (Length 2 < min 3) |
| **FRM-03** | `short_text` | `required: false` | `null` | Valid | Optional field omitted |
| **FRM-04** | `long_text` | `required: true, max: 500` | `"Khartoum, Riyadh District, House 14"` | Valid | Valid multi-line address string |
| **FRM-05** | `email` | `required: true` | `"applicant@example.com"` | Valid | Valid RFC 5322 email string |
| **FRM-06** | `email` | `required: true` | `"not-an-email"` | Invalid | `form.invalid_email_format` |
| **FRM-07** | `phone` | `required: true, country_code: "SD"` | `"+249912345678"` | Valid | Valid E.164 Sudanese telephone |
| **FRM-08** | `phone` | `required: true` | `"12345"` | Invalid | `form.invalid_phone_format` |
| **FRM-09** | `number` | `required: true, min: 1, max: 10` | `3` (integer) | Valid | Within bounds (1 <= 3 <= 10) |
| **FRM-10** | `number` | `required: true, min: 1, max: 10` | `"three"` (string) | Invalid | `form.numeric_expected` |
| **FRM-11** | `number` | `required: true, min: 1, max: 10` | `15` | Invalid | `form.number_out_of_bounds` (15 > max 10) |
| **FRM-12** | `date` | `required: true, format: "YYYY-MM-DD"`| `"1995-10-25"` | Valid | Valid ISO 8601 calendar date |
| **FRM-13** | `date` | `required: true` | `"25/10/1995"` | Invalid | `form.invalid_date_format` (Must be ISO) |
| **FRM-14** | `dropdown` | `options: ["single", "married", "divorced"]`| `"married"` | Valid | Member of declared option list |
| **FRM-15** | `dropdown` | `options: ["single", "married", "divorced"]`| `"other"` | Invalid | `form.invalid_option_selected` |
| **FRM-16** | `radio` | `options: ["male", "female"]` | `"male"` | Valid | Member of declared radio options |
| **FRM-17** | `radio` | `options: ["male", "female"]` | `"unspecified"` | Invalid | `form.invalid_option_selected` |
| **FRM-18** | `checkbox` | `required: true (agreement)` | `true` (boolean) | Valid | Mandatory agreement accepted |
| **FRM-19** | `checkbox` | `required: true (agreement)` | `false` | Invalid | `form.mandatory_agreement_required` |
| **FRM-20** | `file_upload` | `required: true, mime: ["application/pdf"]`| `"doc-bank-statement-pdf"` | Valid | Document exists, `clean`, owned by caller |
| **FRM-21** | `file_upload` | `required: true` | `"doc-quarantined-file"` | Invalid | `form.document_not_clean` (Status != clean) |
| **FRM-22** | `file_upload` | `required: true` | `"doc-other-user-file"` | Invalid | `form.document_ownership_violation` |
| **FRM-23** | `image_upload`| `required: true, mime: ["image/jpeg", "image/png"]`| `"doc-photo-jpg"` | Valid | Image document `clean`, owned by caller |
| **FRM-24** | Undeclared | Schema contains fields A, B | Payload contains fields A, B, and `phantom_c` | Invalid | `form.undeclared_field_rejected` |

---

## 4. Checksum Integrity Vectors

Canonicalization recursively sorts object keys lexicographically, preserves array order and string contents, and encodes UTF-8 JSON without insignificant whitespace. The checksum is the full lowercase hexadecimal SHA-256 of those bytes.

| Case ID | Schema Definition | Computed SHA-256 Checksum | Submission Schema Checksum | Result |
|---|---|---|---|---|
| **CHK-01** | `{"fields":[{"key":"mother_name","required":true,"type":"short_text"}],"version":1}` | `fca3367c3c2678e6e63c8d144235c66ede00fd00495a68927c920522aa1386fc` | Same full hash | Accepted |
| **CHK-02** | Adds `"label":"Changed"` to the field | `b19bddb1eb3e970ec6fd5465ca613667af3dd9e19664f4b0c64584bb53bc34d0` | CHK-01 hash | Rejected: `form.schema_integrity_failed` |
