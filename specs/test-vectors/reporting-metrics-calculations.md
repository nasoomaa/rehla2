# Test Vectors: Reporting Metrics and Analytical Calculations

## 1. Scope and Rule Being Proven

This suite proves the mathematical precision, cohort evaluation, division-by-zero safety, and timezone boundary rules for all **12 core platform metrics** defined in Section 62 of the Rehla Product Concept.

---

## 2. Invariants Under Test

1. Timezone for all cohorts is strictly `Africa/Khartoum` (UTC+2).
2. All monetary metrics are evaluated in integer minor units before formatting.
3. Denominators equal to 0 must yield `0.00%` or `0` duration without exceptions.
4. Ratios and percentages are calculated with exact floating precision and formatted to two decimal places.

---

## 3. Fixed Benchmark Test Fixture

The test dataset contains activity recorded for cohort date **`2026-09-11`** (`2026-09-10 22:00:00 UTC` to `2026-09-11 21:59:59 UTC`):

### 3.1 User Registrations:
- User #101: `created_at = 2026-09-11 08:00:00 UTC`
- User #102: `created_at = 2026-09-11 11:30:00 UTC`
- User #103: `created_at = 2026-09-11 14:15:00 UTC`
- *(Total registered in cohort = 3)*

### 3.2 Saved Travelers:
- Traveler #1 (User #101): `created_at = 2026-09-11 08:15:00 UTC`
- Traveler #2 (User #101): `created_at = 2026-09-11 08:20:00 UTC`
- Traveler #3 (User #102): `created_at = 2026-09-11 12:00:00 UTC`
- Traveler #4 (User #103): `created_at = 2026-09-11 14:30:00 UTC`
- *(Total saved in cohort = 4)*

### 3.3 Top-Up Requests:
- TopUp #1: `submitted_at = 09:00:00 UTC`, `status = 'approved'`, `decision_at = 09:30:00 UTC` (Duration: 30 min)
- TopUp #2: `submitted_at = 10:00:00 UTC`, `status = 'approved'`, `decision_at = 11:00:00 UTC` (Duration: 60 min)
- TopUp #3: `submitted_at = 12:00:00 UTC`, `status = 'rejected'`, `decision_at = 12:30:00 UTC` (Duration: 30 min)
- TopUp #4: `submitted_at = 15:00:00 UTC`, `status = 'under_review'`, `decision_at = NULL`
- *(Total submitted = 4; Decided = 3; Approved = 2; Rejected = 1; Pending = 1)*

### 3.4 Commercial Orders:
- Order #1 (User #101, Traveler #1, UAE Visa): `price_paid = 25,000.00 SDG` (`2,500,000` minor)
- Order #2 (User #101, Traveler #1, UAE Visa): `price_paid = 25,000.00 SDG` (`2,500,000` minor)
- Order #3 (User #102, Traveler #3, Saudi Visa): `price_paid = 40,000.00 SDG` (`4,000,000` minor)
- *(Total orders = 3; Gross value = 90,000.00 SDG / 9,000,000 minor units)*

### 3.5 Service Executions:
- Exec #1 (Order #1): `received_at = 09:30:00`, `completed_at = 17:30:00` (Duration: 8.0 hours). Entered `action_required`: No.
- Exec #2 (Order #2): `received_at = 10:00:00`, `completed_at = NULL` (Status: `processing`). Entered `action_required`: Yes.
- Exec #3 (Order #3): `received_at = 13:00:00`, `completed_at = 19:00:00` (Duration: 6.0 hours). Entered `action_required`: No.
- *(Total active = 3; Completed = 2; Entered action_required = 1)*

---

## 4. Deterministic Metric Calculations Table

| Metric ID | Metric Name | Mathematical Formula | Calculation with Benchmark Values | Expected Formatted Output |
|---|---|---|---|---|
| **M01** | Registered Users Count | Count of accounts created in cohort | `3` | `3` |
| **M02** | Saved Travelers Count | Count of travelers created in cohort | `4` | `4` |
| **M03-A**| Paid Orders Count | Count of commercial orders in cohort | `3` | `3` |
| **M03-B**| Paid Orders Gross Value| Sum of `price_paid_minor` in cohort | `2,500,000 + 2,500,000 + 4,000,000` | `"90,000.00 SDG"` (`9,000,000` minor) |
| **M04** | Top-Up Completion Rate | `(Decided TopUps / Submitted TopUps) * 100` | `(3 / 4) * 100` | `"75.00%"` |
| **M05** | Top-Up Turnaround Time | `Sum(decision - submitted) / Decided TopUps` | `(30 + 60 + 30) / 3` | `"40.00 minutes"` |
| **M06** | Transfer Approval Ratio| `(Approved TopUps / Decided TopUps) * 100` | `(2 / 3) * 100` | `"66.67%"` |
| **M07** | Orders Breakdown | Group by Service Name: Count & Value | UAE Visa: `2` (`50,000 SDG`)<br>Saudi Visa: `1` (`40,000 SDG`) | Breakdown Table |
| **M08** | Fulfillment Duration | `Sum(completed - received) / Completed Execs` | `(8.0 + 6.0) / 2` | `"7.00 hours"` |
| **M09** | Action Required Volume | `Executions that entered action_required` | `1` | `1` (`33.33%` of active executions) |
| **M10** | Completed Orders Rate | `(Completed Executions / Total Executions) * 100` | `(2 / 3) * 100` | `"66.67%"` |
| **M11** | Traveler Profile Reuse | `(Travelers with >1 order / Used Travelers) * 100`| Used: Travelers #1, #3 (2). With >1: Traveler #1 (1).<br>`(1 / 2) * 100` | `"50.00%"` |
| **M12** | Customer Retention Rate| `(Customers with >1 order / Buyer Customers) * 100`| Buyers: User #101, #102 (2). With >1: User #101 (1).<br>`(1 / 2) * 100` | `"50.00%"` |

---

## 5. Division-by-Zero Edge Case Vectors

| Case ID | Metric | Numerator | Denominator | Calculation State | Expected Safe Output |
|---|---|---|---|---|---|
| **ZER-01** | Top-Up Completion Rate | `0` (Decided) | `0` (Submitted) | No top-ups submitted today | `"0.00%"` |
| **ZER-02** | Top-Up Turnaround Time | `0` (Minutes) | `0` (Decided) | No top-ups decided today | `"0.00 minutes"` |
| **ZER-03** | Transfer Approval Ratio| `0` (Approved)| `0` (Decided) | No top-ups decided today | `"0.00%"` |
| **ZER-04** | Fulfillment Duration | `0` (Hours) | `0` (Completed)| No executions completed today | `"0.00 hours"` |
| **ZER-05** | Completed Orders Rate | `0` (Completed)| `0` (Total) | No orders active today | `"0.00%"` |
| **ZER-06** | Traveler Reuse Rate | `0` (Reused) | `0` (Total Used)| No orders placed yet | `"0.00%"` |
| **ZER-07** | Customer Retention Rate| `0` (Repeat) | `0` (Buyers) | No buyers registered yet | `"0.00%"` |
