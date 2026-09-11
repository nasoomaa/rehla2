# Rehla Specification Governance

## 1. Authority and precedence

When two documents disagree, apply this order:

1. `docs/REHLA-PROJECT-CONCEPT-AND-USER-JOURNEY.md` defines the approved product intent and requirements R01–R65.
2. Files under `specs/` refine that intent into testable behavior. They may resolve ambiguity but may not silently add a product capability.
3. `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md` defines Laravel package boundaries and implementation placement.
4. Implementation plans define delivery order only and may not change product behavior.

A conflict must be resolved in the higher-authority document or recorded explicitly before implementation.

## 2. What belongs in this directory

- `product-overview.md` is the product foundation and vocabulary.
- `domains/` owns business rules and invariants.
- `contracts/` owns boundaries shared by domains or delivery surfaces.
- `journeys/` owns end-to-end observable flows.
- `cross-cutting/` owns rules that every affected domain must apply consistently.
- `test-vectors/` owns deterministic acceptance examples.
- `coverage-manifest.csv` proves coverage of requirements R01–R65.

Laravel packages, namespaces, dependency direction, deployment topology, and framework choices belong in the architecture document. SQL or framework snippets in this corpus are allowed only when they make a behavioral contract precise; the stated behavior remains authoritative over the illustrative mechanism.

## 3. Canonical conventions

- Error codes use lowercase dot notation such as `wallet.insufficient_balance`.
- API identifiers are opaque strings. Clients must not infer type, sequence, or ownership from an identifier.
- SDG values are signed 64-bit integer minor units with scale 100; binary floating-point arithmetic is forbidden.
- Timestamps are stored and exchanged in UTC. Customer display and reporting boundaries use `Africa/Khartoum`.
- A customer requesting another account's resource receives HTTP 404. A staff member who can identify a resource but lacks the required ability receives HTTP 403.
- The initial minimum top-up is `500000` minor units and is an auditable setting changeable only by authorized staff.
- Passport normalization removes Unicode whitespace and hyphens, converts ASCII letters to uppercase, then requires `^[A-Z0-9]{6,12}$`. Other punctuation is rejected rather than silently deleted.
- Checkout requires a customer-scoped `Idempotency-Key`. Top-up submission is deduplicated by platform bank account and normalized transfer reference. Terminal review decisions replay their recorded outcome safely.
- In-app notifications are inserted atomically with the business transaction. The transactional outbox delivers asynchronous external channels at least once.
- Cancelling a service execution has no automatic wallet effect. Refund or compensation policy is unspecified in Phase 1 and must not be invented by an implementation.

## 4. Completion gate

A requirement is covered only when it has a primary specification, all related contracts and journeys agree with it, and deterministic cases cover meaningful boundaries. Every row R01–R65 in `coverage-manifest.csv` must be `covered`; no ambiguous test vector may accept multiple outcomes.

Any behavior change must update the primary specification, affected contracts and journeys, test vectors, and coverage evidence in the same change.
