from copy import deepcopy
from unittest import TestCase

from scripts.docs_checks.common import CheckFailure
from scripts.docs_checks.plan_quality import (
    EXPECTED_PLAN_IDS,
    validate_package_owners,
    validate_package_task,
    validate_package_tasks,
    validate_plan_sequence,
    validate_requirement_coverage,
)


def fixture_contract() -> dict[str, object]:
    plans = [
        {
            "id": plan_id,
            "order": number,
            "path": f"docs/superpowers/plans/{number:02d}.md",
            "depends_on": [] if number == 1 else [EXPECTED_PLAN_IDS[number - 2]],
            "outcome": f"Outcome {number}",
            "owned_packages": [],
            "release_gate": f"Gate {number}",
        }
        for number, plan_id in enumerate(EXPECTED_PLAN_IDS, start=1)
    ]
    return {
        "schema_version": 1,
        "implementation_plans": plans,
        "packages": {
            "Core": {
                "owner_plan": "01-foundation-core",
                "owner_task": "Task 5",
                "mandatory_artifacts": [],
                "required_test_kinds": ["unit"],
            }
        },
    }


class PlanContractTest(TestCase):
    def test_sequence_requires_exactly_ten_unique_ordered_plans(self) -> None:
        contract = fixture_contract()
        contract["implementation_plans"] = contract["implementation_plans"][:-1]

        with self.assertRaisesRegex(CheckFailure, "expected 10 implementation plans"):
            validate_plan_sequence(contract)

    def test_sequence_rejects_dependency_on_a_later_plan(self) -> None:
        contract = fixture_contract()
        plans = contract["implementation_plans"]
        plans[0]["depends_on"] = [EXPECTED_PLAN_IDS[-1]]

        with self.assertRaisesRegex(CheckFailure, "depends on later plan"):
            validate_plan_sequence(contract)

    def test_every_package_has_one_build_owner(self) -> None:
        contract = fixture_contract()
        broken = deepcopy(contract)
        broken["packages"]["Core"]["owner_task"] = ""

        with self.assertRaisesRegex(CheckFailure, "Core.*owner"):
            validate_package_owners(broken, expected_packages={"Core"})

    def test_package_task_requires_provider_locales_and_translation_test(self) -> None:
        with self.assertRaisesRegex(CheckFailure, "resources/lang/ar/messages.php"):
            validate_package_task(
                "\n".join(
                    [
                        "packages/Rehla/Core/composer.json",
                        "packages/Rehla/Core/README.md",
                        "packages/Rehla/Core/src/Providers/CoreServiceProvider.php",
                        "packages/Rehla/Core/src/resources/lang/en/messages.php",
                        "packages/Rehla/Core/tests/Architecture/TranslationCompletenessTest.php",
                    ]
                ),
                "Core",
            )

    def test_package_artifacts_must_be_inside_the_owning_task(self) -> None:
        contract = fixture_contract()
        contract["packages"] = {
            "Core": {
                "owner_plan": "01-foundation-core",
                "owner_task": "Task 1",
            }
        }
        text = """### Task 1: Build Core

packages/Rehla/Core/composer.json

### Task 2: Add Missing Files

README.md
src/Providers/CoreServiceProvider.php
src/resources/lang/en/messages.php
src/resources/lang/ar/messages.php
tests/Architecture/TranslationCompletenessTest.php
loadTranslationsFrom and rehla-core
"""

        with self.assertRaisesRegex(CheckFailure, "Core.*README.md"):
            validate_package_tasks(contract, {"01-foundation-core": text})

    def test_requirements_are_exactly_r01_through_r65(self) -> None:
        contract = fixture_contract()
        contract["requirements"] = [
            {
                "requirement_id": f"R{number:02d}",
                "owner_plan": "01-foundation-core",
                "owner_task": "Task 1",
                "acceptance_source": "specs/product-overview.md",
                "verification": "Acceptance test",
                "final_evidence_plan": "10-operations-security-release",
            }
            for number in range(1, 66)
        ]
        validate_requirement_coverage(contract)
        contract["requirements"].pop()
        with self.assertRaisesRegex(CheckFailure, "requirement coverage mismatch"):
            validate_requirement_coverage(contract)
