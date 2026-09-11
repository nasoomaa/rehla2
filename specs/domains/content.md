# Domain: Content and Static Pages

## 1. Purpose

The Content domain manages static public informational pages (e.g. Terms of Service, Privacy Policy, About Us, Frequently Asked Questions, Contact Information, and Travel Guides). It owns bilingual localization management, Right-to-Left (RTL) formatting specifications, SEO metadata, and publishing controls.

---

## 2. Actors

- **Public Guest / Customer**: Reads published informational pages in English or Arabic.
- **Content Administrator**: Creates, updates, drafts, and publishes informational pages, and configures SEO titles and descriptions.

---

## 3. Concepts

- **Content Page**: A distinct public article or policy document identified by a URL slug (e.g. `/terms`, `/privacy`, `/about`). Contains:
  - Page ID.
  - Slug.
  - Title (EN/AR).
  - Content Body (Markdown / Rich HTML in EN/AR).
  - Meta Title and Meta Description (EN/AR for SEO).
  - Status (`draft`, `published`).
  - Published At Timestamp.
  - Last Edited By Staff ID.
- **Bilingual Strategy**: English (`en`) is the default platform display language; Arabic (`ar`) is the secondary display language with native Right-to-Left (RTL) layout.

---

## 4. Invariants

1. **Bilingual Parity**: Every published page must provide non-empty content in both English and Arabic.
2. **Slug Uniqueness**: Page slugs must be globally unique across all content pages.
3. **No Commercial or Financial Mutation**: The content domain is strictly informational. It cannot initiate or alter orders, prices, bank accounts, or financial transactions.
4. **Draft Isolation**: Pages in `draft` status must return HTTP 404 to public guests and non-staff API clients.
5. **RTL Compliance**: When rendering Arabic content, the interface must automatically apply `dir="rtl"` layout rules.

---

## 5. State Model

### Content Page Lifecycle
```text
[Created] ──► Draft ◄──► Published
```
- **Draft**: Visible only in the administrative panel preview.
- **Published**: Publicly accessible via customer web portal and REST API.

---

## 6. Commands and Actions

### 6.1 CreateContentPage
- **Preconditions**: Staff has `content.manage` ability.
- **Inputs**: Slug, Title (EN/AR), Body (EN/AR), Meta Description (EN/AR).
- **Expected Outcome**: Page created in `draft` status.
- **Validation Rules**:
  - Slug: required, URL-safe alphanumeric and hyphens (`^[a-z0-9-]+$`).
  - Title (EN and AR): required, 3-150 characters.
  - Body (EN and AR): required.
- **Failure Behavior**: Duplicate slug returns HTTP 422.

### 6.2 UpdateContentPage
- **Preconditions**: Staff has `content.manage`.
- **Inputs**: Page ID, Title (EN/AR), Body (EN/AR), Meta Description (EN/AR).
- **Expected Outcome**: Page content updated; record of editing staff and timestamp saved.

### 6.3 PublishContentPage
- **Preconditions**: Staff has `content.manage`; page has non-empty EN and AR bodies.
- **Inputs**: Page ID.
- **Expected Outcome**: Status set to `published`, `published_at = NOW()`.
- **Observable Behavior**: Page immediately accessible to public visitors.

### 6.4 GetPublicContentPage
- **Preconditions**: Page exists and is in `published` status.
- **Inputs**: Slug, Preferred Locale (`en` or `ar`).
- **Expected Outcome**: Returns page title, rendered body in requested language, and SEO meta headers.
- **Failure Behavior**: If page is `draft` or does not exist, returns HTTP 404 Not Found.

---

## 7. Business Rules

1. **Locale Fallback**: If an Arabic request encounters a missing localized string in static UI, the system falls back safely to English without throwing a 500 error.
2. **Sanitization**: All HTML content entered into the rich-text editor is sanitized on the server against XSS vectors (stripping `<script>`, `<iframe>`, and event handler attributes).

---

## 8. Edge Cases

- **Accessing Draft Page by Guessing Slug**: Unauthenticated or regular customer requests to `/privacy` while in `draft` status receive HTTP 404 Not Found, preventing disclosure of unreleased policies.
- **Special Characters in Arabic Slugs**: URL slugs are strictly ASCII lowercase alphanumeric and hyphens (e.g. `privacy-policy`), while title headers support full Arabic typography.

---

## 9. Failure Behavior

- **Page Not Found**: HTTP 404 Not Found with code `content.page_not_found`.
- **Duplicate Slug**: HTTP 422 Unprocessable Entity with code `content.slug_exists`.

---

## 10. Cross-Domain Interactions

- **Audit Domain**: Page publishing and major policy updates write an audit record with staff author identity and timestamp.
