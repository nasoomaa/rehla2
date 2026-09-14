# Rehla Content Package

## Responsibility

This package owns the Content boundary declared by the Rehla architecture maps. It does not own another package's models, tables, authorization decisions, or external adapters.

## Rehla dependencies

- `Rehla\Core`
- `Rehla\Audit`

## Owned tables

- `content_pages`

Only this package may create migrations for or write its owned tables. Consumers use declared commands or contracts and never receive mutable models.

## Declared public surfaces

- `ContentPublished`: `Rehla\Content\Contracts\PublishedContentReader`, returning immutable localized `PageData`.
- `ContentAdmin`: `Rehla\Content\Contracts\ContentAdminCommands` for draft creation, updates, and publication.
- `ContentAuthorizationPort`: `Rehla\Content\Contracts\ContentAuthorizer`, implemented by the Admin composition boundary.

Published reads hide drafts and return the resolved locale and `ltr`/`rtl` direction. Every published page has complete English and Arabic titles and bodies. Optional Arabic SEO values fall back to English.

## Runtime contract

- Authorization denies by default because administrative actions cannot resolve without an explicit `ContentAuthorizer` adapter.
- Transaction participation uses the caller's connection when the contract declares it; this package never commits an outer transaction.
- External I/O does not run inside a business transaction. Required delivery is recorded through the owner Outbox contract after the relevant plan task exists.
- Public error identities use stable lowercase dot notation. Internal exceptions and messages are not public identities.
- Recovery disables the affected path or applies a forward-only correction after immutable records exist.
- Rich HTML is sanitized through a server-side allowlist before storage. Scriptable elements, event attributes, and unsafe URL schemes are removed.

## Localization

Translations load from `src/resources/lang` under namespace `rehla-content`. English and Arabic files must keep identical recursive keys and value shapes.

## Verification

```bash
php artisan test packages/Rehla/Content/tests
```

The root Architecture suite verifies dependencies, model boundaries, provider loading, translations, and table ownership.
