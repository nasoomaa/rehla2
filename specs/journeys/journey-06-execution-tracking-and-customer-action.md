# Journey 06: Execution Tracking and Customer Action Exchange

## 1. Goal

An authenticated customer monitors the operational fulfillment of their purchased service, responds to an administrative request for clarification or replacement documents, and successfully downloads their issued travel document (e.g. electronic visa PDF) upon completion.

---

## 2. Actors

- **Customer (Account Owner)**
- **Fulfillment Officer (Staff)**
- **Fulfillment Domain**
- **Documents Domain**
- **Notifications Domain**
- **Audit Domain**

---

## 3. Preconditions

1. A Commercial Order (`ORD-202609-1001`) was successfully purchased and paid.
2. An associated Service Execution case (`exec-9988`) exists in status `received`.
3. Fulfillment Officer has an active administrative session with `executions.transition` ability.

---

## 4. Trigger

Fulfillment Officer claims execution `exec-9988` from the operational fulfillment backlog.

---

## 5. Complete Happy-Path Sequence

### Phase 1: Operational Review and Action Request
1. **Fulfillment officer opens case**:
   - Staff navigates to **Service Executions** in Admin Panel.
   - Selects execution `exec-9988` (UAE Visa for Ahmed Mohammed Osman).
   - Staff reviews submitted answers and personal photograph.
2. **Staff transitions to processing**:
   - Staff transitions status from `received` to `under_review`, then to `processing`.
   - System updates `service_executions.status = 'processing'` and appends changelog.
3. **Staff identifies deficiency in submitted photo**:
   - Staff notes that the applicant's photo has a dark background that does not meet UAE immigration specifications.
4. **Staff initiates Customer Action Request**:
   - Staff clicks **"Request Customer Action"**.
   - Form inputs:
     - Instructions (EN): `"The uploaded personal photo has a dark background. Please upload a clear photo taken against a solid white background."`
     - Instructions (AR): `"الصورة الشخصية المرفقة بخلفية داكنة. يرجى رفع صورة واضحة بخلفية بيضاء نقية."`
     - Expected Attachment Type: `image_upload`.
   - Staff submits the action request.
5. **System transitions status and alerts customer**:
   - Inserts open record into `customer_action_requests`.
   - Transitions `service_executions.status = 'action_required'`.
   - Appends status history entry and writes to `audit_entries`.
   - Creates the in-app request atomically and enqueues external delivery when enabled.
   - The customer sees the in-app notification:
     `"Action required for order ORD-202609-1001: The uploaded personal photo has a dark background..."`

---

### Phase 2: Customer Response
6. **Customer inspects order status**:
   - Customer opens Rehla portal or clicks notification.
   - Navigates to `/account/orders/ORD-202609-1001`.
   - The UI renders an alert banner:
     - Header: **"Action Required From You"** / **"مطلوب إجراء منك"**
     - Description: `"The uploaded personal photo has a dark background. Please upload a clear photo taken against a solid white background."`
     - File Upload input: `"Upload Replacement Photo"`.
7. **Customer uploads compliant photo**:
   - Customer selects new photo `photo_white_bg.jpg`.
   - Documents domain verifies JPEG magic bytes, validates size < 10MB, saves to private disk, and returns `document_id = "doc-photo-clear-102"` in `clean` status.
8. **Customer submits response**:
   - Customer clicks **"Submit Requested Information"**.
   - Client sends `POST /api/v1/executions/exec-9988/actions/act-1/responses` with `document_id = "doc-photo-clear-102"`.
9. **System processes customer response**:
   - Marks action request `act-1` as `resolved`.
   - Transitions `service_executions.status = 'action_received'`.
   - Marks document `doc-photo-clear-102` as `attached`.
   - Appends status history entry (`action_required -> action_received`).
   - Creates the in-app acknowledgement atomically and enqueues external delivery when enabled.
   - Customer interface displays: `"Requested Action Received. Our team is processing your order."`

---

### Phase 3: Final Fulfillment and Completion
10. **Staff verifies replacement document**:
    - Staff sees case `exec-9988` badge updated to `Action Received`.
    - Staff inspects new photo, confirms solid white background, and transitions status to `processing`.
11. **Staff issues electronic visa**:
    - Staff completes visa issuance via external immigration portal.
    - Staff receives official PDF visa document from UAE immigration.
    - Staff uploads the visa PDF (`uae_visa_ahmed.pdf`) to Admin panel.
    - Documents domain stores file in private disk with classification `issued_document` (`document_id = "doc-visa-final-99"`).
12. **Staff marks case completed**:
    - Staff clicks **"Complete Execution"**, selecting `doc-visa-final-99` as the issued document.
13. **System transitions case to completed**:
    - Updates `service_executions`:
      - `status = 'completed'`
      - `issued_document_id = "doc-visa-final-99"`
      - `completed_at = NOW()`
    - Appends final status history entry (`completed`).
    - Appends audit log entry.
    - Creates the in-app completion notification atomically and enqueues external-channel delivery when enabled.
14. **Customer receives final delivery**:
    - Customer receives notification: `"Your order ORD-202609-1001 is complete! Your UAE Visa is ready for download."`
    - Customer opens `/account/orders/ORD-202609-1001`.
    - Order badge displays **"Completed"**.
    - Customer clicks **"Download Issued Visa (PDF)"**.
    - System delivers authorized stream with `Content-Disposition: attachment; filename="UAE_Visa_Ahmed_Osman.pdf"`.

---

## 6. Alternate Paths

### Path 6A: Direct Completion Without Action Request
1. Staff reviews original submission, finds all documents compliant.
2. The captured policy has `requires_issued_document = true`, so staff supplies a clean `issued_document` before completion.
3. Execution transitions directly: `received -> under_review -> processing -> completed`.
4. Customer receives completion notification without entering `action_required`.

### Path 6B: Completion for a Service Without an Issued Document
1. The captured immutable policy has `requires_issued_document = false`.
2. Staff transitions an eligible execution to `completed` without `issued_document_id`.
3. The result is deterministically successful; the completion notification contains no download action and no document link is fabricated.

### Path 6C: Required Issued Document Is Missing
1. The captured immutable policy has `requires_issued_document = true`.
2. Staff attempts completion without `issued_document_id`, or supplies a document that is not clean and classified `issued_document`.
3. The command returns HTTP 422 with `execution.missing_issued_document`; status, history, Audit, notification, and Outbox remain unchanged.

---

## 7. Failure Paths

### Path 7F-1: Customer Attempts Response Without Open Action Request
1. Customer attempts to call `POST /executions/{id}/actions/{id}/responses` when status is `processing` (no action pending).
2. System rejects request with HTTP 409 Conflict and code `execution.action_not_pending`.

### Path 7F-2: Administrative Cancellation (Operational Termination)
1. Staff discovers that the traveler is barred from entry by UAE authorities.
2. Staff clicks **"Cancel Execution"** and enters mandatory reason: `"Rejected by UAE immigration authority due to active travel restriction."`
3. System transitions status to `cancelled`, records audit log entry, and alerts customer.
4. **Financial Invariant**: Wallet balance is NOT automatically refunded. Operational cancellation terminates the execution case without modifying financial ledger entries.

---

## 8. Recovery Behavior

- If the customer's replacement photo is also rejected, staff can trigger another `RequestCustomerAction` with revised guidance.
- While retention permits, the customer can request a fresh authorized short-lived download from order history; no permanent private link is stored or exposed.

---

## 9. Final Observable State

1. **Database State**:
   - `service_executions`: status `completed`, `issued_document_id` set, `completed_at` set.
   - `execution_status_history`: complete chronological changelog (`received` -> `under_review` -> `processing` -> `action_required` -> `action_received` -> `processing` -> `completed`).
   - `audit_entries`: complete audit trail with staff IDs.
2. **User State**:
   - Customer holds the official issued visa PDF in their local device storage.

---

## 10. Cross-Domain Dependencies

- **Fulfillment**: Owns the 7-state lifecycle and action request loop.
- **Documents**: Manages replacement photo uploads and issued visa PDF streaming.
- **Notifications**: Alerts customer upon action requests and completion.
- **Audit**: Records all status transitions and staff identities.

---

## 11. External-System Interactions

- Staff uses the external immigration authority portal manually. Rehla has no Phase 1 programmatic integration or transaction coupling with it; staff attaches the returned official output through the Documents contract.
