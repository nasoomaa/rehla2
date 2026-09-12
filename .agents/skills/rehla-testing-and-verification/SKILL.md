---
name: rehla-testing-and-verification
description: Use when changing Rehla code or executable plans and when collecting RED, GREEN, expanded, fresh-process, or acceptance evidence.
---

# Rehla Testing and Verification

## When to use

Use for every code change and executable-plan change. Apply it again before claiming a task, plan gate, orrelease row complete.

## Authorities

Read the selected task’s completeness contract, `../../../docs/architecture/rehla-plan-contract.json`, acceptance register, and relevant test vectors.

## Rules

- Start with a test that fails for the intended missing behavior. A bootstrap, import, fixture, orunsafe-database failure is not valid RED.
- Implement the smallest GREEN change, then run focused tests, affected package/consumer tests, Architecture, and the task’s expanded command.
- Use PostgreSQL for locks, transactions, constraints, money, and concurrency. Use two real connections/processes where a race is the requirement.
- Test bypasses and false positives for architecture/security guards. Test observable behavior rather than mirroring implementation.
- Run formatter, then rerun affected tests. Run final gates in a fresh process; release proof uses a clean directory and empty PostgreSQL.
- `planned`, `red`, and `green` are not `verified`. Record exact command, named test/result, artifact path, and commit SHA.

## Workflow

Map acceptance IDs to test levels, capture RED, implement GREEN, run narrow-to-broad checks, inspect the diff, format/rerun, update evidence, then commit one task.

## Verification

For documentation run unit discovery and all registered `scripts.docs_checks` groups. For Laravel run the task command, package/consumer/Architecture suites, static analysis and formatter. Add browser/API/security/release suites only when affected orrequired by the plan gate.

## Stop conditions

Stop a completion claim when expected failure was not observed, a required test is skipped/flaky, PostgreSQL proof is absent, formatter changed behavior without rerun, evidence points to stale output, oranother plan’s gate remains open.

## Handoff evidence

Provide RED command/failure, GREEN command/result, expanded/fresh results, test counts, database target, formatter/static analysis, acceptance updates, skipped items with reason, residual risks, and commit.
