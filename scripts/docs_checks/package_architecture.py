from __future__ import annotations

import re
from pathlib import Path

from scripts.docs_checks.common import ROOT, read_text, require

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


def check() -> None:
    check_layout(implementation_documents())
