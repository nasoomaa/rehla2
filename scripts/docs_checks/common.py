from __future__ import annotations

import csv
import json
import re
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]


class CheckFailure(AssertionError):
    """Raised when a documentation contract is inconsistent."""


def require(condition: bool, message: str) -> None:
    if not condition:
        raise CheckFailure(message)


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def read_json(path: Path) -> dict[str, Any]:
    return json.loads(read_text(path))


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open(encoding="utf-8", newline="") as stream:
        return list(csv.DictReader(stream))


def relative_markdown_links(path: Path) -> list[Path]:
    links: list[Path] = []
    for target in re.findall(r"(?<!!)\[[^]]*]\(([^)]+)\)", read_text(path)):
        clean = target.split("#", 1)[0].strip()
        if clean.startswith("<") and clean.endswith(">"):
            clean = clean[1:-1]
        if clean and "://" not in clean and not clean.startswith("mailto:"):
            links.append(Path(clean))
    return links
