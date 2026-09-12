# Test Vectors: State Machines and Transition Matrices

## 1. Scope and Rule Being Proven

This suite proves the deterministic validation of all state transitions across the platform's state machines: `TopUpStatus` (2 valid transitions) and `ExecutionStatus` (7 standard lifecycle states, strictly validated forward transitions).

---

## 2. Invariants Under Test

1. Terminal states (`approved`, `rejected` for Top-Ups; `completed`, `cancelled` for Executions) can NEVER transition to any other state.
2. Backward transitions are forbidden unless explicitly permitted by the operational workflow (e.g. `action_received` returning to `processing`).
3. Skipping intermediate prerequisite states is forbidden.
4. Transitioning to `action_required` requires non-empty instructions.
5. Transitioning to `completed` requires a clean linked `issued_document` exactly when the captured policy has `requires_issued_document = true`; it succeeds without one when the flag is false.
6. Transitioning to `cancelled` requires a non-empty audit rationale.

---

## 3. Top-Up Status Transition Matrix

| Case ID | Current State | Requested Transition | Actor | Parameters / Context | Expected Result | Rejection Code |
|---|---|---|---|---|---|---|
| **TOP-TR01** | `under_review` | `approved` | Staff (`topups.review`) | Transfer confirmed | **Valid** | Transitions to `approved` |
| **TOP-TR02** | `under_review` | `rejected` | Staff (`topups.review`) | Rejection reason supplied | **Valid** | Transitions to `rejected` |
| **TOP-TR03** | `approved` | `rejected` | Staff | Attempt to reverse approval | **Invalid** | `top_up.already_terminal` |
| **TOP-TR04** | `approved` | `under_review` | Staff | Attempt to re-open | **Invalid** | `top_up.already_terminal` |
| **TOP-TR05** | `rejected` | `approved` | Staff | Attempt to re-approve | **Invalid** | `top_up.already_terminal` |
| **TOP-TR06** | `under_review` | `rejected` | Staff | Empty rejection reason | **Invalid** | `top_up.missing_rejection_reason` |

---

## 4. Service Execution Status Transition Matrix

The canonical transition set parsed by contract checks is:

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

| Case ID | Current State | Target State | Actor | Required Payload / Precondition | Expected Result | Rejection Code |
|---|---|---|---|---|---|---|
| **EXE-TR01** | `received` | `under_review` | Staff | Standard intake | **Valid** | Case moves to review |
| **EXE-TR02** | `under_review` | `processing` | Staff | Documents checked | **Valid** | Processing started |
| **EXE-TR03** | `under_review` | `action_required` | Staff | Non-empty instructions provided | **Valid** | Action requested from customer |
| **EXE-TR04** | `processing` | `action_required` | Staff | Non-empty instructions provided | **Valid** | Action requested from customer |
| **EXE-TR05** | `action_required`| `action_received` | Customer | Customer submits response / file | **Valid** | Case moves to action received |
| **EXE-TR06** | `action_received`| `processing` | Staff | Staff reviews response | **Valid** | Fulfillment resumed |
| **EXE-TR07** | `processing` | `completed` | Staff | Valid issued visa PDF attached | **Valid** | Case successfully completed |
| **EXE-TR08** | `action_received`| `completed` | Staff | Valid issued visa PDF attached | **Valid** | Case successfully completed |
| **EXE-TR09** | `received` | `cancelled` | Staff | Mandatory cancellation reason | **Valid** | Operational cancellation |
| **EXE-TR10** | `processing` | `cancelled` | Staff | Mandatory cancellation reason | **Valid** | Operational cancellation |
| **EXE-TR11** | `received` | `completed` | Staff | Attempt to skip review/processing| **Invalid** | `execution.invalid_transition` |
| **EXE-TR12** | `received` | `action_received` | Customer | No action was requested | **Invalid** | `execution.invalid_transition` |
| **EXE-TR13** | `completed` | `processing` | Staff | Re-opening completed case | **Invalid** | `execution.already_terminal` |
| **EXE-TR14** | `completed` | `cancelled` | Staff | Cancelling completed case | **Invalid** | `execution.already_terminal` |
| **EXE-TR15** | `cancelled` | `processing` | Staff | Re-opening cancelled case | **Invalid** | `execution.already_terminal` |
| **EXE-TR16** | `processing` | `completed` | Staff | Issued document ID missing | **Invalid** | `execution.missing_issued_document` |
| **EXE-TR17** | `processing` | `action_required` | Staff | Empty instructions provided | **Invalid** | `execution.missing_action_instructions` |
| **EXE-TR18** | `action_required`| `completed` | Staff | Attempt to complete pending action| **Invalid** | `execution.invalid_transition` |
| **EXE-TR19** | `received` | `processing` | Staff | Captured policy permits direct processing | **Valid** | Processing started |
| **EXE-TR20** | `processing` | `under_review` | Staff | Captured policy permits renewed review | **Valid** | Case returns to review |
| **EXE-TR21** | `action_received` | `under_review` | Staff | Response requires review | **Valid** | Case returns to review |
| **EXE-TR22** | `under_review` | `completed` | Staff | Captured policy permits; required clean issued document attached | **Valid** | Case completed |
| **EXE-TR23** | `under_review` | `cancelled` | Staff | Mandatory reason | **Valid** | Case cancelled |
| **EXE-TR24** | `action_required` | `cancelled` | Staff | Mandatory reason | **Valid** | Case cancelled |
| **EXE-TR25** | `action_received` | `cancelled` | Staff | Mandatory reason | **Valid** | Case cancelled |
| **EXE-TR26** | `processing` | `completed` | Staff | Captured policy has `requires_issued_document = false`; no document ID | **Valid** | Case completed without an issued document |
| **EXE-TR27** | `processing` | `completed` | Staff | Captured policy has `requires_issued_document = true`; no document ID | **Invalid** | `execution.missing_issued_document` |

Every valid row is also evaluated against the execution's captured fulfillment-policy version. If the standard graph permits a transition but that service policy omits it, the deterministic outcome is `execution.invalid_transition`.
