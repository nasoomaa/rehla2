from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase

from scripts.docs_checks.agent_skills import parse_frontmatter, validate_skill, validate_skill_tree
from scripts.docs_checks.common import CheckFailure


class AgentSkillsTest(TestCase):
    def test_frontmatter_parser_reads_name_and_description(self) -> None:
        fields = parse_frontmatter(
            "---\nname: sample\ndescription: Use when a sample workflow needs exact handling.\n---\n"
        )
        self.assertEqual(fields["name"], "sample")

    def test_skill_tree_requires_the_exact_expected_set(self) -> None:
        with TemporaryDirectory() as directory:
            root = Path(directory)
            (root / ".agents/skills").mkdir(parents=True)
            with self.assertRaisesRegex(CheckFailure, "skill set mismatch"):
                validate_skill_tree(root, expected=["rehla-implementation-gate"])

    def test_skill_description_must_discriminate_when_it_applies(self) -> None:
        with TemporaryDirectory() as directory:
            path = Path(directory) / "sample" / "SKILL.md"
            path.parent.mkdir()
            path.write_text(
                "---\nname: sample\ndescription: Generic advice.\n---\n"
                + "\n".join([
                    "## When to use", "## Authorities", "## Rules", "## Workflow",
                    "## Verification", "## Stop conditions", "## Handoff evidence",
                ]),
                encoding="utf-8",
            )
            with self.assertRaisesRegex(CheckFailure, "description must be discriminating"):
                validate_skill(path)
