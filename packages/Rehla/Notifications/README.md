# Rehla Notifications Package

## Responsibility

This package owns the Notifications boundary declared by the Rehla architecture maps. It does not own another package's models, tables, authorization decisions, or external adapters.

## Rehla dependencies

- `Rehla\Core`
- `Rehla\Identity`
- `Rehla\Audit`

## Owned tables

- `notifications`
- `outbox_delivery_attempts`
- `outbox_messages`

Only this package may create migrations for or write its owned tables. Consumers use declared commands or contracts and never receive mutable models.

## Declared public surfaces

- `NotificationsOutbox`: `Rehla\Notifications\Contracts\OutboxWriter`
- `NotificationReader`: `Rehla\Notifications\Contracts\NotificationReader`
- `NotificationRecorder`: `Rehla\Notifications\Contracts\NotificationRecorder`
- `ClaimOutboxBatch`, `MarkDelivered`, and `MarkFailed` implement short PostgreSQL claim and fencing operations.

`NotificationChannel` and external dispatch are introduced by plan 06. Consumers receive immutable snapshots and envelopes rather than mutable models.

## Runtime contract

- Authorization denies by default and is enforced by the owning action or query.
- Transaction participation uses the caller's connection when the contract declares it; this package never commits an outer transaction.
- External I/O does not run inside a business transaction. Required delivery is recorded through the owner Outbox contract after the relevant plan task exists.
- PostgreSQL `CURRENT_TIMESTAMP`, a fresh claim token, and `FOR UPDATE SKIP LOCKED` fence workers. Failure five retains the row as a dead letter and writes sanitized diagnostics and Audit.
- Public error identities use stable lowercase dot notation. Internal exceptions and messages are not public identities.
- Recovery disables the affected path or applies a forward-only correction after immutable records exist.

## Localization

Translations load from `src/resources/lang` under namespace `rehla-notifications`. English and Arabic files must keep identical recursive keys and value shapes.

## Verification

```bash
php artisan test packages/Rehla/Notifications/tests
```

The root Architecture suite verifies dependencies, model boundaries, provider loading, translations, and table ownership.
