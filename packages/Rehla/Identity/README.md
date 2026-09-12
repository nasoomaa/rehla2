# Rehla Identity Package

## Responsibility

This package owns the Identity boundary declared by the Rehla architecture maps. It does not own another package's models, tables, authorization decisions, or external adapters.

## Rehla dependencies

- `Rehla\Core`
- `Rehla\Audit`

## Owned tables

- `abilities`
- `personal_access_tokens`
- `role_ability`
- `roles`
- `staff_profiles`
- `user_role`
- `users`

Only this package may create migrations for or write its owned tables. Consumers use declared commands or contracts and never receive mutable models.

## Declared public surfaces

- `IdentityAuthorization`: `Rehla\Identity\Contracts\AuthorizesActor`
- `IdentityRegistration`: `Rehla\Identity\Actions\RegisterCustomer`
- `IdentityReader`: `Rehla\Identity\Contracts\IdentityReader`
- `RegistrationWalletPort`: `Rehla\Identity\Contracts\RegistrationWalletInitializer`
- `RegistrationNotificationPort`: `Rehla\Identity\Contracts\RegistrationNotificationRecorder`

`RegisterCustomer` creates an immutable `UserData` result and invokes Audit, wallet initialization, and welcome-notification recording inside one PostgreSQL transaction. Wallet and Notifications bind the two registration ports in their owner tasks; resolving registration remains fail-closed until both exist.

## Runtime contract

- Authorization denies by default and is enforced by the owning action or query.
- The canonical thirty staff abilities are represented only by `AbilityName`; sensitive financial and access abilities require both server evidence and the current session's MFA context to be no older than four hours.
- Customer and staff credentials use separate filtered Laravel user providers. The Admin HTTP task owns its additional cookie and session middleware isolation.
- Transaction participation uses the caller's connection when the contract declares it; this package never commits an outer transaction.
- External I/O does not run inside a business transaction. Required delivery is recorded through the owner Outbox contract after the relevant plan task exists.
- Public error identities use stable lowercase dot notation. Internal exceptions and messages are not public identities.
- Recovery disables the affected path or applies a forward-only correction after immutable records exist.
- The personal access token table is Sanctum-compatible and Identity-owned. The API plan installs and configures Sanctum when token issuance and revocation are implemented.

## Localization

Translations load from `src/resources/lang` under namespace `rehla-identity`. English and Arabic files must keep identical recursive keys and value shapes.

## Verification

```bash
php artisan test packages/Rehla/Identity/tests
```

The root Architecture suite verifies dependencies, model boundaries, provider loading, translations, and table ownership.
