---
name: rehla-security-and-privacy
description: Use when changing Rehla authentication, authorization, sessions or tokens, uploads, private documents, sensitive fields, logs, exports, or security headers.
---

# Rehla Security and Privacy

## When to use

Use whenever identity, access, ownership, confidential data, file processing, logging, external payloads, orHTTP/browser security can change.

## Authorities

Read `../../../specs/cross-cutting/security-and-privacy.md`, Documents/storage contracts, Identity abilities, Admin field matrix, and the selected surface plan.

## Rules

- Deny by default and authorize server-side at every route, Query, Command, document access, Admin action, and field reveal.
- Customer ownership failures do not enumerate another account and return 404. Staff uses explicit abilities, least privilege, separate guard, and recent MFA for sensitive operations.
- DTO/Resource/read-model allowlists prevent mass assignment and overexposure. Never log orserialize passwords, tokens, storage keys, passport data, bank secrets, raw notification payloads, orinternal notes.
- Uploads enforce transport and service size limits, MIME/magic bytes, safe decode, malware scan, private default, lifecycle/retention, and cleanup/attach fencing.
- Audit sensitive access and decisions with actor, subject, reason, time, and correlation ID after redaction.
- Validate CSRF/session fixation, Sanctum revocation/ability isolation, rate limits, CSP, HSTS, CORS allowlist, secure cookies, safe downloads and CSV formula escaping.

## Workflow

Build an actor/resource/operation/field matrix, add adversarial RED cases, implement owner Policy/Action boundaries, sanitize every output/log, then run negative authorization, upload abuse, session/token, andheader tests.

## Verification

Test guest, owner, other account, staff-none, limited, sensitive-role, and admin outcomes. Run secret/dependency/security scans when the affected surface orrelease gate requires them; inspect artifacts for leaked data.

## Stop conditions

Stop if an ability orretention/export rule is undefined, a permanent private URL is required, a secret must enter source control, a staff/customer guard is shared, orauthorization would rely only on hidden UI.

## Handoff evidence

Provide threat/actor matrix, denied and allowed outcomes, sensitive-field allowlist, upload/download cases, audit/log redaction, header/session/token results, findings with severity and disposition.
