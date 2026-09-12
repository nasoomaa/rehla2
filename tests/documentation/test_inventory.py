from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase

from scripts.docs_checks.common import CheckFailure
from scripts.docs_checks.inventory import (
    validate_audit_summary,
    validate_relative_links,
    validate_requirement_ids,
)


class InventoryTest(TestCase):
    def test_requirement_ids_must_be_exactly_r01_through_r65(self) -> None:
        rows = [
            {"requirement_id": f"R{number:02d}", "status": "covered"}
            for number in range(1, 66)
        ]
        validate_requirement_ids(rows)
        with self.assertRaisesRegex(CheckFailure, "requirement ids mismatch"):
            validate_requirement_ids(rows[:-1])

    def test_broken_relative_link_fails(self) -> None:
        with TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / "source.md"
            source.write_text("[missing](missing.md)", encoding="utf-8")
            with self.assertRaisesRegex(CheckFailure, "missing.md"):
                validate_relative_links(root, [source])

    def test_relative_link_cannot_escape_the_corpus_root(self) -> None:
        with TemporaryDirectory() as directory:
            root = Path(directory) / "repo"
            root.mkdir()
            source = root / "source.md"
            source.write_text("[escape](../../outside.md)", encoding="utf-8")
            with self.assertRaisesRegex(CheckFailure, "escapes repository"):
                validate_relative_links(root, [source])

    def test_final_audit_must_report_exact_corpus_and_status(self) -> None:
        text = "\n".join([
            "| `docs/` و`specs/` عدا manifest الوثائق نفسه | 69 | 17750 |",
            "| `.agents/README.md` والمهارات التسع | 10 | 380 |",
            "| الإجمالي المراجع في هذا التدقيق | 79 | 18130 |",
            "19 package; 28/28; 65/65; 9 skills; `planned`; لم يُنشأ",
        ])
        validate_audit_summary(text, 69, 17750, 10, 380)
        with self.assertRaisesRegex(CheckFailure, "scope or status evidence"):
            validate_audit_summary(text.replace("`planned`", "completed"), 69, 17750, 10, 380)
