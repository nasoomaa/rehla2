# Rehla Documents Package

## Responsibility

This package owns the Documents boundary declared by the Rehla architecture maps. It does not own another package's models, tables, authorization decisions, or external adapters.

## Rehla dependencies

- `Rehla\Core`
- `Rehla\Identity`
- `Rehla\Audit`

## Owned tables

- `documents`
- `upload_sessions`

Only this package may create migrations for or write its owned tables. Consumers use declared commands or contracts and never receive mutable models.

## Declared public surfaces

- `DocumentsOwned`: `Rehla\Documents\Contracts\OwnedDocuments`
- `DocumentDownload`: `Rehla\Documents\Contracts\DocumentDownloadAuthorizer`
- `DocumentReference`: `Rehla\Documents\Data\DocumentReference`

`OwnedDocuments` locks clean references in stable identifier order, enforces owner and purpose, changes them to `attached` in the consumer's transaction, and returns immutable references without storage keys.

## Runtime contract

- Authorization denies by default and is enforced by the owning action or query.
- Customer uploads use the non-public `private` disk with random keys. Magic bytes, decoded image structure, polyglot markers, malware, and size quotas are checked before a document becomes clean.
- Download authorization is repeated for every stream and returns attachment, no-sniff, CSP, and private no-store headers without exposing a path or permanent URL.
- Transaction participation uses the caller's connection when the contract declares it; this package never commits an outer transaction.
- External I/O does not run inside a business transaction. Required delivery is recorded through the owner Outbox contract after the relevant plan task exists.
- Public error identities use stable lowercase dot notation. Internal exceptions and messages are not public identities.
- Recovery disables the affected path or applies a forward-only correction after immutable records exist.
- Scan and cleanup workers use expiring token fences. Orphans are eligible after 24 hours, rejected blobs after 30 days, and attached documents are excluded from cleanup.

## Localization

Translations load from `src/resources/lang` under namespace `rehla-documents`. English and Arabic files must keep identical recursive keys and value shapes.

## Verification

```bash
php artisan test packages/Rehla/Documents/tests
```

The root Architecture suite verifies dependencies, model boundaries, provider loading, translations, and table ownership.
