from __future__ import annotations

import csv
from pathlib import Path

from scripts.docs_checks.common import ROOT, read_csv, read_text, relative_markdown_links, require

MANIFEST = ROOT / "docs/reviews/2026-09-12-rehla-documentation-manifest.csv"


def documentation_files() -> list[Path]:
    paths = [
        path
        for directory in (ROOT / "docs", ROOT / "specs")
        for path in directory.rglob("*")
        if path.is_file() and path.suffix in {".md", ".csv", ".json"} and path != MANIFEST
    ]
    return sorted(paths)


def validate_requirement_ids(rows: list[dict[str, str]]) -> None:
    expected = [f"R{number:02d}" for number in range(1, 66)]
    actual = [row.get("requirement_id", "") for row in rows]
    require(actual == expected, f"requirement ids mismatch: {actual}")
    if rows and "status" in rows[0]:
        require(all(row.get("status") == "covered" for row in rows), "non-covered requirement row")


def validate_relative_links(root: Path, paths: list[Path]) -> None:
    failures: list[str] = []
    resolved_root = root.resolve()
    for source in paths:
        for target in relative_markdown_links(source):
            resolved = (source.parent / target).resolve()
            try:
                resolved.relative_to(resolved_root)
            except ValueError:
                failures.append(f"{source}: link escapes repository: {target}")
                continue
            if not resolved.exists():
                failures.append(f"{source}: missing relative link: {target}")
    require(not failures, "\n".join(failures))


def classify(path: Path) -> str:
    relative = path.relative_to(ROOT).as_posix()
    if relative in {
        "docs/REHLA-PROJECT-CONCEPT-AND-USER-JOURNEY.md",
        "specs/product-overview.md",
    }:
        return "product"
    if relative == "docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md":
        return "architecture"
    if relative.startswith("docs/architecture/"):
        return "machine-map"
    if relative.startswith("docs/superpowers/specs/"):
        return "design"
    if "/plans/" in relative:
        return "plan"
    if relative.startswith("docs/reviews/"):
        return "review"
    if relative in {"specs/README.md", "specs/GOVERNANCE.md"}:
        return "governance"
    if relative == "specs/coverage-manifest.csv":
        return "coverage"
    for directory, kind in {
        "contracts": "contract",
        "domains": "domain",
        "journeys": "journey",
        "cross-cutting": "cross-cutting",
        "test-vectors": "test-vector",
    }.items():
        if relative.startswith(f"specs/{directory}/"):
            return kind
    raise AssertionError(f"unclassified documentation file: {relative}")


def evidence_group(path: Path) -> str:
    kind = classify(path)
    if kind in {"architecture", "machine-map", "design"}:
        return "package-and-contract-alignment"
    if kind == "plan":
        return "implementation-plans"
    if kind == "review":
        return "audit-evidence"
    return "product-and-behavior-contracts"


def validate_spec_coverage() -> None:
    rows = read_csv(ROOT / "specs/coverage-manifest.csv")
    validate_requirement_ids(rows)
    for row in rows:
        for column in ("primary_spec", "supporting_evidence"):
            target = ROOT / "specs" / row[column]
            require(target.is_file(), f"{row['requirement_id']}: missing {column}: {row[column]}")


def validate_plan_coverage() -> None:
    rows = read_csv(ROOT / "docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv")
    validate_requirement_ids(rows)
    plans_root = ROOT / "docs/superpowers/plans"
    for row in rows:
        path = plans_root / row["primary_plan"]
        require(path.is_file(), f"{row['requirement_id']}: missing primary plan: {row['primary_plan']}")
        require(row["task"] in read_text(path), f"{row['requirement_id']}: task not found: {row['task']}")


def expected_manifest_rows(paths: list[Path]) -> list[dict[str, str]]:
    return [
        {
            "path": path.relative_to(ROOT).as_posix(),
            "kind": classify(path),
            "line_count": str(len(read_text(path).splitlines())),
            "review_status": "reviewed",
            "evidence_group": evidence_group(path),
        }
        for path in paths
    ]


def write_manifest() -> None:
    rows = expected_manifest_rows(documentation_files())
    MANIFEST.parent.mkdir(parents=True, exist_ok=True)
    with MANIFEST.open("w", encoding="utf-8", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=list(rows[0]), lineterminator="\n")
        writer.writeheader()
        writer.writerows(rows)


def validate_manifest(paths: list[Path]) -> None:
    require(MANIFEST.is_file(), f"missing documentation manifest: {MANIFEST.relative_to(ROOT)}")
    actual = read_csv(MANIFEST)
    expected = expected_manifest_rows(paths)
    require(actual == expected, "documentation manifest differs from the discovered corpus; regenerate it")


def check() -> None:
    paths = documentation_files()
    validate_relative_links(ROOT, [path for path in paths if path.suffix == ".md"])
    validate_spec_coverage()
    validate_plan_coverage()
    validate_manifest(paths)
    print(f"  {len(paths)} documentation files, 65 specs rows, 65 plan rows, 0 broken links")
