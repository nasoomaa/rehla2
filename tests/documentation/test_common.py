from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase

from scripts.docs_checks.common import (
    CheckFailure,
    read_json,
    relative_markdown_links,
    require,
)


class CommonChecksTest(TestCase):
    def test_require_reports_a_precise_failure(self) -> None:
        with self.assertRaisesRegex(CheckFailure, "package map mismatch"):
            require(False, "package map mismatch")

    def test_json_and_relative_links_are_parsed(self) -> None:
        with TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "target.md").write_text("# Target\n", encoding="utf-8")
            (root / "source.md").write_text("[target](target.md)\n", encoding="utf-8")
            (root / "map.json").write_text(
                '{"schema_version": 1}',
                encoding="utf-8",
            )

            self.assertEqual(read_json(root / "map.json")["schema_version"], 1)
            self.assertEqual(
                relative_markdown_links(root / "source.md"),
                [Path("target.md")],
            )
