from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase

from scripts.docs_checks.common import CheckFailure
from scripts.docs_checks.package_architecture import check_layout


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
