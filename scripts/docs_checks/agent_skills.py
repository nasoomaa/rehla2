from __future__ import annotations

import re
from pathlib import Path

from scripts.docs_checks.common import ROOT, read_text, relative_markdown_links, require

EXPECTED_SKILLS = [
    "rehla-implementation-gate",
    "rehla-laravel-package-development",
    "rehla-localization",
    "rehla-api-contracts",
    "rehla-filament-admin",
    "rehla-postgres-integrity",
    "rehla-security-and-privacy",
    "rehla-testing-and-verification",
    "rehla-release-operations",
]

SPECIALISTS = EXPECTED_SKILLS[1:]
REQUIRED_SECTIONS = [
    "## When to use",
    "## Authorities",
    "## Rules",
    "## Workflow",
    "## Verification",
    "## Stop conditions",
    "## Handoff evidence",
]
UNFINISHED = re.compile(r"\b(?:TODO|FIXME|TBD|PLACEHOLDER)\b|\[Insert", re.IGNORECASE)


def parse_frontmatter(text: str) -> dict[str, str]:
    match = re.match(r"\A---\n(.*?)\n---\n", text, re.DOTALL)
    require(match is not None, "SKILL.md missing YAML frontmatter")
    fields: dict[str, str] = {}
    for line in match.group(1).splitlines():
        if ":" in line and not line.startswith((" ", "\t")):
            key, value = line.split(":", 1)
            fields[key.strip()] = value.strip().strip('"\'')
    return fields


def validate_skill(path: Path) -> None:
    text = read_text(path)
    fields = parse_frontmatter(text)
    folder = path.parent.name
    require(fields.get("name") == folder, f"{path}: frontmatter name must equal folder")
    description = fields.get("description", "")
    require(len(description) >= 40 and "Use when" in description, f"{path}: description must be discriminating and say when to use")
    require(UNFINISHED.search(text) is None, f"{path}: unfinished scaffold marker")
    for section in REQUIRED_SECTIONS:
        require(section in text, f"{path}: missing section {section}")


def validate_links(root: Path, paths: list[Path]) -> None:
    for source in paths:
        for target in relative_markdown_links(source):
            resolved = (source.parent / target).resolve()
            try:
                resolved.relative_to(root.resolve())
            except ValueError:
                require(False, f"{source}: skill link escapes repository: {target}")
            require(resolved.exists(), f"{source}: broken skill link {target}")


def validate_skill_tree(root: Path, expected: list[str] | None = None) -> None:
    expected_names = EXPECTED_SKILLS if expected is None else expected
    skills_root = root / ".agents/skills"
    actual = sorted(path.parent.name for path in skills_root.glob("*/SKILL.md")) if skills_root.exists() else []
    require(sorted(expected_names) == actual, f"skill set mismatch: expected={sorted(expected_names)} actual={actual}")
    paths = [skills_root / name / "SKILL.md" for name in expected_names]
    for path in paths:
        validate_skill(path)
    readme = root / ".agents/README.md"
    require(readme.is_file(), ".agents/README.md missing")
    readme_text = read_text(readme)
    indexed = sorted(set(re.findall(r"skills/([a-z0-9-]+)/SKILL\.md", readme_text)))
    require(indexed == sorted(expected_names), f"README skill index mismatch: {indexed}")
    gate = read_text(skills_root / "rehla-implementation-gate/SKILL.md")
    for name in SPECIALISTS:
        require(f"../{name}/SKILL.md" in gate, f"implementation gate missing specialist link: {name}")
    validate_links(root, [readme, *paths])


def check() -> None:
    validate_skill_tree(ROOT)
    print("  9 valid skills, 8 specialist links, 0 broken references, 0 scaffold markers")
