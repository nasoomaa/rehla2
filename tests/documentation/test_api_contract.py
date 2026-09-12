from unittest import TestCase

from scripts.docs_checks.api_contract import (
    canonical_operation,
    extract_operations,
)


class ApiContractParserTest(TestCase):
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
