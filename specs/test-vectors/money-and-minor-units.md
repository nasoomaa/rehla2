# Test Vectors: Money and Minor Units Representation

## 1. Scope and Rule Being Proven

This suite proves that monetary values in Sudanese Pounds (SDG) are represented strictly as 64-bit non-negative integers (`amount_minor`) with a fixed scale of 100 (1 SDG = 100 minor units / piastres). It verifies conversions, formatting, minimum thresholds, non-negativity checks, and arithmetic precision without floating-point errors.

---

## 2. Invariants Under Test

1. `amount_minor` must be an integer (`int64`).
2. Floating-point numbers (`float`, `double`) are rejected in money calculations.
3. 1 SDG = 100 minor units.
4. Negative balances and negative transaction amounts are strictly rejected (`amount_minor >= 0`).
5. Minimum top-up amount is 5,000.00 SDG (`500,000` minor units).

---

## 3. Test Vectors Table

| Case ID | Category | Rule Being Proven | Input (`amount_minor` or raw input) | Expected Output / Result | Preconditions & State | Validation Status |
|---|---|---|---|---|---|---|
| **MON-01** | Normal | Standard integer conversion | `500000` minor units | Formatted: `"5,000.00 SDG"` / `"5,000.00 ج.س"` | Currency: `SDG`, Scale: 100 | Valid |
| **MON-02** | Normal | Fractional SDG conversion | `250050` minor units | Formatted: `"2,500.50 SDG"` / `"2,500.50 ج.س"` | Scale: 100 | Valid |
| **MON-03** | Normal | High value conversion | `150000000` minor units | Formatted: `"1,500,000.00 SDG"` | Scale: 100 | Valid |
| **MON-04** | Boundary | Minimum allowed top-up | `500000` minor units (5,000 SDG) | Accepted by TopUp validator | Top-up threshold check | Valid |
| **MON-05** | Boundary | Just below minimum top-up | `499999` minor units (4,999.99 SDG)| Rejected with `top_up.below_minimum` | Top-up threshold check | Invalid |
| **MON-06** | Boundary | Zero amount top-up | `0` minor units | Rejected with `top_up.below_minimum` | Positive integer check | Invalid |
| **MON-07** | Invalid | Negative minor units | `-1` minor units | Exception: `InvalidArgumentException` | Integer signedness check | Invalid |
| **MON-08** | Invalid | Float type input | `5000.50` (float) | Rejected: Strict integer type expected | No float allowed | Invalid |
| **MON-09** | Normal | Balance addition (Credit) | Initial: `1,000,000` (10,000 SDG)<br>Credit: `500,000` (5,000 SDG) | New Balance: `1,500,000` (15,000 SDG) | Ledger credit entry created | Valid |
| **MON-10** | Normal | Balance subtraction (Debit)| Initial: `5,000,000` (50,000 SDG)<br>Debit: `2,500,000` (25,000 SDG) | New Balance: `2,500,000` (25,000 SDG) | Ledger debit entry created | Valid |
| **MON-11** | Boundary | Exact balance exhaustion | Initial: `2,500,000` (25,000 SDG)<br>Debit: `2,500,000` (25,000 SDG) | New Balance: `0` (0.00 SDG) | Allowed (Zero balance valid) | Valid |
| **MON-12** | Boundary | Overdraft rejection | Initial: `2,500,000` (25,000 SDG)<br>Debit: `2,500,001` (25,000.01 SDG)| Rejected with `wallet.insufficient_balance`; Balance remains `2,500,000` | Non-negative constraint | Invalid |

---

## 4. Deterministic Transformation Specification

```php
function formatSdg(int $amountMinor, string $locale = 'en'): string {
    if ($amountMinor < 0) {
        throw new \InvalidArgumentException('Monetary minor units cannot be negative.');
    }
    $whole = intdiv($amountMinor, 100);
    $fraction = $amountMinor % 100;
    $formattedNumber = number_format($whole, 0, '.', ',') . '.' . str_pad((string)$fraction, 2, '0', STR_PAD_LEFT);
    
    return $locale === 'ar' ? "{$formattedNumber} ج.س" : "{$formattedNumber} SDG";
}
```
