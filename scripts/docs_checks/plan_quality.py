from __future__ import annotations

from pathlib import Path
import re

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


def extract_task(text: str, task_reference: str) -> str:
    match = re.search(
        rf"(?ms)^### {re.escape(task_reference)}:\s.*?(?=^### Task\s+\d+:|\Z)",
        text,
    )
    require(match is not None, f"missing task heading: {task_reference}")
    return match.group(0)


def validate_package_tasks(contract: dict[str, object], plan_texts: dict[str, str]) -> None:
    for package, record in contract["packages"].items():
        plan_id = str(record["owner_plan"])
        require(plan_id in plan_texts, f"{package}: owner plan text missing: {plan_id}")
        task = extract_task(plan_texts[plan_id], str(record["owner_task"]))
        validate_package_task(task, package)
        namespace = f"rehla-{package.lower()}"
        require(namespace in task, f"{package}: package task missing translation namespace {namespace}")
        require("loadTranslationsFrom" in task, f"{package}: package task missing provider translation load")


def validate_no_duplicate_tasks(plan_texts: dict[str, str]) -> None:
    owners: dict[str, str] = {}
    for plan_id, text in plan_texts.items():
        for title in re.findall(r"(?m)^### Task \d+:\s*(.+?)\s*$", text):
            normalized = " ".join(title.casefold().split())
            require(
                normalized not in owners,
                f"duplicate executable task '{title}' in {owners.get(normalized)} and {plan_id}",
            )
            owners[normalized] = plan_id


def validate_requirement_coverage(contract: dict[str, object]) -> None:
    rows = contract.get("requirements", [])
    require(isinstance(rows, list), "requirements must be a list")
    expected = {f"R{number:02d}" for number in range(1, 66)}
    actual = [str(row.get("requirement_id", "")) for row in rows]
    require(len(actual) == len(set(actual)), "duplicate requirement ID")
    require(set(actual) == expected, f"requirement coverage mismatch: {sorted(set(actual) ^ expected)}")
    plan_ids = set(EXPECTED_PLAN_IDS)
    required = {
        "owner_plan",
        "owner_task",
        "acceptance_source",
        "verification",
        "final_evidence_plan",
    }
    for row in rows:
        requirement = str(row["requirement_id"])
        missing = required - row.keys()
        require(not missing, f"{requirement}: missing fields {sorted(missing)}")
        require(row["owner_plan"] in plan_ids, f"{requirement}: unknown owner plan")
        require(bool(str(row["owner_task"])), f"{requirement}: empty owner task")
        require(
            row["final_evidence_plan"] == "10-operations-security-release",
            f"{requirement}: wrong final evidence plan",
        )


def validate_program_ownership(contract: dict[str, object]) -> None:
    plans = contract["implementation_plans"]
    packages = contract["packages"]
    declared: dict[str, str] = {}
    for plan in plans:
        for package in plan["owned_packages"]:
            require(package not in declared, f"{package}: duplicate plan ownership")
            declared[str(package)] = str(plan["id"])
    require(set(declared) == set(packages), "plan owned_packages differs from package registry")
    for package, record in packages.items():
        require(
            declared[package] == record["owner_plan"],
            f"{package}: owner plan mismatch",
        )


def validate_architecture_order(contract: dict[str, object]) -> None:
    plan_order = {
        str(row["id"]): int(row["order"])
        for row in contract["implementation_plans"]
    }
    packages = contract["packages"]
    for consumer, providers in EXPECTED_PACKAGES.items():
        consumer_order = plan_order[str(packages[consumer]["owner_plan"])]
        for provider in providers:
            provider_order = plan_order[str(packages[provider]["owner_plan"])]
            require(
                provider_order <= consumer_order,
                f"{consumer}: provider {provider} is scheduled in a later plan",
            )


def validate_table_schedule(contract: dict[str, object]) -> None:
    ownership = read_json(ROOT / "docs/architecture/table-ownership.json")
    expected_by_package = {
        package: sorted(
            table
            for table, row in ownership["tables"].items()
            if row["owner"] == package
        )
        for package in EXPECTED_PACKAGES
    }
    for package, record in contract["packages"].items():
        require(
            record.get("owned_tables") == expected_by_package[package],
            f"{package}: owned table schedule differs from table-ownership.json",
        )


def validate_live_paths(contract: dict[str, object]) -> None:
    plan_texts: dict[str, str] = {}
    for row in contract["implementation_plans"]:
        path = ROOT / str(row["path"])
        require(path.is_file(), f"missing implementation plan: {path.relative_to(ROOT)}")
        content = read_text(path)
        require("### Task" in content, f"{path.relative_to(ROOT)}: no executable tasks")
        plan_texts[str(row["id"])] = content
        partial_contract = {
            "packages": {
                package: contract["packages"][package]
                for package in row["owned_packages"]
            }
        }
        validate_package_tasks(partial_contract, plan_texts)
    validate_no_duplicate_tasks(plan_texts)
    legacy = ROOT / "docs/superpowers/plans/2026-09-11-rehla-07-interfaces-operations-release.md"
    legacy_text = read_text(legacy)
    require("### Task" not in legacy_text, "deprecated interface plan contains executable tasks")


def check() -> None:
    path = ROOT / "docs/architecture/rehla-plan-contract.json"
    require(path.is_file(), "docs/architecture/rehla-plan-contract.json missing")
    contract = read_json(path)
    require(contract.get("schema_version") == 1, "plan contract schema must be 1")
    validate_plan_sequence(contract)
    validate_package_owners(contract)
    validate_requirement_coverage(contract)
    validate_program_ownership(contract)
    validate_architecture_order(contract)
    validate_table_schedule(contract)
    validate_live_paths(contract)
    print("  10 plans, 19 package owners, 65 requirements, 40 owned tables, 0 duplicate tasks")
