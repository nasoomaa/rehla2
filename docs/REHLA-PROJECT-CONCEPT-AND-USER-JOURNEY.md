# Rehla — Comprehensive Project Concept and User Journey

**Project Name:** Rehla  
**Project Type:** Integrated digital travel services platform  
**Initial Scope:** Visas, Umrah, tourist visits, and related travel services to Gulf countries  
**Primary Audience:** Sudanese users  
**Base Currency:** Sudanese Pound (SDG)  
**Primary Display Language:** English (`en`)  
**Secondary Display Language:** Arabic (`ar`)  

---

## 1. Project Concept

Rehla is a unified platform that helps Sudanese users explore travel services, view pricing and requirements, save traveler details, top up their wallet balance, submit service requests, pay from their wallet, track execution, and receive updates—all in one place.

The goal is to transform the customer journey from fragmented procedures scattered across chat conversations, bank transfers, and phone calls into a clear, organized, and trackable path.

Rehla brings together in one place:

- Service catalog and browsing.
- Pricing details and requirements.
- Family member and traveler profile management.
- Wallet and transaction history.
- Balance top-up via bank transfer.
- Direct submission for a specific service.
- Creation of an independent record for each purchase.
- Execution tracking for each service.
- Secure upload of required documents.
- Status update notifications.
- WhatsApp communication for inquiries.
- A unified admin panel to operate the platform.

---

## 2. The Problem Rehla Solves

In the traditional model, users suffer from:

- Lack of clarity regarding requirements before payment.
- The need to repeatedly inquire about prices.
- Repeatedly sending personal and family member details.
- Difficulty verifying whether a bank transfer was received.
- Absence of a clear history of past orders.
- Difficulty tracking multiple requests for different individuals.
- Reliance on chat threads as the sole reference.
- Confusion between payment status and service fulfillment status.

Rehla clearly separates:

```text
Service
Traveler
Wallet
Top-up (Balance Funding)
Order
Service Execution
```

---

## 3. Vision

To become the trusted digital platform for Sudanese people to access travel services, visas, Umrah, and related offerings, delivering a seamless and transparent experience from initial inquiry to service fulfillment.

---

## 4. User Objectives

The user must be able to:

- Find the appropriate service.
- View prices and requirements.
- Save multiple travelers.
- Check their wallet balance.
- Top up the wallet and track top-up request status.
- Submit a service request for a specific traveler.
- Know the exact amount paid.
- Track the execution status of the request.
- Know what action is required from them, if any.
- Access and review past orders.

---

## 5. Admin Objectives

The administration must be able to:

- Manage services.
- Update prices and content.
- Define requirements for each service.
- Manage dynamic application forms.
- Manage platform bank accounts.
- Review wallet top-up requests.
- Approve or reject bank transfers.
- Monitor and track orders.
- Manage service fulfillment and execution.
- Request additional documents from customers.
- Manage roles and permissions.
- Maintain an audit trail of critical operational decisions.

---

## 6. Core Components

```text
Rehla
├── Public Interface
│   ├── Home
│   ├── Services
│   ├── Service Details
│   └── WhatsApp
│
├── User Account
│   ├── Profile
│   ├── Travelers
│   ├── Wallet
│   ├── Top-up Requests
│   ├── Orders
│   └── Notifications
│
├── Services
│   ├── Price
│   ├── Images
│   ├── Requirements
│   ├── Expected Duration
│   └── Application Form
│
├── Wallet
│   ├── Balance
│   └── Transaction History
│
├── Bank Transfer
│   ├── Bank
│   ├── Transaction Reference Number
│   ├── Receipt
│   └── Review
│
├── Order
│   ├── Service
│   ├── Price at Purchase
│   ├── Traveler
│   └── Debit Transaction
│
├── Service Execution Record
│   ├── Traveler Snapshot at Submission
│   ├── Form Responses
│   ├── Documents
│   ├── Status
│   └── Update History
│
└── Admin Panel
```

---

## 7. The Service

The service is the core entity in Rehla.

Examples:

- UAE Visa.
- Saudi Arabia Visa.
- Qatar Visa.
- Bahrain Visa.
- Umrah Services.
- Tourist Visit Services.

Each service includes:

- Name.
- Short description.
- Detailed description.
- Images.
- Current price.
- Requirements.
- Expected fulfillment duration.
- Important notes.
- **Order Now** action.
- **Inquire via WhatsApp** action.

---

## 8. Service Requirements and Application Form

There is a distinction between:

### Service Requirements
Information the user reads before applying, such as passport validity rules or the requirement for a personal photo.

### Application Form
The specific data fields and documents that the user must actively fill out and upload.

Examples of supported field types:

- Short text.
- Long text.
- Email.
- Phone number.
- Number.
- Date.
- Dropdown select.
- Single choice (radio).
- Checkbox.
- File upload.
- Image upload.

For each field, the administration defines:

- Label / Name.
- Display order.
- Mandatory status (required or optional).
- Description / Helper text.
- Available options (if applicable).
- Appropriate validation rules.

---

## 9. Service Form Versioning

Modifying a service form must never alter historical orders.

Workflow:

```text
New Version
   ↓
Admin Draft
   ↓
Publish
   ↓
Used for New Orders
   ↓
When a newer version is published, the previous version is retained for history
```

Every completed order preserves the exact form version that was active when it was submitted.

---

## 10. User Account

The account contains:

```text
My Account
├── My Profile
├── Travelers
├── Wallet
├── Top-up Requests
├── My Orders
└── Notifications
```

The account owner holds the wallet and makes payments, but is not necessarily the traveler.

---

## 11. Travelers

The user can save multiple travelers:

```text
Travelers
├── Ahmed
├── Sarah
├── Mohammed
└── + Add Traveler
```

Basic traveler information:

- Full name.
- Date of birth.
- Gender.
- Passport number.
- Passport issue date.
- Passport expiry date.

There is no passport country field in the current version, and users do not need to select nationality because the current scope is dedicated to Sudanese citizens.

---

## 12. Passport Number

The passport number:

- Is mandatory.
- Is normalized before storage.
- Cannot be registered to different travelers across the platform (unique across travelers).

---

## 13. Editing a Traveler

Traveler information can be updated for use in future orders.

However, past orders do not change.

Upon successful submission of any order, an independent snapshot of traveler details as they existed at the time of submission is saved.

---

## 14. One Traveler per Order

Each successful submission represents a single service for a single traveler.

```text
User Account
├── Order #1001 → UAE Visa → Ahmed
├── Order #1002 → UAE Visa → Sarah
└── Order #1003 → UAE Visa → Mohammed
```

The same service can be ordered multiple times for different family members.

---

## 15. The Wallet

The wallet is the primary payment method in Rehla.

The user sees:

- Current balance.
- Transaction history.
- Top-up requests.
- Debits linked to orders.

Every balance change is recorded as an independent transaction ledger entry.

Example:

```text
+ 50,000 SDG   Top-up
- 25,000 SDG   Visa Request
```

Historical ledger records are never modified to hide past transactions. Any future correction appears as a new balancing transaction.

---

## 16. Wallet Top-up

Workflow:

```text
Wallet
   ↓
Top-up Balance
   ↓
Specify Amount
   ↓
Select Bank
   ↓
View Bank Account Details
   ↓
Perform Transfer Externally
   ↓
Enter Transaction Reference Number
   ↓
Upload Receipt
   ↓
Submit for Review
```

Initial minimum top-up amount:

```text
5,000 SDG
```

This can be adjusted administratively later.

---

## 17. Bank Accounts

A platform bank account displayed to the user includes:

- Bank name.
- Beneficiary name.
- Account number.
- Bank logo.
- Active / inactive status.
- Display sort order.

Deactivating a bank account does not erase historical transactions associated with it.

---

## 18. Top-up Request

A top-up request record contains:

- User.
- Amount.
- Bank.
- Transaction reference number.
- Receipt image/file.
- Submission timestamp.
- Review status.
- Reviewing staff member.
- Decision timestamp.
- Rejection reason (if rejected).

The same transaction reference number cannot be reused for the same bank account.

---

## 19. Bank Transfer Review

After submission, the request status is:

```text
Under Review
```

The administration verifies:

- Amount.
- Bank.
- Transaction reference number.
- Receipt.
- Banking information available internally.

Then:

```text
Approve
or
Reject
```

---

## 20. Approving a Transfer

Upon approval:

```text
Top-up Request
   ↓
Approved
   ↓
Credit Transaction Added
   ↓
Wallet Balance Increased
   ↓
User Notified
```

A single transfer can never credit the wallet balance more than once, even if the approval action is triggered repeatedly.

---

## 21. Rejecting a Transfer

Upon rejection:

- The wallet balance remains unchanged.
- The rejection decision is logged.
- The rejection reason is recorded.
- The user can be notified.

---

## 22. Difference Between Top-up and Payment

```text
Bank Transfer
= Wallet Top-up

Service Purchase
= Debit from Wallet
```

Bank transfers are not directly linked to commercial orders in the current version.

---

## 23. Service Details Page

The service page contains:

- Images.
- Name.
- Description.
- Current price.
- Requirements.
- Expected duration.
- Notes.
- **Order Now**.
- **Inquire via WhatsApp**.

---

## 24. WhatsApp

The WhatsApp button is dedicated to inquiries.

It can open a pre-filled chat message containing the service name.

Clicking WhatsApp:

- Does not create an order.
- Does not debit any balance.
- Does not create an execution record.
- Is not considered a purchase.

---

## 25. Order Now

Clicking **Order Now** initiates the official purchase flow.

The wallet balance can be pre-checked to improve the user experience.

If insufficient:

```text
Insufficient Balance
   ↓
Top up Wallet
```

This check is preliminary only, not the final payment execution decision.

---

## 26. Selecting a Traveler

The user selects:

```text
Select a Traveler
├── Ahmed
├── Sarah
├── Mohammed
└── + Add New Traveler
```

Then proceeds to the service application form.

---

## 27. Filling the Application Form

The currently published form for the service is loaded.

The user enters data and uploads required documents.

Opening the form does not create a final order.

---

## 28. No Order Drafts

If the user abandons the form before submission:

```text
No Order
No Debit
No Balance Hold
```

Upon returning, they start fresh with the current price and currently active form version.

---

## 29. Final Submission

When clicking **Submit Order**, the system verifies:

- The service is still available.
- The current price.
- The active published form version.
- The traveler belongs to the user account.
- Data completeness.
- Document validity.
- Documents are linked to the correct account.
- Current wallet balance sufficiency.

---

## 30. Price Changes

The authoritative price is the price at the moment of final submission.

If the price changes while filling out the form, the user must be notified of the updated price before executing an irreversible transaction.

---

## 31. Balance Changes

The wallet balance might be sufficient when opening the form, but become insufficient before submission due to another order.

Therefore, the balance is re-verified at the exact moment of final submission.

---

## 32. Order Success

Successful submission results in:

```text
Wallet Debit
      ↓
Commercial Order Created
      ↓
Service Execution Record Created
```

These outcomes are atomic and interdependent. There must never remain a debit without an order, or an order without an execution record, if the process fails.

---

## 33. Commercial Order

The order is the permanent purchase record.

It records:

- Account owner.
- Service.
- Price at time of purchase.
- Amount paid.
- Currency.
- Debit transaction reference.
- Service metadata snapshot at time of purchase.
- Snapshot of traveler data.
- Creation timestamp.

If service prices or traveler details change later, the historical order remains unchanged.

---

## 34. Service Execution Record

After payment succeeds, an independent record is created to track service fulfillment.

It contains:

- Associated commercial order.
- User.
- Traveler.
- Traveler data snapshot at time of submission.
- Service.
- Application form version used.
- Form responses.
- Uploaded documents.
- Current status.
- Status update history (changelog).
- Operational internal notes.

---

## 35. Difference Between Order and Execution

```text
Order
= What did the customer buy and how much did they pay?

Execution Record
= Where is the service in processing and how is it being fulfilled?
```

For this reason, financial status is never conflated with operational execution status.

---

## 36. Service Execution Statuses

The standard lifecycle can include:

```text
Order Received
Under Review
In Processing
Customer Action Required
Requested Action Received
Completed
Cancelled
```

The specific lifecycle transitions for each service type are defined when designing its dedicated standard operating procedures.

---

## 37. Customer Action Required

The Rehla team may require:

- An additional document.
- A clearer photo/scan.
- Correction of information.
- Further clarification.
- Another action.

Clearly displayed to the user:

```text
Action Required From You
```

accompanied by a description of what is required.

---

## 38. My Orders Section

Example:

```text
My Orders
├── #1001 UAE Visa — Ahmed — In Processing
├── #1002 UAE Visa — Sarah — Action Required
└── #1003 Umrah — Mohammed — Completed
```

Upon opening an order, the user sees:

- Service name.
- Traveler name.
- Price paid.
- Order date.
- Execution status.
- Last update timestamp.
- Action required from them (if any).
- Relevant documents according to view permissions.

---

## 39. Notifications

Notifications can be dispatched upon:

- Top-up approval.
- Top-up rejection.
- Order success.
- Execution status change.
- Request for an additional document.
- Action required from customer.
- Service completion.

Notifications may be delivered in-app or through approved external communication channels.

---

## 40. Admin Panel

The admin panel is Rehla's operational nerve center.

```text
Admin Panel
├── Overview
├── Services
├── Application Forms
├── Customers
├── Travelers
├── Wallets
├── Bank Accounts
├── Top-up Requests
├── Orders
├── Service Executions
├── Content
├── Notifications
├── Roles & Permissions
└── Audit Log
```

---

## 41. Service Management

The administration can:

- Add new services.
- Edit service content.
- Update prices.
- Manage images.
- Define requirements.
- Set expected fulfillment duration.
- Publish or deactivate services.
- Manage application forms.
- Reorder services in the storefront interface.

Deactivating a service prevents new orders without deleting or invalidating past orders.

---

## 42. Application Form Management

The administration can create a new version of a service form and modify it prior to publishing.

Once published, that version remains immutable for orders that utilized it.

Any subsequent modifications must be made in a new version.

---

## 43. User Management

Authorized staff can, based on their assigned permissions:

- Search for users.
- View customer data necessary for support.
- View orders.
- Review wallet transactions.
- View travelers.
- Monitor account status.

Sensitive information is not automatically accessible to all staff members.

---

## 44. Transfer Review Management

The transfer review interface displays:

- User.
- Amount.
- Bank.
- Transaction reference number.
- Receipt file.
- Submission timestamp.
- Review status.

Authorized staff decide:

```text
Approve
or
Reject
```

recording the reviewer's identity and the decision timestamp.

---

## 45. Service Execution Management

Authorized staff can:

- Open execution files.
- Review submitted data.
- Review uploaded documents.
- Transition status according to permitted state machine paths.
- Log internal operational notes.
- Request action from the customer.
- Mark service as completed.
- Review the historical status log.

---

## 46. Audit Log

Sensitive operations must be fully auditable and traceable.

Examples:

- Who approved the transfer?
- Who rejected it?
- When?
- Why?
- Who modified the execution status?
- Who altered a sensitive platform setting?

---

## 47. Permissions

Permissions can be separated, such as:

- Service management.
- Transfer review.
- Bank account management.
- Order viewing.
- Service execution.
- User management.
- Audit log viewing.

No single employee automatically receives all permissions.

---

## 48. Document Privacy

Sensitive documents such as:

- Passport scans.
- Identification documents.
- Transfer receipts.
- Supporting documents.

are treated as private content.

They never appear as permanent public URLs and can only be accessed by authorized parties.

---

## 49. Public Images

Service marketing images and bank logos are treated as public content and can be served normally.

---

## 50. User-Friendly Error Messages

### Insufficient Balance

```text
Your wallet balance is insufficient to complete this order.
Please top up your wallet and try again.
```

### Service Unavailable

```text
This service is currently unavailable for ordering.
```

### Price Changed

```text
The service price has been updated since opening the order.
Please review the new price before proceeding.
```

### Duplicate Passport Number

```text
This passport number is already registered to another traveler.
```

### Used Transaction Reference Number

```text
This bank transaction reference number has already been used.
```

---

## 51. Separation of Statuses

Example:

```text
Wallet Top-up Request: Approved
Commercial Order: Paid
Service Execution: In Processing
```

These represent distinct lifecycle states and are never collapsed into a single status.

---

# 52. Complete User Journey

## Phase 1 — Service Discovery

```text
Opens Rehla
   ↓
Browses Services
   ↓
Opens Specific Service
   ↓
Reads Price & Requirements
```

The user can inquire via WhatsApp or proceed to the official ordering process.

## Phase 2 — Account Sign-in / Registration

The user signs in or registers an account according to the approved authentication mechanism.

## Phase 3 — Adding a Traveler

```text
Travelers
   ↓
Add Traveler
   ↓
Full Name
Date of Birth
Gender
Passport Number
Issue Date
Expiry Date
```

## Phase 4 — Balance Check

If the wallet balance is insufficient, the user proceeds to top up their wallet.

## Phase 5 — Wallet Top-up

```text
Specify Amount
   ↓
Select Bank
   ↓
Make External Transfer
   ↓
Enter Transaction Reference Number
   ↓
Upload Receipt
   ↓
Submit for Review
```

## Phase 6 — Admin Review

```text
Under Review
   ↓
Approve
   ↓
Balance Credited
```

Or:

```text
Reject
   ↓
Provide Reason
   ↓
Balance Unchanged
```

## Phase 7 — Starting the Order

```text
Service
   ↓
Order Now
   ↓
Select Traveler
   ↓
Fill Form
   ↓
Upload Documents
```

## Phase 8 — Final Submission

The system re-validates:

- Service availability.
- Current price.
- Form validity.
- Traveler details.
- Attached documents.
- Sufficient wallet balance.

## Phase 9 — Payment

Example:

```text
Balance = 50,000 SDG
Service Price = 25,000 SDG
      ↓
Debit 25,000 SDG
      ↓
New Balance = 25,000 SDG
```

## Phase 10 — Record Creation

```text
Commercial Order Created
   ↓
Service Execution Record Created
```

## Phase 11 — Tracking

```text
Order Received
   ↓
Under Review
   ↓
In Processing
```

May transition to:

```text
Action Required From You
```

## Phase 12 — User Response

The user opens the order and submits the requested document or information.

## Phase 13 — Completion

```text
Requested Action Received
   ↓
In Processing
   ↓
Completed
```

The order remains permanently accessible in the account's historical log.

---

# 53. Family Scenario

An account owner manages:

```text
Ahmed
Sarah
Mohammed
```

They can top up their wallet once, then submit:

```text
Visa request for Ahmed
Visa request for Sarah
Visa request for Mohammed
```

Each order is completely independent in:

- Price.
- Debit.
- Documents.
- Status.
- Fulfillment.

---

# 54. Traveler Data Modification Scenario

A user submitted an order for Ahmed, and later Ahmed's passport was renewed.

After updating the traveler profile:

```text
New Orders → Use updated traveler details
Past Orders → Preserve historical details snapshot at time of submission
```

---

# 55. Price and Form Change Scenario

At the time of the first order:

```text
Price = 25,000 SDG
Form = Version 3
```

Later:

```text
Price = 30,000 SDG
Form = Version 4
```

The historical order retains:

```text
25,000 SDG
Version 3
```

While new orders use the updated pricing and form version.

---

# 56. Duplicate Transfer Scenario

If a user attempts to submit the same transaction reference number again for the same bank, the duplicate submission is rejected.

---

# 57. Duplicate Transfer Approval Scenario

Even if an administrative action is triggered repeatedly:

```text
One Transfer
=
One Balance Credit
```

---

# 58. Double Submission Scenario

If a user resubmits an order due to network instability, the system must prevent creating duplicate orders and duplicate debits for the same logical purchase.

---

# 59. Concurrent Purchase Scenario

If the balance is only sufficient for one service and the user attempts to purchase two services simultaneously, debits must execute atomically to prevent negative or inconsistent balances.

---

# 60. Excluded from Phase 1 (Out of Scope)

Phase 1 does NOT include:

- Physical products.
- Shopping carts.
- Item quantities.
- Inventory / stock tracking.
- Warehouses.
- Physical shipping.
- Multi-currency support.
- Multi-nationality support.
- Direct payment from bank transfer to order.
- Split payments.
- Saving incomplete order drafts.
- Multiple travelers in a single order.
- Third-party add-on marketplaces.

---

# 61. User Experience Principles

## Clarity

The user must always know:

- What is the price?
- What are the requirements?
- Who is the traveler?
- Was payment completed?
- Where is the order in processing?
- Is there any action required from them?

## Simplicity

Minimal necessary steps while preserving transparency and decision clarity.

## Reusability

Saved traveler profiles eliminate the need to re-enter details for every order.

## Security

Sensitive documents are never handled as public web assets.

## Auditability

Every financial transaction and significant administrative action has an immutable log entry.

---

# 62. Product Success Metrics

Tracked metrics include:

- Number of registered users.
- Number of saved traveler profiles.
- Total order volume.
- Completion rate of top-up requests.
- Average bank transfer review turnaround time.
- Transfer approval vs. rejection ratio.
- Orders broken down by service.
- Average service fulfillment time.
- Volume of requests requiring customer action.
- Percentage of completed orders.
- Reuse rate of saved traveler profiles.
- Customer repeat usage / retention rate.

---

# 63. Phase 1 Completion Definition

Phase 1 is considered successful when a user can complete:

```text
Discover Service
   ↓
Sign in to Account
   ↓
Add Traveler
   ↓
Top up Wallet
   ↓
Transfer Approved
   ↓
Select Service
   ↓
Select Traveler
   ↓
Fill Application Form
   ↓
Pay from Wallet
   ↓
Order Created
   ↓
Track Execution
   ↓
Respond to Action Required (if any)
   ↓
Service Completed
```

And from the administrative side:

```text
Manage Service
   ↓
Manage Requirements
   ↓
Manage Bank Accounts
   ↓
Review Top-ups
   ↓
Track Orders
   ↓
Execute Services
   ↓
Request Action When Needed
   ↓
Complete Service
   ↓
Maintain Audit Log
```

---

# 64. Future Expansion

Rehla can expand in the future to include:

- New travel services.
- New destination countries.
- Additional nationalities.
- Additional top-up and payment methods.
- Partner service integrations.
- Promotional offers and loyalty programs.
- Customer ratings and reviews.
- Advanced customer support center.
- Comprehensive operational and financial reporting.
- External API integrations as genuine business needs arise.

Any future expansion must never alter the historical records of past orders.

---

# 65. Conclusion

Rehla aligns:

```text
Service
   ↓
Traveler
   ↓
Wallet
   ↓
Order
   ↓
Execution
```

into a single, transparent journey.

The user discovers the service, understands the price and requirements, selects the traveler, tops up their wallet balance when necessary, submits the order, pays, and tracks execution until completion.

Simultaneously, the Rehla team gains a unified system to manage services, customers, travelers, bank transfers, orders, documents, and fulfillment in an organized, traceable, and scalable manner.
