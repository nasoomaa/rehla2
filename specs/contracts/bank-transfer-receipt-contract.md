# Contract: Bank Transfer Verification and Receipt Proof

## 1. Responsibility

This contract defines the operational protocol and data contract for customer-submitted bank transfers and receipt proofs. Because Sudanese commercial banking systems operate without programmatic open banking APIs in Phase 1, this contract formalizes the verification workflow between customer-submitted payment claims and administrative verification.

---

## 2. Producer and Consumer

- **Producer**: Customer (via Web or REST API) submitting transfer claims and receipt files.
- **Consumer**: Top-Ups Domain (`packages/Rehla/TopUps`), Operations Review Staff, Wallet Domain.

---

## 3. Submission Data Contract

When a customer submits proof of an out-of-band bank transfer:

### Input Payload:
```json
{
  "bank_account_id": 2,
  "amount_minor": 5000000,
  "transaction_reference": "TXN-987654321",
  "receipt_document_id": "doc-uuid-101"
}
```

### Field Semantics:
- `bank_account_id`: Integer ID of the destination platform bank account selected by the customer. Must reference an `active` account.
- `amount_minor`: Integer minor units of SDG (scale 100). Minimum value: `500,000` (equivalent to 5,000.00 SDG).
- `transaction_reference`: The unique alphanumeric transaction identifier issued by the customer’s commercial banking application (e.g. Bank of Khartoum / Bankak, O-Cash, Fawry).
- `receipt_document_id`: The ID of a private document previously uploaded and marked `clean` via the Documents domain. Must be an image (JPEG/PNG) or PDF.

---

## 4. Normalization and Uniqueness Contract

To prevent fraudulent double-spending or accidental resubmission:
1. **Normalization Function**:
   - `normalized_reference = UPPER(REGEXP_REPLACE(transaction_reference, '[^A-Za-z0-9]', '', 'g'))`
   - Example: `" bok-987 654-321 "` ──► `"BOK987654321"`
2. **Database Constraint**:
   ```sql
   CONSTRAINT uq_bank_account_reference UNIQUE (bank_account_id, normalized_reference)
   ```
   Any subsequent top-up submission attempting to use the same normalized reference for the same bank account is rejected immediately.

---

## 5. Administrative Verification Checklist

Before approving a top-up request, the reviewing staff member must execute this standard operational verification protocol:

1. **Beneficiary Bank Matching**:
   Verify that the receipt clearly displays the platform bank's account number and beneficiary name as the destination.
2. **Amount Verification**:
   Verify that the numerical transfer amount on the receipt image exactly matches `amount_minor / 100`.
3. **Reference Verification**:
   Verify that the transaction reference number visibly printed on the receipt matches `transaction_reference`.
4. **Timestamp Plausibility**:
   Verify that the receipt timestamp is recent (within 72 hours of submission).
5. **Internal Statement Reconciliation**:
   Staff cross-references their internal online banking portal or commercial statement to confirm the funds actually cleared into the platform's bank account.

---

## 6. Review Decisions and Atomic Contracts

### 6.1 Approval Outcome:
- Top-Up status transitions to `approved`.
- `TopUps` invokes `Wallet\Contracts\CreditWallet`:
  - `amount_minor`: exactly the approved amount.
  - `reference_type`: `'top_up_approval'`.
  - `reference_id`: `top_up_request.id`.
- Wallet balance increases by `amount_minor`.
- Customer receives `TopUpApproved` notification.

### 6.2 Rejection Outcome:
- Top-Up status transitions to `rejected`.
- Staff must select or type an explicit rejection reason:
  - `receipt_unreadable`: `"The uploaded receipt image is blurry or unreadable."`
  - `amount_mismatch`: `"The amount on the receipt does not match the claimed amount."`
  - `funds_not_received`: `"Transfer could not be confirmed in the platform bank account."`
  - `invalid_reference`: `"The reference number does not correspond to a valid transfer."`
- Wallet balance is NOT changed.
- Customer receives `TopUpRejected` notification with rejection reason.

---

## 7. Error Semantics

- **Below Minimum Threshold**: HTTP 422 with code `top_up.below_minimum`.
- **Duplicate Bank Reference**: HTTP 422 with code `top_up.reference_used`. Message: `"This bank transaction reference number has already been used."`
- **Receipt Unverified or Missing**: HTTP 422 with code `top_up.receipt_required`.
- **Target Bank Account Inactive**: HTTP 422 with code `top_up.bank_account_inactive`.
