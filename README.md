# Rehla

Rehla is a Laravel modular monolith for the Phase 1 travel-service workflow defined in `docs/REHLA-PROJECT-CONCEPT-AND-USER-JOURNEY.md`.

The root application is only the runtime host. Business behavior, owned tables, translations, and public contracts live in local Composer packages under `packages/Rehla/<Package>` according to the machine-readable maps in `docs/architecture/`.

## Local prerequisites

- PHP 8.5
- Composer 2
- Node.js 24
- PostgreSQL 18

Copy `.env.example` to `.env` for local runtime configuration. Keep `.env`, `.env.testing`, generated keys, and credentials outside Git. Integration tests require PostgreSQL and a database name ending in `_testing`.

## Foundation checks

```bash
php artisan test tests/Feature/HostBootTest.php
npm ci
npm run build
./vendor/bin/pint --test
python3 -m scripts.docs_checks.run --group all
```

The complete build sequence is recorded in `docs/superpowers/plans/2026-09-11-rehla-platform-build.md`.
