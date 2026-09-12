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

TASK_COMPLETENESS_LABELS = [
    "Files:",
    "Contracts:",
    "Database ownership:",
    "Authorization:",
    "Localization:",
    "Error codes:",
    "Transaction boundary:",
    "External I/O:",
    "Privacy:",
    "RED:",
    "GREEN:",
    "Expanded verification:",
    "Acceptance IDs:",
    "Recovery:",
    "Commit:",
]

EXPECTED_INTERFACE_TASKS = {
    "07-customer-web": [
        "Public Catalog, Content and Inquiry",
        "Customer Authentication and Account Shell",
        "Traveler and Wallet Views",
        "Top-Up Submission and Receipt Replacement",
        "Service Checkout and Atomic Submission",
        "Order, Execution and Customer-Action Tracking",
        "Private Documents and In-App Notifications",
        "Bilingual Accessible Browser Acceptance",
    ],
    "08-customer-api": [
        "API Foundation, Customer Authentication and Problem Details",
        "Public Service Catalog Contract",
        "Travelers, Wallet and Bank Accounts",
        "Top-Up Submission and Receipt Replacement",
        "Upload Lifecycle and Private Document Delivery",
        "Order Submission, Orders and Execution Actions",
        "Customer Notifications",
        "OpenAPI Equality and Transport Release Gate",
    ],
    "09-admin-control-panel": [
        "Staff Access and Filament Shell",
        "Complete Operational Resource Matrix",
        "Command-Only Mutation and Read-Model Guards",
        "Sensitive Data, Documents and Export Policy",
        "End-to-End Staff Operations Journey",
        "Admin Verification and Release Handoff",
    ],
    "10-operations-security-release": [
        "Localization, RTL, Accessibility and Browser Journeys",
        "Health, Workers, Scheduler and Observability",
        "Deployment, Migration and Restore Proof",
        "Security, Performance and Final R01–R65 Release Gate",
    ],
}

EXPECTED_ADMIN_AREAS = [
    "Overview",
    "Services & Policies",
    "Forms",
    "Content",
    "Customers",
    "Travelers",
    "Wallets & Ledger",
    "Bank Accounts",
    "Top-Ups",
    "Orders",
    "Executions, Actions & Documents",
    "Notifications & Dead Letters",
    "Roles & Abilities",
    "Audit",
]

RELEASE_PROOF_FRAGMENTS = [
    "Node.js 24.x LTS",
    "host-native",
    "artifact SHA-256",
    "encrypted",
    "consistency manifest",
    "RPO 15 minutes",
    "RTO 4 hours",
    "secret scan",
    "license audit",
    "CSP",
    "HSTS",
    "CORS allowlist",
    "zero unresolved critical/high findings",
    "fresh directory",
    "empty PostgreSQL",
    "all 19 package suites",
    "restore-rehearsal.sh",
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
    for plan_id, text in plan_texts.items():
        titles: set[str] = set()
        for title in re.findall(r"(?m)^### Task \d+:\s*(.+?)\s*$", text):
            normalized = " ".join(title.casefold().split())
            require(
                normalized not in titles,
                f"duplicate executable task '{title}' in {plan_id}",
            )
            titles.add(normalized)


def validate_task_completeness(plan_id: str, text: str) -> None:
    tasks = re.findall(r"(?ms)^### Task \d+:\s.*?(?=^### Task\s+\d+:|^## (?!#)|\Z)", text)
    require(tasks, f"{plan_id}: no executable tasks")
    for task in tasks:
        heading = task.splitlines()[0]
        for label in TASK_COMPLETENESS_LABELS:
            require(label in task, f"{plan_id} {heading}: completeness gate missing {label}")


def validate_requirement_task_references(
    contract: dict[str, object], plan_texts: dict[str, str]
) -> None:
    for row in contract["requirements"]:
        plan_id = str(row["owner_plan"])
        reference = str(row["owner_task"])
        numbers = [int(number) for number in re.findall(r"\d+", reference.split(":", 1)[0])]
        require(numbers, f"{row['requirement_id']}: owner_task has no task number")
        for number in numbers:
            require(
                re.search(rf"(?m)^### Task {number}:\s", plan_texts[plan_id]) is not None,
                f"{row['requirement_id']}: missing {plan_id} Task {number}",
            )


def validate_cross_plan_gates(contract: dict[str, object]) -> None:
    order = {str(row["id"]): int(row["order"]) for row in contract["implementation_plans"]}
    gates = contract.get("cross_plan_gates", [])
    require(isinstance(gates, list) and gates, "cross_plan_gates required")
    seen: set[str] = set()
    for gate in gates:
        gate_id = str(gate.get("id", ""))
        require(gate_id and gate_id not in seen, f"duplicate or empty cross-plan gate: {gate_id}")
        seen.add(gate_id)
        opened = str(gate.get("opened_by", ""))
        closed = str(gate.get("closed_by", ""))
        require(opened in order and closed in order, f"{gate_id}: unknown gate plan")
        require(order[opened] <= order[closed], f"{gate_id}: closes before it opens")
        require(bool(str(gate.get("evidence", ""))), f"{gate_id}: evidence required")


def validate_interface_task_sets(plan_texts: dict[str, str]) -> None:
    for plan_id, expected in EXPECTED_INTERFACE_TASKS.items():
        actual = re.findall(r"(?m)^### Task \d+:\s*(.+?)\s*$", plan_texts[plan_id])
        require(actual == expected, f"{plan_id}: interface task set mismatch: {actual}")
    admin = plan_texts["09-admin-control-panel"]
    for area in EXPECTED_ADMIN_AREAS:
        require(f"| {area} |" in admin, f"09-admin-control-panel: missing Admin area {area}")
    for guard in ["DB::", "builder update/delete", "raw connection", "relationship mutation", "model-bound forms"]:
        require(guard in admin, f"09-admin-control-panel: missing mutation guard {guard}")
    validate_release_proof(plan_texts["10-operations-security-release"])


def validate_release_proof(text: str) -> None:
    for fragment in RELEASE_PROOF_FRAGMENTS:
        require(fragment in text, f"10-operations-security-release: missing release proof {fragment}")


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


def validate_table_task_schedule(
    contract: dict[str, object], plan_texts: dict[str, str]
) -> None:
    ownership = read_json(ROOT / "docs/architecture/table-ownership.json")["tables"]
    schedule = contract.get("table_schedule", {})
    require(isinstance(schedule, dict), "table_schedule must be an object")
    require(set(schedule) == set(ownership), "table_schedule table set mismatch")
    for table, record in schedule.items():
        owner = str(ownership[table]["owner"])
        require(record.get("owner") == owner, f"{table}: migration owner mismatch")
        plan_id = str(record.get("migration_plan", ""))
        task_reference = str(record.get("migration_task", ""))
        require(plan_id in plan_texts, f"{table}: unknown migration plan {plan_id}")
        task = extract_task(plan_texts[plan_id], task_reference)
        require(table in task, f"{table}: missing from scheduled {plan_id} {task_reference}")
        prefix = f"packages/Rehla/{owner}/src/database/migrations/"
        require(
            str(record.get("migration_path", "")).startswith(prefix),
            f"{table}: migration path must belong to {owner}",
        )


def validate_live_paths(contract: dict[str, object]) -> None:
    plan_texts: dict[str, str] = {}
    for row in contract["implementation_plans"]:
        path = ROOT / str(row["path"])
        require(path.is_file(), f"missing implementation plan: {path.relative_to(ROOT)}")
        content = read_text(path)
        require("### Task" in content, f"{path.relative_to(ROOT)}: no executable tasks")
        require("**Prerequisites:**" in content, f"{row['id']}: prerequisites missing")
        require(re.search(r"(?m)^## (?:Final .+|Plan Completion) Gate$", content) is not None, f"{row['id']}: final gate missing")
        plan_texts[str(row["id"])] = content
        partial_contract = {
            "packages": {
                package: contract["packages"][package]
                for package in row["owned_packages"]
            }
        }
        validate_package_tasks(partial_contract, plan_texts)
        validate_task_completeness(str(row["id"]), content)
    validate_no_duplicate_tasks(plan_texts)
    validate_requirement_task_references(contract, plan_texts)
    validate_table_task_schedule(contract, plan_texts)
    validate_interface_task_sets(plan_texts)
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
    validate_cross_plan_gates(contract)
    validate_live_paths(contract)
    print("  10 plans, 19 package owners, 65 requirements, 40 owned tables, 0 duplicate tasks")
