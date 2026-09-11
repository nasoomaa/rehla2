# Cross-Cutting Specification: Security and Privacy

## 1. Authentication boundaries

- Customer Web uses an encrypted session cookie and CSRF protection.
- Customer API uses scoped Laravel Sanctum bearer tokens.
- Admin uses a separate guard, cookie, and session namespace. Customer credentials never authenticate Admin routes.
- Suspended accounts lose active sessions and tokens and cannot perform authenticated writes.

## 2. Canonical staff abilities

The complete Phase 1 ability registry is:

```text
admin.overview.view
services.view
services.manage
forms.view
forms.draft
forms.publish
customers.view
customers.view_sensitive
customers.manage_status
travelers.view
travelers.view_sensitive
wallets.view
banks.view
banks.manage
topups.view
topups.review
topups.settings.manage
orders.view
executions.view
executions.view_sensitive
executions.transition
executions.note
documents.view_sensitive
content.view
content.manage
notifications.view
notifications.replay
access.view
access.manage
audit.view
```

Authorization denies access unless an ownership policy or explicit ability grants it. A verified TOTP challenge is valid for at most four hours and is required when exercising `topups.review`, `topups.settings.manage`, `access.manage`, or `audit.view`.

## 3. Sensitive data handling

- Passport numbers, identity data, receipts, application answers, and private documents are revealed only to the owning customer or staff with the specific sensitive-data ability.
- Lists mask passport numbers and sensitive financial references by default.
- Private storage keys and permanent URLs are never returned to clients. Each stream or short-lived access grant performs authorization again.
- Logs and audit records exclude passwords, tokens, MFA secrets, complete document contents, and raw card or banking credentials. IP addresses and user-agent data are minimized according to the approved audit policy.
- Exported or cached private responses use `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`.

## 4. Input and abuse controls

Every delivery surface validates size, type, structure, and ownership before invoking a domain command. Authentication, upload, top-up submission, checkout, customer-action response, and administrative review endpoints have independently configurable rate limits. Validation failures expose stable error codes and safe messages, never stack traces or internal class names.

## 5. Acceptance

- Cross-account customer access returns 404 without confirming that the resource exists.
- Missing staff ability returns 403 and creates an audit event for sensitive operations.
- An expired MFA challenge cannot authorize a high-risk command.
- A private document cannot be recovered from a public disk or permanent URL.
