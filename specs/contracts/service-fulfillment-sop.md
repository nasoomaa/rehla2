# Contract: Service Fulfillment SOP and Transition Policy

## 1. Responsibility

Every orderable service has a published, immutable fulfillment policy version. The policy selects the allowed subset of Rehla's standard seven-state execution graph and provides the bilingual operational instructions needed to apply it consistently.

## 2. Policy contents

A policy version contains an opaque ID, service ID, positive version number, state (`draft` or `published`), allowed transitions, actor permitted for each transition, required reason or note, completion-document requirement, and English/Arabic staff and customer guidance.

Draft versions may be edited. Publication is atomic; a published version cannot be changed or deleted. A correction creates a later draft and publishes it as a new version.

## 3. Ordering readiness and capture

A service is orderable only when all of these are true at checkout:

1. The service is active and has a current price version.
2. It has a published application form version.
3. It has a published fulfillment policy version.

Checkout locks and captures all three versions. The new execution stores the captured fulfillment-policy version and continues to use it even after a newer policy is published.

## 4. Phase 1 standard transition graph

The default policy may select from these transitions:

```text
received -> under_review
received -> processing
under_review -> processing
processing -> under_review
under_review -> action_required
processing -> action_required
action_required -> action_received
action_received -> under_review
action_received -> processing
under_review -> completed
processing -> completed
action_received -> completed
received -> cancelled
under_review -> cancelled
processing -> cancelled
action_required -> cancelled
action_received -> cancelled
```

`completed` and `cancelled` are terminal. Completion requires a clean issued document when the captured policy says so. Cancellation has no automatic wallet effect; any refund or compensation behavior requires a separately approved product policy.

## 5. Errors and acceptance

- Missing published policy: `service.fulfillment_policy_missing`.
- Disallowed transition: `execution.invalid_transition`.
- Mutation of a published policy: `fulfillment.policy_immutable`.

Acceptance proves that a newly published policy affects new executions only, old executions keep their captured policy, and every allowed/forbidden transition produces one deterministic result.
