# Cross-Cutting Specification: Localization, Accessibility, and Errors

## 1. Language and direction

English (`en`) is the default locale and Arabic (`ar`) is fully supported with RTL layout. Published services, requirements, form labels, customer-action instructions, content pages, notifications, and validation messages must have both languages before publication. If a non-published operational label lacks a translation, the UI may fall back to English and must report the missing key.

Dates shown to customers use `Africa/Khartoum`; API timestamps remain ISO 8601 UTC. Money is formatted from integer minor units with two decimal digits and the localized SDG label.

## 2. Accessibility

Customer and Admin interfaces target WCAG 2.2 AA. All actions are keyboard operable, focus remains visible and follows dialog flow, controls have programmatic labels, errors are associated with their fields, status is not conveyed by color alone, and Arabic reading order remains logical in RTL mode. Uploaded-document requirements must be available as text.

## 3. Problem details

API errors use one stable envelope containing `code`, localized `message`, optional field `errors`, `trace_id`, and `correlation_id`. Public codes match `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$`. The following shared cases are canonical:

- trace_id: unique identifier for one HTTP request or one queued-job attempt; changes on retry.
- correlation_id: stable identifier for one logical business operation across retries, audit, notifications, and outbox.

| HTTP | Code | Meaning |
|---:|---|---|
| 422 | `wallet.insufficient_balance` | Current wallet funds cannot cover the locked price. |
| 422 | `service.unavailable` | Service is inactive or lacks a published ordering dependency. |
| 409 | `service.price_changed` | Accepted price or price version is stale. |
| 422 | `traveler.passport_conflict` | Normalized passport is already registered. |
| 422 | `top_up.reference_used` | Bank/reference pair has already been submitted. |

Authentication returns 401, staff authorization returns 403, concealed cross-account ownership failures return 404, validation returns 422, and state or idempotency conflicts return 409 unless a narrower contract says otherwise.

## 4. Acceptance

- Every published customer-visible object passes bilingual completeness validation.
- The same domain failure produces the same code on Web and API even when presentation differs.
- Automated accessibility checks are supplemented by keyboard, focus, and RTL journey tests.
