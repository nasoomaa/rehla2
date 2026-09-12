from __future__ import annotations

import re
from pathlib import Path

from scripts.docs_checks.common import ROOT, read_text, require

EXPECTED_API = {
    ("POST", "/api/v1/auth/register"),
    ("POST", "/api/v1/auth/login"),
    ("POST", "/api/v1/auth/logout"),
    ("GET", "/api/v1/me"),
    ("PATCH", "/api/v1/me"),
    ("GET", "/api/v1/services"),
    ("GET", "/api/v1/services/{service_slug}"),
    ("GET", "/api/v1/services/{service_slug}/application-form"),
    ("GET", "/api/v1/travelers"),
    ("POST", "/api/v1/travelers"),
    ("GET", "/api/v1/travelers/{traveler_id}"),
    ("PATCH", "/api/v1/travelers/{traveler_id}"),
    ("GET", "/api/v1/wallet"),
    ("GET", "/api/v1/wallet/entries"),
    ("GET", "/api/v1/bank-accounts"),
    ("GET", "/api/v1/top-ups"),
    ("POST", "/api/v1/top-ups"),
    ("GET", "/api/v1/top-ups/{top_up_id}"),
    ("PUT", "/api/v1/top-ups/{top_up_id}/receipt"),
    ("POST", "/api/v1/uploads"),
    ("GET", "/api/v1/uploads/{document_id}"),
    ("GET", "/api/v1/documents/{document_id}/content"),
    ("POST", "/api/v1/order-submissions"),
    ("GET", "/api/v1/orders"),
    ("GET", "/api/v1/orders/{order_reference}"),
    (
        "POST",
        "/api/v1/executions/{execution_id}/actions/{action_request_id}/responses",
    ),
    ("GET", "/api/v1/notifications"),
    ("POST", "/api/v1/notifications/{notification_id}/read"),
}

OPERATION = re.compile(r"\b(GET|POST|PUT|PATCH|DELETE)\s+(/api/v1/[^\s`&,]+)")
PLACEHOLDER = re.compile(r"\{([^}]+)\}")
LEGAL_PARAMETER = re.compile(r"^[a-z][a-z0-9_]*$")
PUBLIC_CODE = re.compile(r"(?:code|Code)\s+`([A-Za-z][A-Za-z0-9_.]+)`")
LEGAL_CODE = re.compile(r"^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$")

TRACE_DEFINITION = (
    "trace_id: unique identifier for one HTTP request or one queued-job attempt; "
    "changes on retry."
)
CORRELATION_DEFINITION = (
    "correlation_id: stable identifier for one logical business operation across "
    "retries, audit, notifications, and outbox."
)


def canonical_operation(method: str, path: str) -> tuple[str, str]:
    return method.upper(), path.rstrip(".);,")


def extract_operations(text: str) -> set[tuple[str, str]]:
    return {canonical_operation(method, path) for method, path in OPERATION.findall(text)}


def rest_endpoint_section(text: str) -> str:
    start = text.find("## 4. Endpoint Specifications")
    end = text.find("## 5.", start + 1)
    require(start >= 0 and end > start, "REST endpoint section missing")
    return text[start:end]


def architecture_route_block(text: str) -> str:
    blocks = re.findall(r"```text\s*\n(.*?)```", text, flags=re.DOTALL)
    matching = [block for block in blocks if extract_operations(block) == EXPECTED_API]
    require(len(matching) == 1, "architecture route block missing or duplicated")
    return matching[0]


def check_operation_set(path: Path, text: str) -> None:
    actual = extract_operations(text)
    require(
        actual == EXPECTED_API,
        f"{path}: missing={sorted(EXPECTED_API-actual)}, extra={sorted(actual-EXPECTED_API)}",
    )
    parameters = {name for _, route in actual for name in PLACEHOLDER.findall(route)}
    invalid = sorted(name for name in parameters if not LEGAL_PARAMETER.fullmatch(name))
    require(not invalid, f"{path}: invalid parameter names {invalid}")
    require(len(parameters) == 8, f"{path}: expected 8 parameter names, got {len(parameters)}")


def check_tracing() -> None:
    paths = [
        "specs/contracts/customer-rest-api-v1.md",
        "specs/cross-cutting/localization-accessibility-and-errors.md",
        "specs/cross-cutting/operational-reliability.md",
        "specs/domains/audit.md",
        "docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md",
    ]
    for relative in paths:
        content = read_text(ROOT / relative)
        require(TRACE_DEFINITION in content, f"{relative}: trace_id definition missing")
        require(
            CORRELATION_DEFINITION in content,
            f"{relative}: correlation_id definition missing",
        )
    rest = read_text(ROOT / paths[0])
    require('"trace_id"' in rest and '"correlation_id"' in rest, "Problem Details must contain both identifiers")
    require("X-Correlation-ID" in rest, "REST correlation header contract missing")


def check_error_codes() -> None:
    paths = [
        "specs/contracts/customer-rest-api-v1.md",
        "specs/domains/orders-and-purchasing.md",
        "specs/journeys/journey-08-order-submission-edge-cases.md",
        "specs/test-vectors/idempotency-and-deduplication.md",
        "docs/superpowers/plans/2026-09-11-rehla-08-customer-api.md",
    ]
    invalid: list[str] = []
    for relative in paths:
        for code in PUBLIC_CODE.findall(read_text(ROOT / relative)):
            if not LEGAL_CODE.fullmatch(code):
                invalid.append(f"{relative}: {code}")
    require(not invalid, "invalid public error codes: " + ", ".join(invalid))
    for relative in paths[:4]:
        content = read_text(ROOT / relative)
        require(
            "order.idempotency_conflict" not in content,
            f"{relative}: stale idempotency code",
        )
    require(
        "idempotency.key_reused" in read_text(ROOT / paths[0]),
        "canonical idempotency code missing from REST contract",
    )


def check() -> None:
    rest_path = ROOT / "specs/contracts/customer-rest-api-v1.md"
    architecture_path = ROOT / "docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md"
    check_operation_set(rest_path, rest_endpoint_section(read_text(rest_path)))
    check_operation_set(
        architecture_path,
        architecture_route_block(read_text(architecture_path)),
    )
    api_plan_path = ROOT / "docs/superpowers/plans/2026-09-11-rehla-08-customer-api.md"
    check_operation_set(api_plan_path, read_text(api_plan_path))
    check_tracing()
    check_error_codes()
    print("  28/28 operations in spec, architecture, and plan; 8 parameters; trace/correlation and codes")
