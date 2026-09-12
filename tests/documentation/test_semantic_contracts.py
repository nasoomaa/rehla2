from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase
from unittest.mock import patch

from scripts.docs_checks.common import CheckFailure
from scripts.docs_checks.semantic_contracts import reject_all, require_all


class SemanticAssertionTest(TestCase):
    def test_require_all_reports_missing_fragment(self) -> None:
        with TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "contract.md").write_text("present", encoding="utf-8")
            with patch("scripts.docs_checks.semantic_contracts.ROOT", root):
                with self.assertRaisesRegex(CheckFailure, "missing.*absent"):
                    require_all("contract.md", ["present", "absent"])

    def test_reject_all_reports_stale_fragment(self) -> None:
        with TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "contract.md").write_text("stale wording", encoding="utf-8")
            with patch("scripts.docs_checks.semantic_contracts.ROOT", root):
                with self.assertRaisesRegex(CheckFailure, "stale.*wording"):
                    reject_all("contract.md", ["wording"])
