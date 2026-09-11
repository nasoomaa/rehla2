# Contract: Administrative Control Plane Operations

## 1. Responsibility

This contract governs the boundary between the Administrative Control Plane (e.g. Filament Admin Panel) and the underlying domain packages. It defines the operational rules for administrative browsing, field-level sensitivity masking, authorization gates, Multi-Factor Authentication (MFA), and strictly enforces that presentation layers never execute raw database mutations or bypass domain invariants.

---

## 2. Producer and Consumer

- **Producer**: Business Domain Packages (`Identity`, `Catalog`, `Forms`, `Travelers`, `Documents`, `Wallet`, `TopUps`, `Orders`, `Fulfillment`, `Audit`, `Reporting`).
- **Consumer**: Administrative Presentation Layer (`packages/Rehla/Admin`).

---

## 3. Core Architectural Rules

1. **Read-Only Models for Browsing**: Administrative list and detail views bind exclusively to read-only models (`Contracts/ReadModels`). These models strictly forbid `save()`, `update()`, `delete()`, or relationship mutation.
2. **Explicit Domain Actions for State Changes**: Any administrative modification (e.g. approving a transfer, changing an execution status, publishing a form) must invoke an explicit Domain Action DTO. The admin presentation layer is strictly prohibited from executing direct Eloquent updates or SQL write statements.
3. **Immutability of Historical Entities**: Administrative UI components must not render "Edit" or "Delete" actions for:
   - Wallet Ledger Entries (`wallet_ledger_entries`).
   - Commercial Orders and Snapshots (`orders`).
   - Published Form Versions (`form_versions`).
   - Audit Log Entries (`audit_entries`).
4. **Deny-by-Default and Least Privilege**: Every administrative page, resource, widget, and action is gated by an explicit Ability. Possession of a staff account grants zero access unless abilities are assigned.
5. **Mandatory TOTP MFA for Critical Actions**: Executing high-risk operations (top-up review, role assignment, audit viewing) requires an active session verified with Time-based One-Time Password (TOTP) MFA within the last 4 hours.

---

## 4. Administrative Sections and Ability Matrix

| Section / Resource | Required View Ability | Modification Commands | Sensitive Fields & Masking |
|---|---|---|---|
| **Overview** | `admin.overview.view` | None (Read-only analytics) | Aggregated KPIs only |
| **Services** | `services.view` | `CreateService`, `UpdatePrice`, `PublishService`, `DeactivateService` | Pricing history requires `services.manage` |
| **Application Forms** | `forms.view` | `CreateDraft`, `UpdateDraft`, `PublishVersion` | Schema editor requires `forms.draft` / `forms.publish` |
| **Customers** | `customers.view` | `SuspendCustomer`, `ReactivateCustomer` | Contact details allowlisted; requires `customers.view_sensitive` |
| **Travelers** | `travelers.view` | None (No direct profile editing by staff) | Passport number masked as `P*****567` unless `travelers.view_sensitive` |
| **Wallets & Ledger** | `wallets.view` | Compensating adjustment command only | Ledger entries immutable; no edit/delete buttons |
| **Bank Accounts** | `banks.view` | `CreateBankAccount`, `UpdateBankAccount`, `ToggleStatus` | Internal banking notes restricted to `banks.manage` |
| **Top-Up Requests** | `topups.view` | `ApproveTopUp`, `RejectTopUp` | Transfer receipt requires `documents.view_sensitive`; MFA required |
| **Orders** | `orders.view` | None (Permanent commercial records) | Order snapshots permanently immutable |
| **Service Executions**| `executions.view` | `TransitionStatus`, `RequestCustomerAction`, `CompleteExecution`, `CancelExecution`, `AddInternalNote` | Attached documents gated by policy; internal notes hidden from customers |
| **Content** | `content.view` | `CreatePage`, `UpdatePage`, `PublishPage` | Markdown / Rich Text sanitization enforced |
| **Notifications** | `notifications.view` | `ReplayOutboxMessage` | Recipient PII masked unless `notifications.manage` |
| **Roles & Permissions**| `access.view` | `AssignRole`, `UpdateAbilities` | Requires MFA; super-admin role self-revocation prevented |
| **Audit Log** | `audit.view` | None (Strictly read-only) | Requires MFA; sensitive payload fields masked |

---

## 5. Administrative Operational Commands

### 5.1 ApproveTopUp
- **Consumer Action**: Admin clicks "Approve Transfer" on Top-Up detail modal.
- **Contract Invocation**: Calls `TopUps\Actions\ApproveTopUp`.
- **Inputs**: `top_up_id`, `reviewer_id` (from staff session), `correlation_id`.
- **Enforcement**:
  - Checks staff has `topups.review` and active MFA.
  - Domain action executes row lock and calls Wallet domain credit.
  - If successful: returns DTO with approval timestamp and updated wallet balance.
  - If already approved: returns idempotency confirmation.
- **Audit Side-Effect**: Invariant requires appending to `audit_entries`.

### 5.2 RejectTopUp
- **Consumer Action**: Admin clicks "Reject Transfer", opens modal.
- **Contract Invocation**: Calls `TopUps\Actions\RejectTopUp`.
- **Inputs**: `top_up_id`, `reviewer_id`, `rejection_reason` (mandatory, min 10 characters), `correlation_id`.
- **Enforcement**:
  - Asserts `rejection_reason` is non-empty.
  - Domain updates status to `rejected`, records reason.
  - Appends to `audit_entries`.

### 5.3 TransitionExecution
- **Consumer Action**: Staff moves execution case to next stage (e.g. `processing` to `completed`).
- **Contract Invocation**: Calls `Fulfillment\Actions\TransitionStatus` or specialized command (`CompleteExecution`, `RequestCustomerAction`, `CancelExecution`).
- **Inputs**: `execution_id`, `target_status`, `staff_id`, `notes_or_reason`, `attached_document_id` (if completing).
- **Enforcement**: Asserts transition is valid per the 7-state lifecycle state machine. Rejects invalid transitions with clear error.

---

## 6. Document Viewing and Masking Protocol

1. **Private Document Access**:
   When an administrator clicks to inspect a passport scan, national ID, or bank receipt:
   - The Admin panel does NOT render a public S3/CDN link.
   - It requests a signed, temporary streaming token via `Documents\Contracts\GetSecureDownloadUrl`.
   - The token has a maximum lifetime of 15 minutes and is tied to the requesting staff member's IP and session.
   - File is delivered with `Content-Disposition: inline` and `X-Content-Type-Options: nosniff`.
2. **Passport Number Masking**:
   In list views and tables, passport numbers appear masked:
   - Raw: `P01234567`
   - Masked: `P*****567`
   Unmasking requires an explicit click and possession of `travelers.view_sensitive`.

---

## 7. Error Handling and Problem Details

The admin panel intercepts domain exceptions and converts them into structured user-friendly notifications:
- If a review collision occurs (e.g. another reviewer approved the top-up 2 seconds earlier):
  - Catches `TopUpAlreadyDecidedException`.
  - Refreshes resource view.
  - Displays alert: `"This request has already been approved by [Reviewer Name]."`
- If an invalid execution transition is attempted:
  - Catches `InvalidExecutionTransitionException`.
  - Displays modal error: `"Cannot transition execution from [Current Status] to [Target Status]."`
