from unittest import TestCase

from scripts.docs_checks.api_contract import (
    EXPECTED_API,
    architecture_route_block,
    canonical_operation,
    extract_operations,
)


class ApiContractParserTest(TestCase):
    def test_finds_the_route_block_without_language_specific_marker(self) -> None:
        routes = "\n".join(f"{method} {path}" for method, path in EXPECTED_API)
        text = f"""## Customer API

The routes are:

```text
{routes}
```
"""

        self.assertEqual(extract_operations(architecture_route_block(text)), EXPECTED_API)

    def test_extracts_single_and_grouped_headings(self) -> None:
        text = """
#### `POST /api/v1/auth/login`
#### `GET /api/v1/me` & `PATCH /api/v1/me`
"""
        self.assertEqual(
            extract_operations(text),
            {
                ("POST", "/api/v1/auth/login"),
                ("GET", "/api/v1/me"),
                ("PATCH", "/api/v1/me"),
            },
        )

    def test_canonical_operation_strips_heading_punctuation(self) -> None:
        self.assertEqual(
            canonical_operation("get", "/api/v1/orders/{order_reference});"),
            ("GET", "/api/v1/orders/{order_reference}"),
        )
