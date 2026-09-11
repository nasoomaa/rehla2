# Rehla — Authoritative Product Specification Corpus

This directory contains the complete, implementation-independent specification corpus for **Rehla** (رحلة), an integrated digital travel services platform designed for Sudanese users.

This corpus serves as the authoritative source of truth for downstream `iterative-development`, requirements extraction, story decomposition, behavior scenario generation, and system verification.

---

## Directory Structure

```text
specs/
├── README.md                                      # Corpus map, reading guide, and governance
├── product-overview.md                            # High-level product vision, boundaries, invariants, terminology
│
├── domains/                                       # Coherent business domain specifications
│   ├── identity-and-access.md                     # Accounts, authentication, staff roles, and abilities
│   ├── service-catalog.md                         # Travel services, requirements, media, pricing history
│   ├── application-forms.md                       # Dynamic form schemas, 11 field types, versioning
│   ├── travelers.md                               # Traveler profiles, passport normalization, uniqueness
│   ├── documents.md                               # Document storage, quarantine/scanning pipeline, privacy
│   ├── wallet-and-ledger.md                       # Ledger, integer minor units (SDG), balance invariants
│   ├── top-ups.md                                 # Bank accounts, transfer proof review, credit workflow
│   ├── orders-and-purchasing.md                   # Purchase coordination, atomic checkout, commercial orders
│   ├── fulfillment.md                             # Service execution, 7-state lifecycle, customer actions
│   ├── notifications.md                           # In-app notifications, transactional Outbox pattern
│   ├── content.md                                 # Informational pages, bilingual EN/AR content
│   ├── audit.md                                   # Immutable operational audit logging
│   └── reporting.md                               # 12 core product metrics, Khartoum timezone cohorts
│
├── contracts/                                     # System boundaries, external and subsystem contracts
│   ├── customer-rest-api-v1.md                    # Public and customer REST API specification (v1)
│   ├── admin-operations-contract.md               # Administrative control plane operational contract
│   ├── storage-and-document-pipeline.md           # Private/public file storage and scanning contract
│   ├── outbox-and-notifications-delivery.md       # Transactional outbox event delivery contract
│   ├── whatsapp-inquiry-contract.md               # WhatsApp deep-link generation and zero-side-effect contract
│   └── bank-transfer-receipt-contract.md          # Bank transfer verification and receipt proof contract
│
├── journeys/                                      # End-to-end observable user and system workflows
│   ├── journey-01-service-discovery-and-inquiry.md # Discovery, requirements viewing, WhatsApp inquiry
│   ├── journey-02-account-registration-and-profile.md # Account registration, authentication, profile management
│   ├── journey-03-traveler-profile-management.md  # Saving travelers, duplicate passport prevention
│   ├── journey-04-wallet-topup-submission-and-review.md # Bank selection, transfer submission, review & credit
│   ├── journey-05-service-order-and-instant-purchase.md # Service order, dynamic form, atomic debit & creation
│   ├── journey-06-execution-tracking-and-customer-action.md # Execution tracking, document requests, completion
│   ├── journey-07-family-multiple-orders.md       # One account funding multiple family member orders
│   └── journey-08-order-submission-edge-cases.md  # Concurrency, price-change races, idempotency retries
│
└── test-vectors/                                  # Deterministic examples, calculations, and rules
    ├── money-and-minor-units.md                   # SDG minor unit conversions, min top-up, non-negative balance
    ├── passport-normalization-and-validation.md   # Normalization rules, regex checks, uniqueness collisions
    ├── form-schema-validation-and-evaluation.md   # Validation test vectors across all 11 field types
    ├── state-machines-and-transitions.md          # State transitions for TopUpStatus and ExecutionStatus
    ├── idempotency-and-deduplication.md           # Idempotency key evaluation, fingerprint matching, conflicts
    └── reporting-metrics-calculations.md          # Deterministic test fixtures for all 12 platform metrics
```

---

## Specification Principles

1. **Implementation Independence**: The specifications describe WHAT the system does and all observable behavior, not internal implementation layers (no controllers, Eloquent models, or database schemas).
2. **Observable Behavior Discipline**: Every requirement is expressed in terms of verifiable, externally observable inputs, outcomes, state transitions, and error codes.
3. **Immutability of Financial and Historical Records**: Once created, wallet ledger records, commercial order snapshots, published form versions, and audit entries can never be modified or deleted.
4. **Separation of Concerns**:
   - **Service**: What can be purchased.
   - **Traveler**: Who the service is for.
   - **Wallet**: Financial account holding balance.
   - **Top-Up**: Independent funding mechanism via bank transfer.
   - **Commercial Order**: Permanent legal purchase record.
   - **Service Execution**: Operational fulfillment workflow.
5. **Deterministic Proof**: Test vectors provide precise mathematical and textual fixtures for automated validation.
