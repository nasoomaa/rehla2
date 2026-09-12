from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase

from scripts.docs_checks.common import CheckFailure
from scripts.docs_checks.package_architecture import (
    EXPECTED_PACKAGES,
    check_layout,
    cycle_path,
    parse_dependency_matrix,
    validate_contract_edges,
    validate_package_graph,
    validate_table_ownership,
)


class PackageLayoutTest(TestCase):
    def test_rejects_package_resources_at_package_root(self) -> None:
        with TemporaryDirectory() as directory:
            path = Path(directory) / "bad.md"
            path.write_text(
                "`packages/Rehla/Api/routes/api_v1.php`",
                encoding="utf-8",
            )

            with self.assertRaisesRegex(CheckFailure, "Api/routes"):
                check_layout([path])

    def test_rejects_package_provider_outside_providers_namespace(self) -> None:
        with TemporaryDirectory() as directory:
            path = Path(directory) / "bad-provider.md"
            path.write_text(
                "`packages/Rehla/Api/src/ApiServiceProvider.php`",
                encoding="utf-8",
            )

            with self.assertRaisesRegex(CheckFailure, "src/Providers"):
                check_layout([path])

    def test_accepts_src_resources_and_root_tests(self) -> None:
        with TemporaryDirectory() as directory:
            path = Path(directory) / "good.md"
            path.write_text(
                "`packages/Rehla/Api/src/routes/api_v1.php`\n"
                "`packages/Rehla/Api/src/Providers/ApiServiceProvider.php`\n"
                "`packages/Rehla/Api/tests/Feature/AuthApiTest.php`\n",
                encoding="utf-8",
            )

            check_layout([path])


class PackageDependencyTest(TestCase):
    def test_parses_human_dependency_matrix(self) -> None:
        markdown = """
## 6. قواعد الاعتماد
| المستهلك | الحزم المسموح أن يعتمد عليها |
|---|---|
| Core | لا شيء من Rehla |
| Audit | Core |
| Identity | Core, Audit |
## 7. واجهات التطبيق الثلاث
"""

        self.assertEqual(
            parse_dependency_matrix(markdown),
            {"Core": [], "Audit": ["Core"], "Identity": ["Core", "Audit"]},
        )

    def test_approved_graph_has_nineteen_packages_and_ninety_eight_edges(self) -> None:
        validate_package_graph(EXPECTED_PACKAGES)
        self.assertEqual(len(EXPECTED_PACKAGES), 19)
        self.assertEqual(sum(map(len, EXPECTED_PACKAGES.values())), 98)

    def test_cycle_reports_the_closed_path(self) -> None:
        self.assertEqual(
            cycle_path({"A": ["B"], "B": ["A"]}),
            ["A", "B", "A"],
        )

    def test_contract_edges_must_match_package_edges(self) -> None:
        graph = {"Core": [], "Feature": ["Core"]}
        records: list[dict[str, object]] = []

        with self.assertRaisesRegex(CheckFailure, "missing=.*Feature.*Core"):
            validate_contract_edges(graph, records)

    def test_only_table_owner_can_write(self) -> None:
        tables = {
            "orders": {
                "owner": "Orders",
                "writers": ["Orders", "Reporting"],
                "reporting_readers": ["Reporting"],
            }
        }

        with self.assertRaisesRegex(CheckFailure, "orders.*writers"):
            validate_table_ownership(tables, {"Orders": [], "Reporting": []})
