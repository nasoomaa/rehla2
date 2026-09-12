# Rehla Integrations Package

## Responsibility

This package owns the Integrations boundary declared by the Rehla architecture maps. It does not own another package's models, tables, authorization decisions, or external adapters.

## Rehla dependencies

- `Rehla\Core`
- `Rehla\Notifications`

## Owned tables

- No owned business tables.

Only this package may create migrations for or write its owned tables. Consumers use declared commands or contracts and never receive mutable models.

## Declared public surfaces

- `IntegrationsInquiry`: `Rehla\Integrations\Contracts\InquiryLinkBuilder`

These are contract-map declarations for later owner tasks; the Foundation scaffold does not implement domain behavior prematurely.

## Runtime contract

- Authorization denies by default and is enforced by the owning action or query.
- Transaction participation uses the caller's connection when the contract declares it; this package never commits an outer transaction.
- External I/O does not run inside a business transaction. Required delivery is recorded through the owner Outbox contract after the relevant plan task exists.
- Public error identities use stable lowercase dot notation. Internal exceptions and messages are not public identities.
- Recovery disables the affected path or applies a forward-only correction after immutable records exist.

## Localization

Translations load from `src/resources/lang` under namespace `rehla-integrations`. English and Arabic files must keep identical recursive keys and value shapes.

## Verification

```bash
php artisan test packages/Rehla/Integrations/tests
```

The root Architecture suite verifies dependencies, model boundaries, provider loading, translations, and table ownership.
