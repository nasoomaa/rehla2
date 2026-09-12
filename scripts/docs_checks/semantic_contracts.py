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


def check() -> None:
    check_registration_contracts()
    check_document_contracts()
    check_execution_inversion()
    print("  registration ports, document ownership, and execution inversion aligned")
