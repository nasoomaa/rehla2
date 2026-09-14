from __future__ import annotations

import re
from pathlib import Path

from scripts.docs_checks.common import ROOT, read_json, read_text, require

EXPECTED_PACKAGES = {
    "Core": [],
    "Audit": ["Core"],
    "Identity": ["Core", "Audit"],
    "Documents": ["Core", "Identity", "Audit"],
    "Travelers": ["Core", "Identity", "Audit"],
    "Wallet": ["Core", "Identity", "Audit"],
    "Notifications": ["Core", "Identity", "Audit"],
    "Catalog": ["Core", "Documents", "Audit"],
    "Forms": ["Core", "Catalog", "Audit"],
    "Content": ["Core", "Audit"],
    "TopUps": [
        "Core",
        "Identity",
        "Documents",
        "Wallet",
        "Audit",
        "Notifications",
    ],
    "Orders": ["Core"],
    "Purchasing": [
        "Core",
        "Identity",
        "Catalog",
        "Forms",
        "Travelers",
        "Documents",
        "Wallet",
        "Orders",
        "Audit",
        "Notifications",
    ],
    "Fulfillment": [
        "Core",
        "Identity",
        "Orders",
        "Forms",
        "Documents",
        "Purchasing",
        "Audit",
        "Notifications",
    ],
    "Integrations": ["Core", "Notifications"],
    "Reporting": [
        "Core",
        "Identity",
        "Travelers",
        "TopUps",
        "Orders",
        "Fulfillment",
    ],
    "Web": [
        "Core",
        "Identity",
        "Catalog",
        "Forms",
        "Travelers",
        "Documents",
        "Wallet",
        "TopUps",
        "Orders",
        "Fulfillment",
        "Purchasing",
        "Notifications",
        "Content",
        "Integrations",
    ],
    "Api": [
        "Core",
        "Identity",
        "Catalog",
        "Forms",
        "Travelers",
        "Documents",
        "Wallet",
        "TopUps",
        "Orders",
        "Fulfillment",
        "Purchasing",
        "Notifications",
        "Integrations",
    ],
    "Admin": [
        "Core",
        "Identity",
        "Catalog",
        "Forms",
        "Travelers",
        "Documents",
        "Wallet",
        "TopUps",
        "Orders",
        "Fulfillment",
        "Notifications",
        "Content",
        "Audit",
        "Reporting",
    ],
}

FORBIDDEN_PACKAGE_ROOT = re.compile(
    r"packages/Rehla/(?P<package>[A-Za-z]+)/"
    r"(?P<directory>config|database|resources|routes|openapi)(?:/|`|\b)"
)
MISPLACED_PROVIDER = re.compile(
    r"packages/Rehla/(?P<package>[A-Za-z]+)/src/"
    r"(?P=package)ServiceProvider\.php"
)
PACKAGE_TREE_ROOT = re.compile(r"^packages/Rehla/(?P<package>[A-Za-z]+)/$")
TOP_LEVEL_RESOURCE = re.compile(
    r"^[├└]── (?P<directory>config|database|resources|routes|openapi)(?:/|$)"
)
SHALLOW_PROVIDER = re.compile(r"^[│ ]{4}[├└]── [A-Za-z]+ServiceProvider\.php$")
DEPENDENCY_ROW = re.compile(r"^\|\s*(?P<consumer>[A-Za-z]+)\s*\|\s*(?P<providers>[^|]+?)\s*\|$")


def implementation_documents() -> list[Path]:
    plans = sorted((ROOT / "docs/superpowers/plans").glob("2026-09-11-*.md"))
    return [
        ROOT / "docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md",
        ROOT / "docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md",
        *plans,
    ]


def display_path(path: Path) -> Path:
    try:
        return path.relative_to(ROOT)
    except ValueError:
        return path


def check_layout(paths: list[Path]) -> None:
    failures: list[str] = []

    for path in paths:
        in_fence = False
        tree_package: str | None = None

        for number, line in enumerate(read_text(path).splitlines(), start=1):
            if line.startswith("```"):
                in_fence = not in_fence
                tree_package = None
                continue

            root_match = PACKAGE_TREE_ROOT.match(line) if in_fence else None
            if root_match:
                tree_package = root_match.group("package")
                continue

            match = FORBIDDEN_PACKAGE_ROOT.search(line)
            if match:
                failures.append(
                    f"{display_path(path)}:{number}: "
                    f"{match.group('package')}/{match.group('directory')} must be under src"
                )

            provider = MISPLACED_PROVIDER.search(line)
            if provider:
                failures.append(
                    f"{display_path(path)}:{number}: "
                    f"{provider.group('package')} provider must be under src/Providers"
                )

            if tree_package:
                resource = TOP_LEVEL_RESOURCE.match(line)
                if resource:
                    failures.append(
                        f"{display_path(path)}:{number}: "
                        f"{tree_package}/{resource.group('directory')} tree entry must be under src"
                    )
                if SHALLOW_PROVIDER.match(line):
                    failures.append(
                        f"{display_path(path)}:{number}: "
                        f"{tree_package} provider tree entry must be under src/Providers"
                    )

    require(not failures, "\n".join(failures))


def cycle_path(graph: dict[str, list[str]]) -> list[str] | None:
    state = {name: "unseen" for name in graph}
    stack: list[str] = []

    def visit(node: str) -> list[str] | None:
        state[node] = "visiting"
        stack.append(node)
        for dependency in graph[node]:
            if state[dependency] == "visiting":
                start = stack.index(dependency)
                return [*stack[start:], dependency]
            if state[dependency] == "unseen":
                found = visit(dependency)
                if found:
                    return found
        stack.pop()
        state[node] = "done"
        return None

    for package in graph:
        if state[package] == "unseen":
            found = visit(package)
            if found:
                return found
    return None


def validate_package_graph(graph: dict[str, list[str]]) -> None:
    packages = set(graph)
    for consumer, providers in graph.items():
        require(consumer not in providers, f"self dependency: {consumer}")
        unknown = set(providers) - packages
        require(not unknown, f"{consumer}: unknown providers {sorted(unknown)}")
        require(
            len(providers) == len(set(providers)),
            f"{consumer}: duplicate providers",
        )
    cycle = cycle_path(graph)
    require(cycle is None, f"dependency cycle: {' -> '.join(cycle or [])}")


def parse_dependency_matrix(markdown: str) -> dict[str, list[str]]:
    start = markdown.find("## 6.")
    end = markdown.find("## 7.", start + 1)
    require(start >= 0 and end > start, "human dependency matrix section missing")
    matrix: dict[str, list[str]] = {}
    for line in markdown[start:end].splitlines():
        match = DEPENDENCY_ROW.match(line)
        if not match or match.group("consumer") not in EXPECTED_PACKAGES:
            continue
        value = match.group("providers").strip()
        providers = [] if value in {"لا شيء من Rehla", "None from Rehla"} else [
            item.strip() for item in value.split(",")
        ]
        matrix[match.group("consumer")] = providers
    return matrix


def validate_contract_edges(
    graph: dict[str, list[str]],
    records: list[dict[str, object]],
    surface_groups: dict[str, dict[str, object]] | None = None,
) -> None:
    edges = {
        (consumer, provider)
        for consumer, providers in graph.items()
        for provider in providers
    }
    contract_edges = {
        (str(record.get("consumer")), str(record.get("provider")))
        for record in records
    }
    require(len(contract_edges) == len(records), "duplicate contract dependency edge")
    missing = edges - contract_edges
    extra = contract_edges - edges
    require(
        not missing and not extra,
        f"contract edge mismatch: missing={sorted(missing)}, extra={sorted(extra)}",
    )

    required_fields = {
        "consumer",
        "provider",
        "surfaces",
        "mode",
        "transaction_owner",
        "write_rule",
        "failure_rule",
    }
    for record in records:
        absent = required_fields - record.keys()
        require(not absent, f"contract record missing fields: {sorted(absent)}")
        surfaces = record["surfaces"]
        require(
            isinstance(surfaces, list) and bool(surfaces),
            f"{record['consumer']} -> {record['provider']}: empty surfaces",
        )
        if surface_groups is not None:
            for surface in surfaces:
                require(
                    surface in surface_groups,
                    f"unknown surface group: {surface}",
                )
                require(
                    surface_groups[str(surface)]["owner"] == record["provider"],
                    f"{surface}: owner does not match {record['provider']}",
                )


def validate_table_ownership(
    tables: dict[str, dict[str, object]],
    graph: dict[str, list[str]],
) -> None:
    for table, record in tables.items():
        owner = record.get("owner")
        writers = record.get("writers")
        readers = record.get("reporting_readers")
        require(owner in graph, f"{table}: unknown owner {owner}")
        require(writers == [owner], f"{table}: writers must equal [{owner}]")
        require(isinstance(readers, list), f"{table}: reporting_readers must be a list")
        unknown_readers = set(readers) - set(graph)
        require(
            not unknown_readers,
            f"{table}: unknown reporting readers {sorted(unknown_readers)}",
        )


def check_dependency_artifacts() -> None:
    package_map = read_json(ROOT / "docs/architecture/rehla-package-map.json")
    require(package_map.get("schema_version") == 2, "package map schema must be 2")
    packages = package_map.get("packages")
    require(packages == EXPECTED_PACKAGES, "package graph differs from approved matrix")
    validate_package_graph(packages)
    require(len(packages) == 19, f"expected 19 packages, got {len(packages)}")
    require(
        sum(map(len, packages.values())) == 97,
        "expected 97 package dependency edges",
    )

    contract_map = read_json(
        ROOT / "docs/architecture/rehla-package-contract-map.json"
    )
    require(contract_map.get("schema_version") == 1, "contract map schema must be 1")
    validate_contract_edges(
        packages,
        contract_map.get("dependencies", []),
        contract_map.get("surface_groups", {}),
    )

    ownership = read_json(ROOT / "docs/architecture/table-ownership.json")
    require(ownership.get("schema_version") == 1, "table map schema must be 1")
    validate_table_ownership(ownership.get("tables", {}), packages)

    architecture = read_text(ROOT / "docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md")
    human_matrix = parse_dependency_matrix(architecture)
    require(
        human_matrix == EXPECTED_PACKAGES,
        "human dependency matrix differs from package map",
    )


def check() -> None:
    check_layout(implementation_documents())
    check_dependency_artifacts()
    print("  19 packages, 97 edges, 97 contract edge records, 0 cycles, 1 owner per table")
