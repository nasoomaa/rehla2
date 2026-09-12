from __future__ import annotations

import argparse
import importlib
import sys

from scripts.docs_checks.common import CheckFailure

GROUPS = {
    "package": "scripts.docs_checks.package_architecture",
    "api": "scripts.docs_checks.api_contract",
    "semantics": "scripts.docs_checks.semantic_contracts",
    "inventory": "scripts.docs_checks.inventory",
}


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Validate Rehla documentation contracts.",
    )
    parser.add_argument("--group", choices=[*GROUPS, "all"], required=True)
    args = parser.parse_args()
    selected = list(GROUPS) if args.group == "all" else [args.group]

    try:
        for group in selected:
            module = importlib.import_module(GROUPS[group])
            module.check()
            print(f"PASS {group}")
    except (CheckFailure, ModuleNotFoundError) as error:
        print(f"FAIL {group}: {error}", file=sys.stderr)
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
