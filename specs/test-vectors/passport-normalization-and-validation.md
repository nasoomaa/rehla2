# Test Vectors: Passport Normalization and Validation

## 1. Scope and Rule Being Proven

This suite proves the deterministic normalization, regex validation (`^[A-Z0-9]{6,12}$`), and global uniqueness enforcement for Sudanese passport numbers across the platform.

---

## 2. Invariants Under Test

1. Passport input must be normalized: all whitespace, hyphens, and non-alphanumeric characters stripped; all letters converted to uppercase ASCII.
2. Normalized length must be between 6 and 12 characters inclusive.
3. Normalized characters must belong strictly to the character class `[A-Z0-9]`.
4. Global Uniqueness: A normalized passport number cannot exist more than once across the entire `travelers` table.
5. Date Sequencing: Date of Birth < Passport Issue Date < Passport Expiry Date.

---

## 3. Normalization and Validation Vectors Table

| Case ID | Category | Raw Input String | Expected Normalized String | Expected Validation Status | Rejection Code / Detail |
|---|---|---|---|---|---|
| **PAS-01** | Normal | `"P01234567"` | `"P01234567"` | Valid | Meets standard format (9 chars) |
| **PAS-02** | Normal | `" p01234567 "` | `"P01234567"` | Valid | Strips leading/trailing whitespace, uppercases |
| **PAS-03** | Normal | `"P-012-345-67"` | `"P01234567"` | Valid | Strips internal hyphens |
| **PAS-04** | Normal | `"p 012 345 67"` | `"P01234567"` | Valid | Strips internal whitespace, uppercases |
| **PAS-05** | Normal | `"D0987654"` | `"D0987654"` | Valid | Diplomatic prefix format (8 chars) |
| **PAS-06** | Boundary | `"A12345"` (6 chars) | `"A12345"` | Valid | Minimum allowed length boundary |
| **PAS-07** | Boundary | `"ABCDEFGHIJKL"` (12 chars)| `"ABCDEFGHIJKL"` | Valid | Maximum allowed length boundary |
| **PAS-08** | Boundary | `"P1234"` (5 chars) | `"P1234"` | Invalid | `traveler.invalid_passport_format` (Too short, < 6) |
| **PAS-09** | Boundary | `"P1234567890123"` (13 chars)| `"P1234567890123"`| Invalid | `traveler.invalid_passport_format` (Too long, > 12) |
| **PAS-10** | Invalid | `""` (Empty string) | `""` | Invalid | `traveler.passport_required` |
| **PAS-11** | Invalid | `"   "` (Whitespace only) | `""` | Invalid | `traveler.passport_required` |
| **PAS-12** | Invalid | `"P0123#456"` | `"P0123456"` | Valid if stripped, or invalid if non-alphanumeric rejected | Stripping strips `#`, yielding 8 chars |
| **PAS-13** | Invalid | `"12345@"` | `"12345"` | Invalid | Stripped length 5 is too short (< 6) |

---

## 4. Global Uniqueness Collision Test Vectors

| Case ID | Scenario | Existing DB Record | Incoming New Record | Expected Outcome |
|---|---|---|---|---|
| **UNI-01** | Same account identical input | `account_id: 10`, `passport: "P01234567"` | `account_id: 10`, `passport: "P01234567"` | Rejected: `traveler.passport_conflict` |
| **UNI-02** | Different account collision | `account_id: 10`, `passport: "P01234567"` | `account_id: 20`, `passport: "P01234567"` | Rejected: `traveler.passport_conflict` |
| **UNI-03** | Format-variation collision | `account_id: 10`, `passport: "P01234567"` | `account_id: 20`, `passport: " p-012 345 67 "` | Rejected: `traveler.passport_conflict` (Normalizes to identical string) |
| **UNI-04** | Different valid passports | `account_id: 10`, `passport: "P01234567"` | `account_id: 20`, `passport: "P01234568"` | Accepted (Distinct normalized values) |

---

## 5. Date Consistency Test Vectors

| Case ID | DOB | Issue Date | Expiry Date | Expected Status | Error Code |
|---|---|---|---|---|---|
| **DAT-01** | `1990-05-15` | `2022-01-01` | `2027-01-01` | Valid | Correct chronological sequence |
| **DAT-02** | `1990-05-15` | `1985-01-01` | `2025-01-01` | Invalid | `traveler.invalid_dates` (Issue date before DOB) |
| **DAT-03** | `1990-05-15` | `2022-01-01` | `2020-01-01` | Invalid | `traveler.invalid_dates` (Expiry date before Issue date) |
| **DAT-04** | `1990-05-15` | `2022-01-01` | `2022-01-01` | Invalid | `traveler.invalid_dates` (Expiry equals Issue date) |

---

## 6. Deterministic Normalization Function

```php
function normalizePassport(string $rawInput): string {
    // 1. Remove all characters except A-Z, a-z, 0-9
    $stripped = preg_replace('/[^A-Za-z0-9]/', '', $rawInput) ?? '';
    // 2. Uppercase ASCII
    $normalized = strtoupper($stripped);
    // 3. Length validation
    $length = strlen($normalized);
    if ($length < 6 || $length > 12) {
        throw new \InvalidArgumentException("Passport number must be between 6 and 12 alphanumeric characters.");
    }
    return $normalized;
}
```
