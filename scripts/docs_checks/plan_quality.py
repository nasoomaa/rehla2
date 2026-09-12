from __future__ import annotations

from pathlib import Path

from scripts.docs_checks.common import ROOT, read_json, read_text, require
from scripts.docs_checks.package_architecture import EXPECTED_PACKAGES

EXPECTED_PLAN_IDS = [
    "01-foundation-core",
    "02-identity-platform-services",
    "03-catalog-forms-content",
    "04-wallet-topups",
    "05-orders-purchasing-fulfillment",
    "06-reporting-integrations",
    "07-customer-web",
    "08-customer-api",
    "09-admin-control-panel",
    "10-operations-security-release",
]

MANDATORY_PACKAGE_FRAGMENTS = [
    "composer.json",
    "README.md",
    "src/Providers/{package}ServiceProvider.php",
    "src/resources/lang/en/messages.php",
    "src/resources/lang/ar/messages.php",
    "tests/Architecture/TranslationCompletenessTest.php",
]


def validate_plan_sequence(contract: dict[str, object]) -> None:
    plans = contract.get("implementation_plans", [])
    require(isinstance(plans, list), "implementation_plans must be a list")
    require(len(plans) == 10, f"expected 10 implementation plans, got {len(plans)}")
    ids = [str(row.get("id", "")) for row in plans]
    require(ids == EXPECTED_PLAN_IDS, f"plan sequence mismatch: {ids}")
    orders = [row.get("order") for row in plans]
    require(orders == list(range(1, 11)), f"plan order mismatch: {orders}")
    require(len(ids) == len(set(ids)), "duplicate implementation plan ID")
    positions = {plan_id: position for position, plan_id in enumerate(ids)}
    required = {"path", "outcome", "owned_packages", "release_gate", "depends_on"}
    for row in plans:
        plan_id = str(row["id"])
        missing = required - row.keys()
        require(not missing, f"{plan_id}: missing plan fields {sorted(missing)}")
        require(bool(str(row["path"])), f"{plan_id}: empty path")
        require(bool(str(row["outcome"])), f"{plan_id}: empty outcome")
        require(bool(str(row["release_gate"])), f"{plan_id}: empty release gate")
        dependencies = row["depends_on"]
        require(isinstance(dependencies, list), f"{plan_id}: depends_on must be a list")
        for dependency in dependencies:
            require(dependency in positions, f"{plan_id}: unknown plan dependency {dependency}")
            require(
                positions[str(dependency)] < positions[plan_id],
                f"{plan_id}: depends on later plan {dependency}",
            )


def validate_package_owners(
    contract: dict[str, object],
    expected_packages: set[str] | None = None,
) -> None:
    expected = set(EXPECTED_PACKAGES) if expected_packages is None else expected_packages
    packages = contract.get("packages", {})
    require(isinstance(packages, dict), "packages must be an object")
    require(set(packages) == expected, f"package owner set mismatch: {sorted(set(packages) ^ expected)}")
    for package, record in packages.items():
        require(isinstance(record, dict), f"{package}: owner record must be an object")
        require(
            bool(record.get("owner_plan")) and bool(record.get("owner_task")),
            f"{package}: owner plan/task required",
        )
        require(
            isinstance(record.get("mandatory_artifacts"), list),
            f"{package}: mandatory_artifacts must be a list",
        )
        require(
            bool(record.get("required_test_kinds")),
            f"{package}: required_test_kinds required",
        )


def validate_package_task(text: str, package: str) -> None:
    for template in MANDATORY_PACKAGE_FRAGMENTS:
        fragment = template.format(package=package)
        require(fragment in text, f"{package}: package task missing {fragment}")


def validate_live_paths(contract: dict[str, object]) -> None:
    for row in contract["implementation_plans"]:
        path = ROOT / str(row["path"])
        require(path.is_file(), f"missing implementation plan: {path.relative_to(ROOT)}")
        content = read_text(path)
        require("### Task" in content, f"{path.relative_to(ROOT)}: no executable tasks")


def check() -> None:
    path = ROOT / "docs/architecture/rehla-plan-contract.json"
    require(path.is_file(), "docs/architecture/rehla-plan-contract.json missing")
    contract = read_json(path)
    require(contract.get("schema_version") == 1, "plan contract schema must be 1")
    validate_plan_sequence(contract)
    validate_package_owners(contract)
    validate_live_paths(contract)
    print("  10 plans, 19 package owners")
