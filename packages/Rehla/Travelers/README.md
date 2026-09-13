# Rehla Travelers Package

## Responsibility

This package owns the Travelers boundary declared by the Rehla architecture maps. It does not own another package's models, tables, authorization decisions, or external adapters.

## Rehla dependencies

- `Rehla\Core`
- `Rehla\Identity`
- `Rehla\Audit`

## Owned tables

- `travelers`

Only this package may create migrations for or write its owned tables. Consumers use declared commands or contracts and never receive mutable models.

## Declared public surfaces

- `CreateTraveler::handle(TravelerData): TravelerSnapshot`
- `UpdateTraveler::handle(string, TravelerData): TravelerSnapshot`
- `TravelerReader::listOwned(string, int, int): array<TravelerSnapshot>`
- `TravelerSnapshotReader::getOwned(string, string): TravelerSnapshot`

Snapshots expose only the six traveler profile fields and the opaque traveler identifier. Queries scope every read by owner and return the same not-found result for missing and foreign records.

## Runtime contract

- Authorization denies by default and is enforced by the owning action or query.
- Transaction participation uses the caller's connection when the contract declares it; this package never commits an outer transaction.
- External I/O does not run inside a business transaction. Required delivery is recorded through the owner Outbox contract after the relevant plan task exists.
- Public error identities use stable lowercase dot notation. Internal exceptions and messages are not public identities.
- Passport numbers are canonicalized globally before a PostgreSQL unique constraint is applied. Unique violations become `traveler.passport_conflict`.
- Recovery disables the affected path or applies a forward-only correction after immutable records exist.

## Localization

Translations load from `src/resources/lang` under namespace `rehla-travelers`. English and Arabic files must keep identical recursive keys and value shapes.

## Verification

```bash
php artisan test packages/Rehla/Travelers/tests
```

The root Architecture suite verifies dependencies, model boundaries, provider loading, translations, and table ownership.
