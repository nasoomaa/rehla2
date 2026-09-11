# Domain: Fulfillment and Service Execution

## 1. Purpose

The Fulfillment and Service Execution domain owns the operational processing lifecycle of purchased travel services. It tracks application processing, document verification, staff operational notes, formal customer action requests, and final permit delivery (e.g. issued electronic visas), completely decoupled from financial records.

---

## 2. Actors

- **Customer**: Tracks the real-time operational status of their purchased services; receives alerts when action is required; submits requested additional documents or clarifications.
- **Fulfillment Officer (Staff)**: Opens execution cases, reviews applicant details and uploaded files, updates processing statuses, logs operational notes, requests customer actions, attaches approved permits/visas, and completes or cancels executions.
- **System**: Enforces valid state machine transitions, records chronological status changelogs, and dispatches status notification events.

---

## 3. Concepts

- **Service Execution Record**: The operational case file representing fulfillment of a purchased service. Contains:
  - Execution ID.
  - Commercial Order ID (1-to-1 linkage).
  - Account ID.
  - Service ID.
  - Form Version ID used.
  - Form Responses (submitted key-value data).
  - Attached Document IDs (application files).
  - Current Status (one of the 7 standard lifecycle states).
  - Last Status Change Timestamp.
  - Assigned Staff Member ID (optional).
  - Final Issued Document ID (e.g. visa PDF upon completion).
  - Created Timestamp (UTC).
- **Execution Status History (Changelog)**: An append-only audit trail recording every status change: `from_status`, `to_status`, `changed_by_actor_id`, `reason_or_notes`, and UTC timestamp.
- **Customer Action Request**: An operational item created by staff requiring customer intervention (e.g. "Passport copy was blurry; please upload a clear scan"). Tracks:
  - Request ID.
  - Execution ID.
  - Description / Instructions (EN/AR).
  - Requested Document Type (optional).
  - Status (`open`, `resolved`).
  - Created Timestamp.
  - Resolved Timestamp.
- **Internal Operational Notes**: Private notes written by fulfillment staff for internal collaboration, strictly hidden from the customer.

---

## 4. Invariants

1. **Decoupling from Financial State**: Operational fulfillment status is never mixed with financial payment status. The order is permanently `paid`, while execution independently transitions through operational states.
2. **Deterministic State Machine**: Status transitions must adhere strictly to the allowed state graph. Arbitrary or backward status jumps are strictly forbidden.
3. **Mandatory Action Request Rationale**: Transitioning to `action_required` requires an explicit, non-empty customer-facing instruction describing what action is needed.
4. **Automatic Resume on Response**: When a customer submits the requested information or document for an open action request, the execution status must automatically transition to `action_received`.
5. **No Automated Refunds on Cancellation**: In Phase 1, transitioning an execution to `cancelled` is an operational termination only. It does NOT trigger an automated wallet refund. Any financial compensation is resolved manually out-of-band.
6. **Internal Notes Privacy**: Internal operational notes must never be leaked through customer APIs, customer web interfaces, or customer notifications.

---

## 5. State Model

### Standard 7-State Execution Lifecycle
```text
           [Order Paid]
                 │
                 ▼
              Received
                 │
                 ▼
            Under Review ◄───────────────┐
                 │                       │
                 ▼                       │
             Processing                  │
                 │                       │
                 ├──────────────┐        │
                 │              ▼        │
                 │       Action Required │
                 │              │        │
                 │              ▼        │
                 │       Action Received ┘
                 │
                 ├───────────────────────────────┐
                 ▼                               ▼
             Completed                       Cancelled
      (Terminal Success;               (Terminal Abort;
       Issued visa attached)            Audit reason required)
```

### Valid Transitions:
- `received` ──► `under_review`
- `under_review` ──► `processing`
- `under_review` ──► `action_required`
- `processing` ──► `action_required`
- `action_required` ──► `action_received`
- `action_received` ──► `processing` OR `under_review`
- `under_review`, `processing`, or `action_received` ──► `completed`
- Any non-terminal state (`received`, `under_review`, `processing`, `action_required`, `action_received`) ──► `cancelled`

`completed` and `cancelled` are terminal states; no further transitions are permitted once reached.

---

## 6. Commands and Actions

### 6.1 CreateExecution (Internal Contract Command)
- **Preconditions**: Called within the atomic `SubmitOrder` transaction by Purchasing domain.
- **Inputs**: Order ID, Account ID, Service ID, Form Version ID, Form Answers, Document IDs.
- **Expected Outcome**: New execution record created with initial status `received`; initial status changelog entry appended.
- **Observable Behavior**: Execution appears on customer’s active orders list and staff fulfillment queue.

### 6.2 TransitionStatus (Staff Command)
- **Preconditions**: Staff member has `executions.transition` ability; target transition is valid per state machine.
- **Inputs**: Execution ID, Target Status, Operational Reason / Notes.
- **Expected Outcome**:
  - Execution row locked.
  - Asserts transition from current status to target status is valid.
  - Updates status and `updated_at`.
  - Appends record to `ExecutionStatusHistory`.
  - Appends entry to Audit log.
  - Dispatches `ExecutionStatusChanged` notification to Outbox.
- **Observable Behavior**: Immediate update of execution status on customer portal; customer receives notification.
- **Failure Behavior**: Invalid transition returns HTTP 422 with code `execution.invalid_transition`.

### 6.3 RequestCustomerAction (Staff Command)
- **Preconditions**: Execution is in `under_review` or `processing`; staff has `executions.transition`.
- **Inputs**: Execution ID, Action Instructions (EN/AR), Expected Document Type (optional).
- **Expected Outcome**:
  - Creates open `CustomerActionRequest`.
  - Transitions execution status to `action_required`.
  - Dispatches `CustomerActionRequested` notification to Outbox.
- **Observable Behavior**: Order card on customer dashboard prominently displays "Action Required From You" with staff instructions.

### 6.4 SubmitCustomerActionResponse (Customer Command)
- **Preconditions**: Customer is authenticated; execution belongs to customer; execution is in `action_required`; open action request exists.
- **Inputs**: Execution ID, Action Request ID, Customer Notes / Text Response (optional), Replacement / Additional Document ID (clean document).
- **Expected Outcome**:
  - Marks `CustomerActionRequest` as `resolved`.
  - Transitions execution status to `action_received`.
  - Dispatches `CustomerActionReceived` notification to Outbox.
- **Observable Behavior**: Status immediately reflects "Requested Action Received"; fulfillment staff queue alerted.
- **Authorization**: Scoped to owning customer.

### 6.5 CompleteExecution (Staff Command)
- **Preconditions**: Staff has `executions.transition`; execution is in `under_review`, `processing`, or `action_received`; issued document (e.g. visa PDF) uploaded and verified clean.
- **Inputs**: Execution ID, Issued Document ID, Completion Notes.
- **Expected Outcome**:
  - Links issued document to execution.
  - Transitions status to `completed`.
  - Dispatches `ExecutionCompleted` notification to Outbox.
- **Observable Behavior**: Customer receives completion alert; customer can download issued visa PDF. Order marked "Completed".

### 6.6 CancelExecution (Staff Command)
- **Preconditions**: Staff has `executions.transition`; execution is not in a terminal state (`completed` or `cancelled`).
- **Inputs**: Execution ID, Mandatory Cancellation Reason.
- **Expected Outcome**:
  - Status transitioned to `cancelled`.
  - Appends audit log entry with staff ID and cancellation rationale.
  - Dispatches `ExecutionCancelled` notification to Outbox.
  - Wallet balance is NOT modified (manual refund process applies).
- **Validation Rules**: Cancellation reason mandatory (min 15 chars).

### 6.7 AddInternalNote (Staff Command)
- **Preconditions**: Staff has `executions.note`.
- **Inputs**: Execution ID, Note Content.
- **Expected Outcome**: Internal note created and linked to execution.
- **Invariants**: Notes are strictly hidden from customer queries.

---

## 7. Business Rules

1. **Customer Action Banner**: Whenever an execution is in `action_required`, the customer interface must display a prominent actionable banner:
   - English: `"Action Required From You"`
   - Arabic: `"مطلوب إجراء منك"`
   alongside the exact instructions entered by staff.
2. **Issued Document Access**: The final issued document (e.g. visa PDF) is stored on the private disk, linked to the execution record, and made accessible to the customer via authorized streaming or signed temporary URLs upon reaching `completed` status.

---

## 8. Edge Cases

- **Customer Submits Multiple Files for Action Request**: If an action request requires multiple files, the customer uploads them through the Documents domain, and passes the array of `clean` document IDs to `SubmitCustomerActionResponse`.
- **Staff Attempts to Cancel Completed Execution**: An execution in `completed` status cannot be transitioned to `cancelled`. Command fails with `execution.already_terminal`.

---

## 9. Failure Behavior

- **Invalid Transition**: HTTP 422, code `execution.invalid_transition`. Message: `"Cannot transition execution from [current] to [target]."`
- **No Action Pending**: If customer submits response when status is not `action_required`, returns HTTP 409 with `execution.action_not_pending`.
- **Missing Issued Document on Completion**: If completing an execution without attaching the required official document, returns HTTP 422 with `execution.missing_issued_document`.

---

## 10. Cross-Domain Interactions

- **Orders & Purchasing Domain**: Orders instantiate service execution records upon checkout commit.
- **Documents Domain**: Application attachments, customer response files, and final issued visas are stored and verified clean via Documents.
- **Notifications Domain**: Every status change emits an Outbox event for customer notification.
- **Audit Domain**: Every operational status transition, note addition, and cancellation reason is recorded.
