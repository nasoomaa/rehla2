---
name: rehla-api-contracts
description: Use when changing Rehla REST routes, controllers, Form Requests, Resources, Sanctum policy, OpenAPI, Problem Details, uploads, or API tests.
---

# Rehla API Contracts

## When to use

Use for any `/api/v1` transport or schema change. Do not use it for Web’s internal flow because Web calls domain contracts directly.

## Authorities

Read [REST contract](../../../specs/contracts/customer-rest-api-v1.md), [API plan](../../../docs/superpowers/plans/2026-09-11-rehla-08-customer-api.md), package/contract maps, and cross-cutting error/security specs.

## Rules

- Preserve exact equality among the 28 method/path operations, route names, OpenAPI operation IDs, controllers, policy matrix, and contract tests.
- Customer Sanctum tokens never contain staff abilities orenter Admin. Apply declared rate limit and content type to each operation.
- Mutations use Form Requests mapped to DTOs and owner Actions. Resources use explicit field allowlists and never serialize Models blindly.
- Return RFC-style `application/problem+json` with stable lower-dot `code`, new `trace_id` per attempt, stable `correlation_id` per logical operation, and correct 401/403/404/409/422/429 behavior.
- Other-account customer resources return non-enumerating 404. A known staff resource without ability uses 403.
- Preserve idempotency, upload limits/scanning, private-document authorization, and receipt-replacement semantics from domain contracts.

## Workflow

Add RED contract/policy tests, update route/controller/request/resource/action mapping, update OpenAPI and examples in the same change, then run ownership and error datasets for every outcome.

## Verification

Run Api feature/contract/architecture suites and `python3 -m scripts.docs_checks.run --group api`. Assert 28/28 exact operations and no Models, DB writes, staff abilities, undocumented route, orlocale-dependent code.

## Stop conditions

Stop when the observable behavior is absent from the REST spec, the domain owner exposes no required Action/Query, a new operation changes the approved 28-operation surface without authority, orOpenAPI cannot describe the actual response.

## Handoff evidence

Provide method/path/operationId, auth/ownership/rate/content policy, Request/Resource/Action mapping, error outcomes, OpenAPI diff, focused and full API results.
