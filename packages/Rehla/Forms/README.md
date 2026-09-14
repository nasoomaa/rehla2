# Rehla Forms Package

## Responsibility

This package owns the Forms boundary declared by the Rehla architecture maps. It does not own another package's models, tables, authorization decisions, or external adapters.

## Rehla dependencies

- `Rehla\Core`
- `Rehla\Catalog`
- `Rehla\Audit`

## Owned tables

- `form_drafts`
- `form_versions`

Only this package may create migrations for or write its owned tables. Consumers use declared commands or contracts and never receive mutable models.

## Declared public surfaces

- `FormsPublished`: `Rehla\Forms\Contracts\PublishedFormReader`
- `FormsValidator`: `Rehla\Forms\Contracts\FormSubmissionValidator`
- `FormsAdmin`: `Rehla\Forms\Contracts\FormAdminCommands`

`PublishedFormReader` returns the current immutable form version. `FormSubmissionValidator` validates answers against that exact current version and returns normalized answers plus opaque document references and required classifications. `FormAdminCommands` exposes draft creation, draft updates, and publication without exposing mutable database models.

`FormsAuthorizer` is an inbound Forms-owned port. It remains unbound in this package, so administrative commands fail closed until Admin provides its Identity-backed adapter.

## Runtime contract

- Draft and publish commands call the matching `FormsAuthorizer` ability before writing. Public reads and validation require the current published pointer.
- Forms owns draft and publication transactions. Audit appends on the same database connection and does not commit internally.
- Forms performs no external I/O and never queries Documents. File answers are UUID-shaped opaque references; Purchasing later verifies ownership, scan state, purpose, MIME, and attachment eligibility.
- Public errors use `form.version_outdated`, `form.validation_failed`, `form.schema_integrity_failed`, and non-enumerating not-found codes.
- Every publication inserts a new sequential version with canonical SHA-256 checksum. PostgreSQL rejects update and delete for every `form_versions` row; the mutable draft stores the current-version pointer.
- Recovery publishes a forward-only corrected version and moves the draft pointer. Historical versions are never rewritten or deleted.

## Localization

Translations load from `src/resources/lang` under namespace `rehla-forms`. English and Arabic files must keep identical recursive keys and value shapes.

## Verification

```bash
php artisan test packages/Rehla/Forms/tests
```

The root Architecture suite verifies dependencies, model boundaries, provider loading, translations, and table ownership.
