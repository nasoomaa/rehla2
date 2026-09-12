# ADR 0004: Document Lifecycle and Passport Identity

## Status

Accepted

## Context

Traveler documents and bank receipts contain sensitive identity and financial evidence. Passport identity also needs one deterministic platform-wide representation.

## Decision

- Accept PDF, JPEG, and PNG only. Images are limited to 10 MiB and PDFs to 20 MiB.
- Validate declared MIME, magic bytes, decoded structure, and malware result before a document becomes usable.
- Store traveler documents and receipts privately. Clients receive an authorized stream or short-lived access grant, never a storage key or permanent public URL.
- Delete unattached temporary uploads after 24 hours. Retain rejected evidence for 30 days. An attached document is not an orphan and remains retained until an approved parent retention policy authorizes deletion.
- Normalize passports by uppercasing ASCII letters and removing Unicode whitespace and hyphens. Then require `^[A-Z0-9]{6,12}$`; reject other punctuation instead of deleting it.
- Enforce global uniqueness on the normalized passport value without revealing another account's record.

## Consequences

File acceptance, cleanup, authorization, and passport uniqueness require PostgreSQL and private-storage integration tests. Cleanup and malware workers use fresh claim tokens so stale workers cannot attach, expose, or delete a document.
