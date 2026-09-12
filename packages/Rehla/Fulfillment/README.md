# Rehla Fulfillment Package

## Responsibility

This package owns the Fulfillment boundary declared by the Rehla architecture maps. It does not own another package's models, tables, authorization decisions, or external adapters.

## Rehla dependencies

- `Rehla\Core`
- `Rehla\Identity`
- `Rehla\Orders`
- `Rehla\Forms`
- `Rehla\Documents`
- `Rehla\Purchasing`
- `Rehla\Audit`
- `Rehla\Notifications`

## Owned tables

- `customer_action_requests`
- `customer_action_responses`
- `execution_documents`
- `execution_internal_notes`
- `execution_status_history`
- `service_executions`

Only this package may create migrations for or write its owned tables. Consumers use declared commands or contracts and never receive mutable models.

## Declared public surfaces

- `FulfillmentReader`: `Rehla\Fulfillment\Contracts\ExecutionReader`
- `FulfillmentAdmin`: `Rehla\Fulfillment\Contracts\FulfillmentAdminCommands`
- `FulfillmentReporting`: `Rehla\Fulfillment\Contracts\FulfillmentReportingSource`

These are contract-map declarations for later owner tasks; the Foundation scaffold does not implement domain behavior prematurely.

## Runtime contract

- Authorization denies by default and is enforced by the owning action or query.
- Transaction participation uses the caller's connection when the contract declares it; this package never commits an outer transaction.
- External I/O does not run inside a business transaction. Required delivery is recorded through the owner Outbox contract after the relevant plan task exists.
- Public error identities use stable lowercase dot notation. Internal exceptions and messages are not public identities.
- Recovery disables the affected path or applies a forward-only correction after immutable records exist.

## Localization

Translations load from `src/resources/lang` under namespace `rehla-fulfillment`. English and Arabic files must keep identical recursive keys and value shapes.

## Verification

```bash
php artisan test packages/Rehla/Fulfillment/tests
```

The root Architecture suite verifies dependencies, model boundaries, provider loading, translations, and table ownership.
