# Domain: Reporting and Analytics

## 1. Purpose

The Reporting and Analytics domain computes and presents the 12 core platform business metrics defined in Section 62 of the Rehla Product Concept. It acts strictly as a read model consumer, aggregating transactional source data across domains to evaluate operational velocity, financial throughput, review turnaround, and repeat usage.

---

## 2. Actors

- **Platform Executive / Administrative Staff**: Inspects high-level dashboard summaries and operational turnaround analytics via the `admin.overview.view` ability.
- **System**: Executes deterministic analytical read queries and builds read-model projections.

---

## 3. Concepts

- **Cohort Window**: A defined time interval (e.g. Daily, Weekly, Monthly) anchored to the business timezone `Africa/Khartoum` (UTC+2).
- **As-Of Timestamp**: Required UTC cutoff controlling which decisions and transitions are visible, so the same window and cutoff reproduce the same result.
- **Read Model**: A denormalized or indexed projection optimized for analytical queries without locking or degrading primary transactional tables.
- **The 12 Core Metrics**:
  1. **Registered Users Count**: Total accounts created within the cohort window.
  2. **Saved Traveler Profiles**: Count of traveler profiles created, and total active traveler pool size.
  3. **Total Order Volume**: Dual indicators:
     - Total count of paid commercial orders.
     - Total gross monetary value of paid orders in SDG minor units.
  4. **Top-Up Request Completion Rate**: Percentage of submitted top-up requests that reached a terminal decision (`approved` or `rejected`) within the cohort.
  5. **Average Bank Transfer Review Turnaround Time**: Mean duration in minutes from `submitted_at` to `decision_at` across all decided top-ups in the cohort.
  6. **Transfer Approval Ratio**: Percentage of decided top-ups resulting in `approved` status vs `rejected`.
  7. **Orders Breakdown by Service**: Count and total value of paid orders grouped by service snapshot.
  8. **Average Service Fulfillment Duration**: Mean duration in hours/days from order submission (`created_at` / `received`) to execution `completed_at`.
  9. **Customer Action Volume**: Total count of service executions that entered `action_required` status at least once.
  10. **Completed Orders Percentage**: Percentage of eligible service executions that reached `completed` status within the cohort.
  11. **Traveler Profile Reuse Rate**: Percentage of distinct travelers who have been the beneficiary of more than one commercial order.
  12. **Customer Repeat-Usage Proxy**: Percentage of purchasing accounts that placed more than one paid order as of the cutoff. It is explicitly a repeat-usage proxy, not a time-based cohort-retention claim.

---

## 4. Invariants

1. **Read-Only Invariant**: The reporting domain must NEVER perform write operations on source transactional business tables (`wallets`, `orders`, `executions`, `users`).
2. **Timezone Standardization**: All daily, weekly, and monthly date boundaries are evaluated in the `Africa/Khartoum` timezone (UTC+2), regardless of the server's native system timezone.
3. **Division-by-Zero Safety**: Whenever a ratio or rate denominator is zero, the metric must deterministically return `0.00%` or `0` rather than throwing an error or yielding `null`.
4. **Reproducibility**: The same cohort window, `as_of`, source data, and metric version produce the same result. A later cutoff may include late decisions and therefore may legitimately change a past submission cohort.
5. **Exact Arithmetic**: Monetary sums and ratios use integer or exact decimal/rational arithmetic, never binary floating point.

---

## 5. Metric Formulas and Definitions

| Metric ID | Metric Name | Numerator | Denominator | Unit / Format |
|---|---|---|---|---|
| **M01** | Registered Users | Count of accounts with `created_at` in cohort | N/A | Integer count |
| **M02** | Saved Travelers | Count of travelers with `created_at` in cohort | N/A | Integer count |
| **M03-A** | Order Count | Count of paid orders with `created_at` in cohort | N/A | Integer count |
| **M03-B** | Order Gross Value | Sum of `price_paid_minor` for orders in cohort | N/A | SDG minor units (`amount_minor`) |
| **M04** | Top-Up Completion Rate | Top-ups submitted in the window and terminal by `as_of` | All top-ups submitted in the window | Percentage (`0.00%` to `100.00%`) |
| **M05** | Top-Up Turnaround | Sum of decision minus submission for the M04 terminal set | Count of that terminal set | Duration (Minutes / Hours) |
| **M06** | Transfer Approval Ratio | Approved requests in the M04 terminal set | All terminal requests in that set | Percentage (`0.00%` to `100.00%`) |
| **M07** | Orders by Service | Count and `price_paid_minor` sum of orders created in window grouped by service snapshot | N/A | Table / Breakdown |
| **M08** | Fulfillment Duration | Sum of completed minus received for transitions to completed in window by `as_of` | Count of those completions | Duration (Hours / Days) |
| **M09** | Action Required Volume | Count of transition events into `action_required` in window by `as_of` | N/A | Integer count; distinct execution count may also be shown |
| **M10** | Completed Orders Rate | Executions received in window and completed by `as_of` | All executions received in window | Percentage (`0.00%` to `100.00%`) |
| **M11** | Traveler Reuse Rate | Travelers used by more than one paid order as of cutoff | Travelers used by at least one paid order as of cutoff | Percentage (`0.00%` to `100.00%`) |
| **M12** | Customer Repeat-Usage Proxy | Accounts with more than one paid order as of cutoff | Accounts with at least one paid order as of cutoff | Percentage (`0.00%` to `100.00%`) |

---

## 6. Commands and Actions

### 6.1 GetPlatformOverviewMetrics
- **Preconditions**: Staff has `admin.overview.view` ability.
- **Inputs**: Cohort Period (`today`, `this_week`, `this_month`, or custom start/end dates) and required UTC `as_of` timestamp.
- **Expected Outcome**: Returns aggregated KPIs (M01 through M12) formatted for administrative dashboard widgets.
- **Authorization**: Denied by default; requires `admin.overview.view`.

### 6.2 GetTopUpPerformanceReport
- **Preconditions**: Staff has `admin.overview.view`.
- **Inputs**: Bank Account ID (optional filter), Date Range, `as_of`.
- **Expected Outcome**: Returns volume of submitted transfers, approved vs rejected counts, average review turnaround time, and backlog size of pending requests.

### 6.3 GetFulfillmentPerformanceReport
- **Preconditions**: Staff has `admin.overview.view`.
- **Inputs**: Service ID (optional filter), Date Range, `as_of`.
- **Expected Outcome**: Returns execution throughput, average completion time, volume of customer action requests, and current execution backlog by status.

---

## 7. Business Rules

1. **Cohort Date Boundary Rules**:
   A daily cohort for date `D` begins at `D 00:00:00 Africa/Khartoum` (equivalent to `D-1 22:00:00 UTC`) and ends at `D 23:59:59.999999 Africa/Khartoum` (equivalent to `D 21:59:59.999999 UTC`).
2. **Snapshot Stability**: If a service is subsequently renamed or deactivated, past order groupings in M07 continue to reference the frozen service name from the order’s historical snapshot.

---

## 8. Edge Cases

- **Zero Activity on Launch Day**: All rates and averages return `0` or `0.00%` without throwing mathematical division errors.
- **Long-Running Executions**: If an order submitted in January completes in February, it counts in January's submission cohort for total orders (M03) and February's completion cohort for fulfillment time (M08).

---

## 9. Failure Behavior

- **Unauthorized Query**: Returns HTTP 403 Forbidden with code `reporting.access_denied`.
- **Invalid Date Range**: Returns HTTP 422 Unprocessable Entity with code `reporting.invalid_date_range` if start date is after end date.

---

## 10. Cross-Domain Interactions

- **Identity Domain**: Supplies user registration timestamps for M01 and account cohorts for M12.
- **Travelers Domain**: Supplies traveler creation records for M02 and reuse counts for M11.
- **Top-Ups Domain**: Supplies top-up request lifecycle timestamps for M04, M05, and M06.
- **Orders Domain**: Supplies paid order counts and monetary totals for M03 and M07.
- **Fulfillment Domain**: Supplies execution status history for M08, M09, and M10.
