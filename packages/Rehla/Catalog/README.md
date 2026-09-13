# Rehla Catalog Package

## Responsibility

This package owns the Catalog boundary declared by the Rehla architecture maps. It does not own another package's models, tables, authorization decisions, or external adapters.

## Rehla dependencies

- `Rehla\Core`
- `Rehla\Documents`
- `Rehla\Audit`

## Owned tables

- `fulfillment_policy_drafts`
- `fulfillment_policy_versions`
- `service_media`
- `service_price_history`
- `service_requirements`
- `services`

Only this package may create migrations for or write its owned tables. Consumers use declared commands or contracts and never receive mutable models.

## Declared public surfaces

- `CatalogService`: `Rehla\Catalog\Contracts\ServiceCatalog`
- `CatalogQuote`: `Rehla\Catalog\Contracts\ServiceQuoteReader`
- `CatalogFulfillmentPolicy`: `Rehla\Catalog\Contracts\PublishedFulfillmentPolicyReader`

`ServiceCatalog` returns immutable published-service snapshots, `ServiceQuoteReader` returns the current integer-SDG price and version, and `PublishedFulfillmentPolicyReader` returns the latest immutable policy version. Administrative actions expose no mutable database model.

`CatalogAuthorizer` is a Catalog-owned inbound port. It is deliberately unbound here, so administrative mutations fail closed until the Admin package binds its Identity-backed adapter. Tests bind an explicit fake.

## Runtime contract

- Every administrative action checks `CatalogAuthorizer` before writing. Public catalog and quote reads expose active state only where the contract says so.
- Catalog owns each transaction for service, price, ordering, and policy mutations. Document attachment and Audit append join the same database connection and transaction; neither provider commits internally.
- No external I/O runs inside these transactions. Catalog does not send notifications or write an Outbox record for the lifecycle implemented here.
- Public errors include `service.not_found`, `service.unavailable`, `service.fulfillment_policy_missing`, and `fulfillment.policy_immutable`. Validation failures remain internal until a presentation adapter maps them.
- Service prices use positive integer SDG minor units. Each price change locks the service row and appends an immutable history row in the same transaction.
- Published fulfillment policies have canonical SHA-256 checksums, sequential per-service versions, and PostgreSQL protection against update and delete.
- Service media is accepted only through `PublicDocuments`; snapshots expose document IDs and bilingual alt text without disk paths or storage keys.
- Recovery disables a service or applies a forward-only corrective price or policy version. Existing price history and published policies are never rewritten or rolled back destructively.

## Localization

Translations load from `src/resources/lang` under namespace `rehla-catalog`. English and Arabic files must keep identical recursive keys and value shapes.

## Verification

```bash
php artisan test packages/Rehla/Catalog/tests
```

The root Architecture suite verifies dependencies, model boundaries, provider loading, translations, and table ownership.
