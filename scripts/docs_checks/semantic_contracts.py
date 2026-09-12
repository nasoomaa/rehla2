from __future__ import annotations

from scripts.docs_checks.common import ROOT, read_text, require


def require_all(path: str, fragments: list[str]) -> None:
    content = read_text(ROOT / path)
    for fragment in fragments:
        require(fragment in content, f"{path}: missing {fragment!r}")


def reject_all(path: str, fragments: list[str]) -> None:
    content = read_text(ROOT / path)
    for fragment in fragments:
        require(fragment not in content, f"{path}: stale {fragment!r}")


def check_registration_contracts() -> None:
    identity = "specs/domains/identity-and-access.md"
    require_all(
        identity,
        [
            "RegistrationWalletInitializer",
            "RegistrationNotificationRecorder",
            "synchronously",
            "rolls back the entire registration transaction",
        ],
    )
    reject_all(
        identity,
        [
            "CustomerRegistered` handler initializes",
            "Emits `CustomerRegistered` inside the transaction",
        ],
    )
    require_all(
        "specs/domains/wallet-and-ledger.md",
        ["implements `RegistrationWalletInitializer`", "registration transaction"],
    )
    require_all(
        "specs/domains/notifications.md",
        ["implements `RegistrationNotificationRecorder`", "registration transaction"],
    )


def check_document_contracts() -> None:
    forms = "specs/domains/application-forms.md"
    require_all(
        forms,
        [
            "opaque `document_id`",
            "shape validation only",
            "does not query the Documents domain",
        ],
    )
    reject_all(
        forms,
        [
            "verifies files exist, are marked `clean`, and belong to current customer",
            "form.invalid_document_attachment",
        ],
    )
    require_all(
        "specs/domains/documents.md",
        ["OwnedDocuments::assertCleanOwned", "document.invalid_attachment"],
    )
    require_all(
        "specs/domains/orders-and-purchasing.md",
        ["OwnedDocuments::assertCleanOwned", "document.invalid_attachment"],
    )


def check_execution_inversion() -> None:
    purchasing_plan = (
        "docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md"
    )
    require_all(
        purchasing_plan,
        [
            "packages/Rehla/Purchasing/src/Contracts/ExecutionCreator.php",
            "packages/Rehla/Fulfillment/src/Infrastructure/PurchasingExecutionCreator.php",
            "FulfillmentServiceProvider",
            "لا تضف `Purchasing -> Fulfillment`",
        ],
    )
    require_all(
        "specs/domains/fulfillment.md",
        ["`ExecutionCreator` owned by Purchasing", "PurchasingExecutionCreator"],
    )


def require_transaction_contract(path: str) -> None:
    content = read_text(ROOT / path)
    lowered = content.lower()
    for fragment in ["transaction", "audit", "in-app", "outbox", "external network i/o"]:
        require(fragment in lowered, f"{path}: transaction contract missing {fragment!r}")


def check_transaction_and_retry_contracts() -> None:
    reject_all(
        "specs/journeys/journey-05-service-order-and-instant-purchase.md",
        ["idempotency_keys"],
    )
    reject_all(
        "specs/journeys/journey-08-order-submission-edge-cases.md",
        ["idempotency_keys"],
    )
    require_all(
        "specs/domains/orders-and-purchasing.md",
        ["purchase_attempts", "price version", "form version"],
    )
    require_all(
        "specs/contracts/outbox-and-notifications-delivery.md",
        ["lock_token", "lease_expires_at", "former worker", "lease is still valid"],
    )
    require_all(
        "specs/contracts/storage-and-document-pipeline.md",
        ["cleanup claim", "fence", "idempotent retry"],
    )
    require_all("specs/domains/documents.md", ["%PDF-"])
    reject_all(
        "specs/domains/documents.md",
        [
            "First 4 bytes must be `%PDF`",
            "deletes underlying storage blobs atomically",
        ],
    )
    for path in [
        "specs/domains/identity-and-access.md",
        "specs/domains/top-ups.md",
        "specs/domains/orders-and-purchasing.md",
        "specs/domains/fulfillment.md",
    ]:
        require_transaction_contract(path)


def check() -> None:
    check_registration_contracts()
    check_document_contracts()
    check_execution_inversion()
    check_transaction_and_retry_contracts()
    print("  registration ports, document ownership, and execution inversion aligned")
