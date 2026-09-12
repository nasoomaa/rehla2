# ADR 0003: Authentication and MFA

## Status

Accepted

## Context

Customers, API clients, and staff have different trust boundaries. Financial review, access management, and audit viewing need stronger proof than possession of a staff session.

## Decision

- Customer Web uses an encrypted session cookie with CSRF protection.
- Customer API uses customer-scoped Laravel Sanctum bearer tokens. Customer tokens never carry staff abilities.
- Admin uses a separate guard, cookie, and session namespace; customer credentials cannot authenticate Admin routes.
- Authorization denies by default and grants only ownership or an ability from the canonical Phase 1 registry in `specs/cross-cutting/security-and-privacy.md`.
- A verified TOTP challenge is valid for at most four hours and is required for `topups.review`, `topups.settings.manage`, `access.manage`, and `audit.view`.
- Suspending an account or processing a security revocation invalidates its active sessions and tokens.

## Consequences

HTTP tests prove the separate guards, 401/403/404 behavior, token scopes, capability checks, and expired MFA rejection. Sensitive operations also create the audit evidence required by their owner package.
