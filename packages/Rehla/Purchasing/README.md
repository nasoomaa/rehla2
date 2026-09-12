# Rehla Purchasing Package

## Responsibility

This package owns the Purchasing boundary declared by the Rehla architecture maps. It does not own another package's models, tables, authorization decisions, or external adapters.

## Rehla dependencies

- `Rehla\Core`
- `Rehla\Identity`
- `Rehla\Catalog`
- `Rehla\Forms`
- `Rehla\Travelers`
- `Rehla\Documents`
- `Rehla\Wallet`
- `Rehla\Orders`
- `Rehla\Audit`
- `Rehla\Notifications`

## Owned tables

- `purchase_attempts`

Only this package may create migrations for or write its owned tables. Consumers use declared commands or contracts and never receive mutable models.

## Declared public surfaces

- `PurchasingSubmit`: `Rehla\Purchasing\Actions\SubmitOrder`
- `ExecutionCreatorPort`: `Rehla\Purchasing\Contracts\ExecutionCreator`

These are contract-map declarations for later owner tasks; the Foundation scaffold does not implement domain behavior prematurely.

## Runtime contract

- Authorization denies by default and is enforced by the owning action or query.
- Transaction participation uses the caller's connection when the contract declares it; this package never commits an outer transaction.
- External I/O does not run inside a business transaction. Required delivery is recorded through the owner Outbox contract after the relevant plan task exists.
- Public error identities use stable lowercase dot notation. Internal exceptions and messages are not public identities.
- Recovery disables the affected path or applies a forward-only correction after immutable records exist.

## Localization

Translations load from `src/resources/lang` under namespace `rehla-purchasing`. English and Arabic files must keep identical recursive keys and value shapes.

## Verification

```bash
php artisan test packages/Rehla/Purchasing/tests
```

The root Architecture suite verifies dependencies, model boundaries, provider loading, translations, and table ownership.
