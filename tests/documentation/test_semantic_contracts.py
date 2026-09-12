from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase
from unittest.mock import patch

from scripts.docs_checks.common import CheckFailure
from scripts.docs_checks.semantic_contracts import (
    EXPECTED_EXECUTION_TRANSITIONS,
    extract_transitions,
    reject_all,
    require_all,
    require_transaction_contract,
)


class SemanticAssertionTest(TestCase):
    def test_transition_parser_requires_the_exact_canonical_set(self) -> None:
        text = "\n".join(f"{source} -> {target}" for source, target in EXPECTED_EXECUTION_TRANSITIONS)
        self.assertEqual(extract_transitions(text), EXPECTED_EXECUTION_TRANSITIONS)
        self.assertNotEqual(extract_transitions(text + "\ncompleted -> processing"), EXPECTED_EXECUTION_TRANSITIONS)

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

    def test_transaction_contract_requires_all_atomic_effects(self) -> None:
        with TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "good.md").write_text(
                "transaction domain state Audit in-app Outbox external network I/O",
                encoding="utf-8",
            )
            (root / "bad.md").write_text(
                "transaction domain state Audit in-app Outbox",
                encoding="utf-8",
            )
            with patch("scripts.docs_checks.semantic_contracts.ROOT", root):
                require_transaction_contract("good.md")
                with self.assertRaisesRegex(CheckFailure, "external network i/o"):
                    require_transaction_contract("bad.md")
