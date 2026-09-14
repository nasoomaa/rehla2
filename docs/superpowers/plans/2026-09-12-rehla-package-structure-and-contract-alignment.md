# Rehla Package Structure and Contract Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** توحيد جميع مواصفات ووثائق وخطط رحلة حول بنية الحزم تحت `src/` وخريطة اعتماد وعقود وAPI وقواعد معاملات واحدة قابلة للتحقق آليًا.

**Architecture:** يبقى المستودع في هذه المرحلة corpus وثائقيًا، وتصبح ملفات JSON وCSV والعقود مصادر حقيقة مقروءة آليًا. تضيف الخطة مدققات Python قياسية تبدأ بحالات RED للفجوات الحالية، ثم تصلح المواصفات والمعمارية والخطط، وتنتهي بتقرير تغطية يثبت فحص كل ملف في `docs/` و`specs/`.

**Tech Stack:** Markdown، JSON، CSV، Python 3 standard library، Git.

**Spec:** `docs/superpowers/specs/2026-09-12-rehla-package-structure-and-contract-alignment-design.md`

## Global Constraints

- جذر حزمة `packages/Rehla/<Package>/` يحتوي `composer.json` و`README.md` و`src/` و`tests/` فقط؛ تبقى الاختبارات في الجذر.
- توضع `config/` و`database/` و`resources/` و`routes/` و`openapi/` الخاصة بالحزمة تحت `src/`، ولا تنشأ شجرة فرعية غير مستخدمة.
- تبقى مجلدات Laravel القياسية في جذر التطبيق المضيف خارج هذا المنع.
- خريطة الحزم القانونية تضم 19 حزمة و98 اعتمادًا مباشرًا وتبقى بلا دورات.
- كل اعتماد مباشر له سجل مزود/مستهلك في `docs/architecture/rehla-package-contract-map.json`.
- الحزمة المالكة وحدها تكتب جداولها؛ لا تعبر mutable Models حدود الحزم.
- `tests/` تستخدم `autoload-dev`، ويكون Service Provider في `src/Providers/<Package>ServiceProvider.php`.
- عقد REST v1 يضم 28 عملية بالمعاملات القانونية المحددة في التصميم.
- تستخدم أخطاء API lower dot notation، وتحمل Problem Details كلًا من `trace_id` و`correlation_id` بدلالتيهما المختلفتين.
- الاسم القانوني لمنع تكرار الشراء هو `purchase_attempts`.
- يكتب تغيير المجال وسجل التدقيق والإشعار داخل التطبيق ورسالة outbox المطلوبة في معاملة PostgreSQL نفسها، ولا يحدث I/O خارجي داخلها.
- توقيع PDF هو `%PDF-`، وتنظيف blob يستخدم claim مسيجًا وإعادة محاولة idempotent.
- `Forms` تتحقق من شكل إجابة الملف؛ `Purchasing` تتحقق عبر `Documents` من الملكية والتصنيف والحالة.
- التقارير تتطلب `as_of`، وتعيد صفرًا عند المقام الصفري، وتفصل عدد الطلبات عن قيمتها.
- تحافظ كل خطوة على تغطية `R01` إلى `R65` ولا تصف كود Laravel بأنه منفذ في هذا المستودع الوثائقي.
- لا يدفع أي commit إلى remote ضمن هذه الخطة.

## File Responsibility Map

| الملف أو المجموعة | المسؤولية بعد التنفيذ |
|---|---|
| `scripts/docs_checks/common.py` | تحميل الملفات، جمع رسائل الفشل، تحليل JSON/CSV والروابط النسبية |
| `scripts/docs_checks/package_architecture.py` | فحص بنية الحزم وخريطة الاعتماد والعقود وملكية الجداول |
| `scripts/docs_checks/api_contract.py` | استخراج عمليات REST ومقارنة الطرق والمسارات والمعاملات |
| `scripts/docs_checks/semantic_contracts.py` | فحص المصطلحات وقواعد المعاملات والمستندات والتنفيذ والتقارير |
| `scripts/docs_checks/inventory.py` | فحص R01–R65 والروابط وتطابق manifest الملفات |
| `scripts/docs_checks/run.py` | نقطة تشغيل موحدة للمجموعات أو الفحص الكامل |
| `tests/documentation/` | اختبارات ذاتية للمدققات باستخدام fixtures مؤقتة |
| `docs/architecture/rehla-package-map.json` | القائمة القانونية للاعتمادات المباشرة |
| `docs/architecture/rehla-package-contract-map.json` | تفسير كل اعتماد بعقد واتجاه وملكية معاملة وفشل |
| `docs/architecture/table-ownership.json` | مالك كل جدول والقراء المصرحون |
| `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md` | الصورة المعمارية البشرية المطابقة للخرائط |
| `specs/` | السلوك والعقود والرحلات ومتجهات الاختبار القانونية |
| `docs/superpowers/plans/2026-09-11-rehla-*.md` | خطوات بناء التطبيق بعد محاذاتها مع مصادر الحقيقة |
| `docs/reviews/2026-09-12-rehla-package-contract-alignment-audit.md` | نتيجة الفحص النهائية والفجوات المغلقة والأدلة |
| `docs/reviews/2026-09-12-rehla-documentation-manifest.csv` | صف لكل ملف مفحوص في `docs/` و`specs/` |

---

### Task 1: Create the Documentation Validation Harness

**Files:**
- Create: `scripts/docs_checks/__init__.py`
- Create: `scripts/docs_checks/common.py`
- Create: `scripts/docs_checks/run.py`
- Create: `tests/documentation/__init__.py`
- Create: `tests/documentation/test_common.py`

**Interfaces:**
- Consumes: repository root resolved from `Path(__file__)`.
- Produces: `CheckFailure`, `read_text(path)`, `read_json(path)`, `read_csv(path)`, `relative_markdown_links(path)`, `require(condition, message)`, and CLI group dispatch.

- [ ] **Step 1: Write failing helper tests**

```python
from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase

from scripts.docs_checks.common import CheckFailure, read_json, relative_markdown_links, require


class CommonChecksTest(TestCase):
    def test_require_reports_a_precise_failure(self) -> None:
        with self.assertRaisesRegex(CheckFailure, "package map mismatch"):
            require(False, "package map mismatch")

    def test_json_and_relative_links_are_parsed(self) -> None:
        with TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "target.md").write_text("# Target\n", encoding="utf-8")
            (root / "source.md").write_text("[target](target.md)\n", encoding="utf-8")
            (root / "map.json").write_text('{"schema_version": 1}', encoding="utf-8")
            self.assertEqual(read_json(root / "map.json")["schema_version"], 1)
            self.assertEqual(relative_markdown_links(root / "source.md"), [Path("target.md")])
```

- [ ] **Step 2: Run the tests and confirm RED**

Run: `python3 -m unittest tests.documentation.test_common -v`

Expected: FAIL with `ModuleNotFoundError: No module named 'scripts.docs_checks'`.

- [ ] **Step 3: Implement the shared helpers and group runner**

```python
# scripts/docs_checks/common.py
from __future__ import annotations

import csv
import json
import re
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]


class CheckFailure(AssertionError):
    pass


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
        clean = target.split("#", 1)[0]
        if clean and "://" not in clean and not clean.startswith("mailto:"):
            links.append(Path(clean))
    return links
```

نفذ `run.py` هكذا حتى لا يستورد مجموعة قبل طلبها:

```python
from __future__ import annotations

import argparse
import importlib
import sys

from scripts.docs_checks.common import CheckFailure

GROUPS = {
    "package": "scripts.docs_checks.package_architecture",
    "api": "scripts.docs_checks.api_contract",
    "semantics": "scripts.docs_checks.semantic_contracts",
    "inventory": "scripts.docs_checks.inventory",
}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--group", choices=[*GROUPS, "all"], required=True)
    args = parser.parse_args()
    selected = list(GROUPS) if args.group == "all" else [args.group]
    try:
        for group in selected:
            importlib.import_module(GROUPS[group]).check()
            print(f"PASS {group}")
    except (CheckFailure, ModuleNotFoundError) as error:
        print(f"FAIL {group}: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
```

- [ ] **Step 4: Verify the harness**

Run: `python3 -m unittest discover -s tests/documentation -v`

Expected: 2 tests PASS.

Run: `python3 -m scripts.docs_checks.run --help`

Expected: يعرض المجموعات `package`, `api`, `semantics`, `inventory`, و`all` وينتهي بـexit code 0. لا تشغل `all` قبل إنشاء وحدات المجموعات في المهام اللاحقة.

- [ ] **Step 5: Commit**

```bash
git add scripts/docs_checks tests/documentation
git commit -m "test(docs): add documentation validation harness"
```

### Task 2: Move Every Package-Owned Runtime Path Under `src`

**Files:**
- Create: `scripts/docs_checks/package_architecture.py`
- Create: `tests/documentation/test_package_architecture.py`
- Modify: `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md:49-214`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-01-foundation-core.md:145-205`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-02-identity-platform-services.md:23-305`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-03-catalog-forms-content.md:23-205`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-04-wallet-topups.md:23-195`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md:23-375`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-06-reporting-integrations.md:23-95`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-07-interfaces-operations-release.md:23-275`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-platform-build.md:12-25`
- Modify: `docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md:9-18`

**Interfaces:**
- Consumes: package paths written in Markdown backticks and tree blocks.
- Produces: `check_layout(paths: list[Path]) -> None`; every package resource path starts with `packages/Rehla/<Package>/src/`, providers use `src/Providers/`, and root tests remain `packages/Rehla/<Package>/tests/`.

- [ ] **Step 1: Write fixture tests for accepted and rejected layouts**

```python
from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase

from scripts.docs_checks.common import CheckFailure
from scripts.docs_checks.package_architecture import check_layout


class PackageLayoutTest(TestCase):
    def test_rejects_package_resources_at_package_root(self) -> None:
        with TemporaryDirectory() as directory:
            path = Path(directory) / "bad.md"
            path.write_text("`packages/Rehla/Api/routes/api_v1.php`", encoding="utf-8")
            with self.assertRaisesRegex(CheckFailure, "Api/routes"):
                check_layout([path])

    def test_accepts_src_resources_and_root_tests(self) -> None:
        with TemporaryDirectory() as directory:
            path = Path(directory) / "good.md"
            path.write_text(
                "`packages/Rehla/Api/src/routes/api_v1.php`\n"
                "`packages/Rehla/Api/tests/Feature/AuthApiTest.php`\n",
                encoding="utf-8",
            )
            check_layout([path])
```

- [ ] **Step 2: Implement the narrow layout check and prove current RED**

استخدم regex الآتي على وثيقة المعمارية والخطة المختصرة وخطط البناء `2026-09-11-*.md` فقط، مع الإبلاغ عن الملف والسطر والنص. استبعد وثيقة التصميم وخطة المحاذاة الحالية لأنهما تعرضان المسار القديم ضمن وصف الفجوة والتحويل ولا يمثلانه كهدف تنفيذ:

```python
FORBIDDEN_PACKAGE_ROOT = re.compile(
    r"packages/Rehla/(?P<package>[A-Za-z]+)/"
    r"(?P<directory>config|database|resources|routes|openapi)(?:/|`|\b)"
)


def check_layout(paths: list[Path]) -> None:
    failures: list[str] = []
    for path in paths:
        for number, line in enumerate(read_text(path).splitlines(), start=1):
            match = FORBIDDEN_PACKAGE_ROOT.search(line)
            if match:
                failures.append(f"{path}:{number}: {match.group('package')}/{match.group('directory')}")
            provider = re.search(r"packages/Rehla/([A-Za-z]+)/src/\1ServiceProvider\.php", line)
            if provider:
                failures.append(f"{path}:{number}: provider must be under src/Providers")
    require(not failures, "\n".join(failures))
```

أضف فحصًا ثانيًا يرفض `src/<Package>ServiceProvider.php` ويقبل فقط `src/Providers/<Package>ServiceProvider.php`. لا تطبق المنع على `config/` أو`database/` أو`resources/` أو`routes/` الخاصة بتطبيق Laravel المضيف.

حلل أيضًا fenced tree blocks التي تبدأ بـ`packages/Rehla/<Package>/`: تتبع مستوى البادئة `│`، وارفض مجلد موارد يظهر في مستوى `src/` و`tests/` نفسه. يقبل الفحص مجلد الموارد عندما يكون داخل فرع `src/` فقط.

Run: `python3 -m unittest tests.documentation.test_package_architecture -v`

Expected: 2 tests PASS.

Run: `python3 -m scripts.docs_checks.run --group package`

Expected: FAIL ويعرض مواضع المسارات السبعة والعشرين الحالية وأمثلة Service Provider غير المطابقة.

- [ ] **Step 3: Rewrite the canonical architecture trees**

في قسم «بنية كل حزمة أعمال» ضع `Providers`, `Actions`, `Queries`, `Contracts`, `Data`, `Domain`, `Models`, `Enums`, `Events`, `Exceptions`, `Policies`, `Infrastructure`, ثم موارد الحزمة تحت `src/`. ضع `tests/` فقط بجوار `src/`.

في أشجار `Web` و`Api` و`Admin` انقل المسارات وOpenAPI والقوالب والترجمات والأصول تحت `src/`. أضف فقرة تلزم Service Provider باستعمال `loadMigrationsFrom`, `loadRoutesFrom`, `loadViewsFrom`, `loadTranslationsFrom`, و`mergeConfigFrom` مع مسارات `src/` الفعلية.

- [ ] **Step 4: Rewrite every affected implementation-plan path**

طبق التحويلات الحرفية الآتية في الخطط السبع:

```text
packages/Rehla/<P>/database/...  -> packages/Rehla/<P>/src/database/...
packages/Rehla/<P>/config/...    -> packages/Rehla/<P>/src/config/...
packages/Rehla/<P>/resources/... -> packages/Rehla/<P>/src/resources/...
packages/Rehla/<P>/routes/...    -> packages/Rehla/<P>/src/routes/...
packages/Rehla/<P>/openapi/...   -> packages/Rehla/<P>/src/openapi/...
packages/Rehla/<P>/src/<P>ServiceProvider.php
                                 -> packages/Rehla/<P>/src/Providers/<P>ServiceProvider.php
```

حدث namespaces وأمثلة اكتشاف المزود في خطة Foundation إلى `Rehla\<Package>\Providers\<Package>ServiceProvider`. أضف قيد البنية إلى الخطة الرئيسية والخطة المختصرة، ولا تنقل `tests/` إلى `src/`.

- [ ] **Step 5: Verify all package paths**

Run: `python3 -m scripts.docs_checks.run --group package`

Expected: PASS لقسم layout، مع صفر package-owned runtime paths خارج `src/`.

Run: `rg -n 'packages/Rehla/.+/(config|database|resources|routes|openapi)/' docs/superpowers/plans/2026-09-11-*.md docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

Expected: كل نتيجة تحتوي `/src/` قبل اسم المجلد.

- [ ] **Step 6: Commit**

```bash
git add scripts/docs_checks/package_architecture.py tests/documentation/test_package_architecture.py docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md docs/superpowers/plans
git commit -m "docs: place package runtime resources under src"
```

### Task 3: Make Dependencies, Contracts, and Table Ownership Machine-Readable

**Files:**
- Modify: `scripts/docs_checks/package_architecture.py`
- Modify: `tests/documentation/test_package_architecture.py`
- Modify: `docs/architecture/rehla-package-map.json`
- Create: `docs/architecture/rehla-package-contract-map.json`
- Create: `docs/architecture/table-ownership.json`
- Modify: `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md:216-291`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-01-foundation-core.md:145-285`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-platform-build.md:12-82`
- Modify: `docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md:9-48`

**Interfaces:**
- Consumes: approved matrix in the design spec.
- Produces: exact package graph, one contract record per graph edge, one owner per declared table, and `check_dependency_artifacts() -> None`.

- [ ] **Step 1: Add failing assertions for the exact package graph**

```python
EXPECTED_PACKAGES = {
    "Core": [],
    "Audit": ["Core"],
    "Identity": ["Core", "Audit"],
    "Documents": ["Core", "Identity", "Audit"],
    "Travelers": ["Core", "Identity", "Audit"],
    "Wallet": ["Core", "Identity", "Audit"],
    "Notifications": ["Core", "Identity", "Audit"],
    "Catalog": ["Core", "Documents", "Audit"],
    "Forms": ["Core", "Catalog", "Audit"],
    "Content": ["Core", "Audit"],
    "TopUps": ["Core", "Identity", "Documents", "Wallet", "Audit", "Notifications"],
    "Orders": ["Core"],
    "Purchasing": ["Core", "Identity", "Catalog", "Forms", "Travelers", "Documents", "Wallet", "Orders", "Audit", "Notifications"],
    "Fulfillment": ["Core", "Identity", "Orders", "Forms", "Documents", "Purchasing", "Audit", "Notifications"],
    "Integrations": ["Core", "Notifications"],
    "Reporting": ["Core", "Identity", "Travelers", "TopUps", "Orders", "Fulfillment"],
    "Web": ["Core", "Identity", "Catalog", "Forms", "Travelers", "Documents", "Wallet", "TopUps", "Orders", "Fulfillment", "Purchasing", "Notifications", "Content", "Integrations"],
    "Api": ["Core", "Identity", "Catalog", "Forms", "Travelers", "Documents", "Wallet", "TopUps", "Orders", "Fulfillment", "Purchasing", "Notifications", "Integrations"],
    "Admin": ["Core", "Identity", "Catalog", "Forms", "Travelers", "Documents", "Wallet", "TopUps", "Orders", "Fulfillment", "Notifications", "Content", "Audit", "Reporting"],
}
```

اختبر أن عدد المفاتيح 19، ومجموع الاعتمادات 98، وكل provider موجود، ولا توجد self-edge أوcycle.

Run: `python3 -m unittest tests.documentation.test_package_architecture -v`

Expected: FAIL لأن الخريطة الحالية تفتقد `Identity -> Audit`, و`Notifications -> Audit`, و`Api -> Integrations`، وتحتوي حواف غير قانونية في `Integrations` و`Reporting`.

- [ ] **Step 2: Update the package map exactly**

استبدل `packages` بالقاموس السابق مع الحفاظ على قواعد العرض، وارفع `schema_version` إلى `2`. اجعل `meaning` يقرر أن القائمة exhaustive، وأن اعتماد Composer والاستيراد الثابت يجب أن يطابقاها.

- [ ] **Step 3: Create the contract map with edge-complete records**

استخدم البنية الآتية لكل اعتماد مباشر من القاموس السابق:

```json
{
  "consumer": "Purchasing",
  "provider": "Wallet",
  "surfaces": ["WalletReader", "WalletDebitor"],
  "mode": "sync",
  "transaction_owner": "Purchasing",
  "write_rule": "provider_command_only",
  "failure_rule": "abort_purchase_transaction"
}
```

يجب أن تكون مجموعة `(consumer, provider)` في `dependencies` مساوية تمامًا لحواف `rehla-package-map.json`. استخدم أسماء الأسطح القانونية الآتية بحسب provider:

| Provider | الأسطح العامة المسموحة |
|---|---|
| `Core` | `Money`, `OpaqueId`, `Clock`, `ProblemCode` |
| `Audit` | `AuditWriter`, `AuditLogReader` |
| `Identity` | `AuthorizesActor`, `RegisterCustomer`, `IdentityReader`, `RegistrationWalletInitializer`, `RegistrationNotificationRecorder` |
| `Documents` | `OwnedDocuments`, `DocumentDownloadAuthorizer`, `DocumentReference` |
| `Travelers` | `TravelerReader`, `TravelerSnapshotReader` |
| `Wallet` | `WalletReader`, `WalletCreditor`, `WalletDebitor`, `RegistrationWalletInitializerImplementation` |
| `Notifications` | `OutboxWriter`, `NotificationReader`, `NotificationRecorder`, `NotificationChannel`, `RegistrationNotificationRecorderImplementation` |
| `Catalog` | `ServiceCatalog`, `ServiceQuoteReader`, `PublishedFulfillmentPolicyReader` |
| `Forms` | `PublishedFormReader`, `FormSubmissionValidator` |
| `Content` | `PublishedContentReader`, `ContentAdminCommands` |
| `TopUps` | `TopUpReader`, `TopUpAdminCommands`, `BankAccountReader` |
| `Orders` | `OrderWriter`, `OrderReader`, `OrderReportingSource` |
| `Purchasing` | `SubmitOrder`, `ExecutionCreator` |
| `Fulfillment` | `ExecutionCreatorImplementation`, `ExecutionReader`, `FulfillmentAdminCommands`, `FulfillmentReportingSource` |
| `Integrations` | `InquiryLinkBuilder`, `NotificationChannelAdapters` |
| `Reporting` | `ProductMetricsReader` |

حزم العرض لا تكون provider لحزمة أعمال. سجلات `Reporting` تستخدم `mode = reporting_read` و`write_rule = no_source_writes`. تسجل منافذ التسجيل و`ExecutionCreator` في قسم `inversions` مع المالك والمنفذ والربط المحدد.

- [ ] **Step 4: Create the table ownership map**

استخدم schema:

```json
{
  "schema_version": 1,
  "tables": {
    "purchase_attempts": {
      "owner": "Purchasing",
      "writers": ["Purchasing"],
      "reporting_readers": []
    }
  },
  "rules": {
    "one_owner": true,
    "cross_package_writes": "provider_command_only",
    "mutable_models_cross_boundary": false
  }
}
```

سجل الجداول المخططة في وثيقة المعمارية وخطط migrations: Identity (`users`, `staff_profiles`, `roles`, `abilities`, `role_ability`, `user_role`, `personal_access_tokens`)، Audit (`audit_entries`)، Documents (`upload_sessions`, `documents`)، Travelers (`travelers`)، Wallet (`wallets`, `wallet_ledger_entries`, `wallet_reconciliation_runs`)، Notifications (`notifications`, `outbox_messages`, `outbox_delivery_attempts`)، Catalog (`services`, `service_requirements`, `service_media`, `service_price_history`, `fulfillment_policy_drafts`, `fulfillment_policy_versions`)، Forms (`form_drafts`, `form_versions`)، Content (`content_pages`)، TopUps (`bank_accounts`, `top_up_settings`, `top_up_requests`)، Orders (`orders`, `order_service_snapshots`, `order_traveler_snapshots`, `order_form_snapshots`)، Purchasing (`purchase_attempts`)، Fulfillment (`service_executions`, `execution_status_history`, `execution_internal_notes`, `customer_action_requests`, `customer_action_responses`, `execution_documents`). تسجل Reporting views كـ`objects` مملوكة لـReporting وليست جداول مصدر. تبقى Core وIntegrations وWeb وApi وAdmin بلا جداول أعمال في المرحلة الأولى.

- [ ] **Step 5: Implement graph, contract, and ownership validation**

أضف إلى `package_architecture.py` تحققًا من:

```python
edges = {(consumer, provider) for consumer, providers in packages.items() for provider in providers}
contract_edges = {(row["consumer"], row["provider"]) for row in contract_map["dependencies"]}
require(edges == contract_edges, f"contract edge mismatch: missing={sorted(edges-contract_edges)}, extra={sorted(contract_edges-edges)}")
require(len(edges) == 98, f"expected 98 dependency edges, got {len(edges)}")
require(all(record["owner"] in EXPECTED_PACKAGES for record in tables.values()), "unknown table owner")
require(all(record["writers"] == [record["owner"]] for record in tables.values()), "cross-package table writer")
```

نفذ DFS بثلاث حالات لاكتشاف الدورات وإظهار مسار الدورة عند الفشل:

```python
def cycle_path(graph: dict[str, list[str]]) -> list[str] | None:
    state: dict[str, str] = {name: "unseen" for name in graph}
    stack: list[str] = []

    def visit(node: str) -> list[str] | None:
        state[node] = "visiting"
        stack.append(node)
        for dependency in graph[node]:
            if state[dependency] == "visiting":
                start = stack.index(dependency)
                return [*stack[start:], dependency]
            if state[dependency] == "unseen":
                found = visit(dependency)
                if found:
                    return found
        stack.pop()
        state[node] = "done"
        return None

    for package in graph:
        if state[package] == "unseen":
            found = visit(package)
            if found:
                return found
    return None
```

اختبر fixture فيه `A -> B -> A` واختبر missing contract edge وduplicate table owner.

- [ ] **Step 6: Align human architecture and foundation plans**

استبدل مصفوفة الاعتماد البشرية بالمصفوفة القانونية، واشرح الحواف المضافة والمحذوفة. حدث Foundation بحيث يولد Composer requires من map v2 ويتحقق من مساواتها، ويقرأ `table-ownership.json` وcontract map في اختبارات Architecture. حدث الخطة الرئيسية والمختصرة لتذكر الخرائط الثلاث كمصادر حقيقة.

- [ ] **Step 7: Verify and commit**

Run: `python3 -m unittest tests.documentation.test_package_architecture -v`

Expected: tests الخاصة بالgraph/cycle/edge/table fixtures كلها PASS.

Run: `python3 -m scripts.docs_checks.run --group package`

Expected: PASS مع `19 packages, 98 edges, 98 contract edge records, 0 cycles, 1 owner per table`.

```bash
git add scripts/docs_checks/package_architecture.py tests/documentation/test_package_architecture.py docs/architecture docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md docs/superpowers/plans/2026-09-11-rehla-01-foundation-core.md docs/superpowers/plans/2026-09-11-rehla-platform-build.md
git commit -m "docs: define package contracts and data ownership"
```

### Task 4: Align Cross-Package Orchestration Contracts

**Files:**
- Create: `scripts/docs_checks/semantic_contracts.py`
- Create: `tests/documentation/test_semantic_contracts.py`
- Modify: `specs/domains/identity-and-access.md:61-76,136-145`
- Modify: `specs/domains/wallet-and-ledger.md:62-76,145-152`
- Modify: `specs/domains/notifications.md:87-102,158-166`
- Modify: `specs/domains/application-forms.md:91-130`
- Modify: `specs/domains/documents.md:96-115,142-150`
- Modify: `specs/domains/orders-and-purchasing.md:66-155`
- Modify: `specs/domains/fulfillment.md:100-108,192-200`
- Modify: `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md:123-150,450-518`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-02-identity-platform-services.md:23-85,260-310`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-03-catalog-forms-content.md:90-165`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-04-wallet-topups.md:23-95`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md:90-225,285-365`
- Modify: `docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md:70-190`

**Interfaces:**
- Consumes: inversion records from `rehla-package-contract-map.json`.
- Produces: explicit synchronous registration ports, shape-only Forms validation, Documents-owned file authorization, and Purchasing-owned `ExecutionCreator` implemented by Fulfillment.

- [ ] **Step 1: Write semantic assertions that expose the current contradictions**

```python
def require_all(path: str, fragments: list[str]) -> None:
    text = read_text(ROOT / path)
    for fragment in fragments:
        require(fragment in text, f"{path}: missing {fragment!r}")


def reject_all(path: str, fragments: list[str]) -> None:
    text = read_text(ROOT / path)
    for fragment in fragments:
        require(fragment not in text, f"{path}: stale {fragment!r}")
```

اختبر أن Identity تحتوي `RegistrationWalletInitializer` و`RegistrationNotificationRecorder` و`synchronously` وtransaction rollback، وأنها لا تصف `CustomerRegistered` كآلية إنشاء المحفظة/الإشعار. اختبر أن Forms تصف `document_id` opaque وشكل الإجابة فقط ولا تنص على فحص `clean` أوملكية العميل. اختبر أن Purchasing تملك `ExecutionCreator` وأن Fulfillment تنفذه.

Run: `python3 -m unittest tests.documentation.test_semantic_contracts -v`

Expected: tests الذاتية PASS بعد إنشاء دوال المساعدة.

Run: `python3 -m scripts.docs_checks.run --group semantics`

Expected: FAIL على حدث التسجيل الحالي والتحقق من المستندات داخل Forms.

- [ ] **Step 2: Replace event-driven registration initialization with explicit ports**

عرف في Identity العقدين حرفيًا:

```php
interface RegistrationWalletInitializer
{
    public function initialize(string $accountId): void;
}

interface RegistrationNotificationRecorder
{
    public function recordWelcome(string $accountId, string $locale, string $correlationId): void;
}
```

ينفذ Wallet الأول، وتنفذ Notifications الثاني. يستدعي `RegisterCustomer` الاثنين داخل معاملة التسجيل بعد إنشاء account وقبل commit. يسجل Audit في المعاملة نفسها. أي فشل يرمي exception ويرجع كامل العملية. يسمح `CustomerRegistered` بعد commit لأغراض تحليلية اختيارية فقط، ولا يحمل أثرًا مطلوبًا لصحة التسجيل.

حدث خطة Identity لتنشئ interfaces تحت `Identity/src/Contracts/`، وimplementations تحت Wallet وNotifications، واختبار registration rollback وbindings. أضف اعتماد Identity على Audit وNotifications على Audit في النص والخطط.

- [ ] **Step 3: Move document authorization out of Forms**

غير ناتج `FormSubmissionValidator` إلى:

```php
public function validate(string $formVersionId, array $answers): ValidatedSubmission;
```

تتحقق Forms من وجود field keys وأن `file_upload` يحمل opaque `document_id` وأن `image_upload` يحمل opaque `document_id` أوالقائمة المسموحة حسب schema. لا تستعلم عن Documents.

بعد نجاح التحقق البنيوي يستخرج Purchasing مراجع المستندات والتصنيفات المطلوبة ويستدعي:

```php
OwnedDocuments::assertCleanOwned(
    array $documentIds,
    string $ownerId,
    array $requiredClassifications,
): array;
```

حدث فشل المستند إلى `document.invalid_attachment` في Purchasing/Documents، ولا تجعل Forms ترجع `form.invalid_document_attachment` لحالة ملكية أوscan.

- [ ] **Step 4: Make the execution inversion explicit everywhere**

ثبت أن `ExecutionCreator` موجود في `Purchasing/src/Contracts/ExecutionCreator.php`، وأن `Fulfillment/src/Infrastructure/PurchasingExecutionCreator.php` ينفذه، وأن `FulfillmentServiceProvider` يربط interface بالتنفيذ. لا تضف `Purchasing -> Fulfillment` في Composer. أبق إنشاء execution داخل معاملة `SubmitOrder` التي يملكها Purchasing.

- [ ] **Step 5: Verify and commit**

Run: `python3 -m scripts.docs_checks.run --group semantics && python3 -m scripts.docs_checks.run --group package`

Expected: PASS لكل عقود التسجيل والنماذج والمستندات وExecutionCreator مع بقاء الرسم بلا دورة.

```bash
git add scripts/docs_checks/semantic_contracts.py tests/documentation/test_semantic_contracts.py specs/domains docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md docs/superpowers/plans/2026-09-11-rehla-02-identity-platform-services.md docs/superpowers/plans/2026-09-11-rehla-03-catalog-forms-content.md docs/superpowers/plans/2026-09-11-rehla-04-wallet-topups.md docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md
git commit -m "docs: align cross-package orchestration contracts"
```

### Task 5: Unify REST API, Error Codes, and Trace Semantics

**Files:**
- Create: `scripts/docs_checks/api_contract.py`
- Create: `tests/documentation/test_api_contract.py`
- Modify: `specs/contracts/customer-rest-api-v1.md:9-338`
- Modify: `specs/domains/orders-and-purchasing.md:39-50,134-143`
- Modify: `specs/journeys/journey-08-order-submission-edge-cases.md:102-115`
- Modify: `specs/test-vectors/idempotency-and-deduplication.md:9-31`
- Modify: `specs/cross-cutting/localization-accessibility-and-errors.md:13-30`
- Modify: `specs/domains/audit.md:17-35,61-84`
- Modify: `specs/cross-cutting/operational-reliability.md:11-27`
- Modify: `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md:293-400,623-650`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-07-interfaces-operations-release.md:146-275,399-450`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md:90-145,285-375`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-platform-build.md:83-180`
- Modify: `docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md:220-238`

**Interfaces:**
- Consumes: Markdown endpoint headings and fenced route matrices.
- Produces: `canonical_operation(method, path)`, `extract_operations(text)`, and exact equality to the 28-operation set.

- [ ] **Step 1: Write parser tests and the exact operation set**

```python
EXPECTED_API = {
    ("POST", "/api/v1/auth/register"),
    ("POST", "/api/v1/auth/login"),
    ("POST", "/api/v1/auth/logout"),
    ("GET", "/api/v1/me"),
    ("PATCH", "/api/v1/me"),
    ("GET", "/api/v1/services"),
    ("GET", "/api/v1/services/{service_slug}"),
    ("GET", "/api/v1/services/{service_slug}/application-form"),
    ("GET", "/api/v1/travelers"),
    ("POST", "/api/v1/travelers"),
    ("GET", "/api/v1/travelers/{traveler_id}"),
    ("PATCH", "/api/v1/travelers/{traveler_id}"),
    ("GET", "/api/v1/wallet"),
    ("GET", "/api/v1/wallet/entries"),
    ("GET", "/api/v1/bank-accounts"),
    ("GET", "/api/v1/top-ups"),
    ("POST", "/api/v1/top-ups"),
    ("GET", "/api/v1/top-ups/{top_up_id}"),
    ("PUT", "/api/v1/top-ups/{top_up_id}/receipt"),
    ("POST", "/api/v1/uploads"),
    ("GET", "/api/v1/uploads/{document_id}"),
    ("GET", "/api/v1/documents/{document_id}/content"),
    ("POST", "/api/v1/order-submissions"),
    ("GET", "/api/v1/orders"),
    ("GET", "/api/v1/orders/{order_reference}"),
    ("POST", "/api/v1/executions/{execution_id}/actions/{action_request_id}/responses"),
    ("GET", "/api/v1/notifications"),
    ("POST", "/api/v1/notifications/{notification_id}/read"),
}
```

اختبر parser بنص صغير يحوي heading مفردًا وheading يجمع `GET` و`PATCH`. اجعل parser يوسع العمليات المجمعة بدل عدها عملية واحدة.

نفذ الاستخراج والتطبيع هكذا:

```python
OPERATION = re.compile(r"\b(GET|POST|PUT|PATCH|DELETE)\s+(/api/v1/[^\s`&,]+)")


def extract_operations(text: str) -> set[tuple[str, str]]:
    return {(method, path.rstrip(".);")) for method, path in OPERATION.findall(text)}


def check_operation_set(path: Path) -> None:
    actual = extract_operations(read_text(path))
    require(
        actual == EXPECTED_API,
        f"{path}: missing={sorted(EXPECTED_API-actual)}, extra={sorted(actual-EXPECTED_API)}",
    )
```

خصص extractor لعقد REST ليقرأ headings تحت القسم 4 فقط حتى لا يعد ذكر route في الشرح مرتين. خصص extractor للمعمارية ليقرأ fenced block الموسوم «المسارات الأساسية للإصدار الأول».

- [ ] **Step 2: Prove the current API mismatch**

Run: `python3 -m unittest tests.documentation.test_api_contract -v`

Expected: parser fixture tests PASS.

Run: `python3 -m scripts.docs_checks.run --group api`

Expected: FAIL لأن المعمارية تفتقد `GET uploads/{document_id}` و`PUT top-ups/{top_up_id}/receipt`، ولأن عقد REST يستخدم `{id}`, `{order_ref}`, و`{action_id}`.

- [ ] **Step 3: Normalize the REST contract and architecture**

استبدل جميع placeholder names بالأسماء الموجودة في `EXPECTED_API`. أضف المسارين المفقودين إلى معمارية API ومصفوفة التفويض. حدث وصف top-up receipt ليحافظ على الطلب نفسه والمرجع نفسه في `under_review`. حدث خطة Api Task 4 لتضم `ReplaceTopUpReceiptRequest`, action/controller method لحالة upload، واختبار ownership لكل مسار جديد. ضع المسارات في `Api/src/routes/api_v1.php` وOpenAPI في `Api/src/openapi/rehla-v1.yaml`.

صحح قسم producer/consumer: حزمة Api تنتج REST للعملاء native والمستهلكين المصرح لهم؛ حزمة Web تستعمل sessions وعقود المجالات مباشرة ولا تستدعي REST داخليًا. ثبت أن Sanctum API tokens للعملاء لا تحمل staff abilities.

- [ ] **Step 4: Define both identifiers in every cross-cutting contract**

استخدم التعريفين الحرفيين:

```text
trace_id: unique identifier for one HTTP request or one queued-job attempt; changes on retry.
correlation_id: stable identifier for one logical business operation across retries, audit, notifications, and outbox.
```

اجعل Problem Details يحتوي `trace_id` و`correlation_id`. يقبل HTTP `X-Correlation-ID` صالحًا أوينشئ واحدًا، بينما ينشئ التطبيق `trace_id` لكل محاولة ولا يثق بقيمة عميل له. تخزن audit entries `correlation_id`، وتخزن diagnostics/telemetry `trace_id` عند الحاجة.

- [ ] **Step 5: Normalize public error codes**

اجعل regex القانوني `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$`. استبدل اختبار `UNAUTHENTICATED` في خطة API بـ`auth.unauthenticated`. وحد تعارض المفتاح المختلف على `idempotency.key_reused` في REST والمجال والرحلات ومتجه الاختبار والخطط؛ تبقى أسماء enum الداخلية حرة بشرط أن تكون القيمة المنشورة lower dot notation.

- [ ] **Step 6: Verify and commit**

Run: `python3 -m scripts.docs_checks.run --group api`

Expected: PASS مع `28/28 operations`, وأسماء المعاملات الثمانية القانونية، وكلا معرفي التتبع، وصفر public uppercase codes.

```bash
git add scripts/docs_checks/api_contract.py tests/documentation/test_api_contract.py specs/contracts/customer-rest-api-v1.md specs/cross-cutting specs/domains/audit.md specs/domains/orders-and-purchasing.md specs/journeys/journey-08-order-submission-edge-cases.md specs/test-vectors/idempotency-and-deduplication.md docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md docs/superpowers/plans/2026-09-11-rehla-07-interfaces-operations-release.md docs/superpowers/plans/2026-09-11-rehla-platform-build.md
git commit -m "docs: unify REST and tracing contracts"
```

### Task 6: Harden Transactions, Idempotency, Outbox, and Document Cleanup

**Files:**
- Modify: `scripts/docs_checks/semantic_contracts.py`
- Modify: `tests/documentation/test_semantic_contracts.py`
- Modify: `specs/domains/orders-and-purchasing.md:17-94`
- Modify: `specs/domains/identity-and-access.md:61-76`
- Modify: `specs/domains/top-ups.md:69-136`
- Modify: `specs/domains/fulfillment.md:100-160`
- Modify: `specs/journeys/journey-05-service-order-and-instant-purchase.md:40-112,144-170`
- Modify: `specs/journeys/journey-08-order-submission-edge-cases.md:87-115`
- Modify: `specs/test-vectors/idempotency-and-deduplication.md:9-56`
- Modify: `specs/contracts/outbox-and-notifications-delivery.md:13-70`
- Modify: `specs/domains/notifications.md:47-116,144-166`
- Modify: `specs/contracts/storage-and-document-pipeline.md:43-80`
- Modify: `specs/domains/documents.md:27-46,109-125`
- Modify: `specs/cross-cutting/operational-reliability.md:3-27`
- Modify: `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md:450-563,602-622`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-02-identity-platform-services.md:145-205,260-310`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-04-wallet-topups.md:95-205`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md:90-145,285-375`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-06-reporting-integrations.md:94-145`
- Modify: `docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md:80-110,160-198`

**Interfaces:**
- Consumes: transaction and retry rules from domain contracts.
- Produces: one purchase-attempt vocabulary, transaction-owned effects, fenced outbox completion, and recoverable object cleanup.

- [ ] **Step 1: Add failing semantic checks**

اختبر الشروط الآتية آليًا:

```python
reject_all("specs/journeys/journey-05-service-order-and-instant-purchase.md", ["idempotency_keys"])
reject_all("specs/journeys/journey-08-order-submission-edge-cases.md", ["idempotency_keys"])
require_all("specs/domains/orders-and-purchasing.md", ["purchase_attempts", "price version", "form version"])
require_all("specs/contracts/outbox-and-notifications-delivery.md", ["lock_token", "lease_expires_at", "former worker"])
require_all("specs/contracts/storage-and-document-pipeline.md", ["claim", "fence", "idempotent retry"])
require_all("specs/domains/documents.md", ["%PDF-"])
reject_all("specs/domains/documents.md", ["First 4 bytes must be `%PDF`", "deletes underlying storage blobs atomically"])
```

أضف check مشتركًا يلزم نصوص الشراء والشحن والتنفيذ بتضمين domain state وAudit وin-app notification وoutbox في المعاملة نفسها، ويلزم النص نفسه بمنع external I/O داخل المعاملة.

Run: `python3 -m scripts.docs_checks.run --group semantics`

Expected: FAIL على `idempotency_keys` في الرحلتين، `%PDF` ذي الأربع بايتات، والادعاء الذري لحذف blob.

- [ ] **Step 2: Align purchase attempts and captured versions**

استبدل `idempotency_keys` بـ`purchase_attempts` في الرحلات. سجل الصف قبل بقية أقفال الشراء بمفتاح unique `(account_id, idempotency_key)` وبصمة canonical. ثبت `accepted_price_version_id`, `form_version_id`, و`fulfillment_policy_version_id` ضمن البصمة والصف/النتيجة. تعيد البصمة المطابقة النتيجة المخزنة، وتعيد المختلفة `idempotency.key_reused`.

- [ ] **Step 3: Align transactional side effects and outbox fencing**

لكل عملية تسجيل/شحن/شراء/انتقال تنفيذ، اكتب داخل معاملة PostgreSQL نفسها: تغيير المجال، Audit، in-app notification، وoutbox للقنوات الخارجية المطلوبة. ضع إرسال الشبكة في العامل بعد commit فقط.

في outbox اجعل claim يولد `lock_token` جديدًا ويحدد `lease_expires_at`. يشترط `MarkDelivered` و`MarkFailed` تطابق `id`, `locked_by`, `lock_token` وأن lease ما زال صالحًا. affected rows = 0 تعني stale worker ولا تغير حالة الرسالة. أضف إلى خطة worker اختبار عامل A تنتهي مهلة حجزه، يحجز B الرسالة، ثم يفشل acknowledge من A وينجح B.

- [ ] **Step 4: Replace impossible cross-system atomic deletion**

عرف cleanup بثلاث مراحل:

1. معاملة قصيرة تقفل document وتثبت أنه orphan وتولد `cleanup_claim_token` وحالة `cleanup_claimed`.
2. حذف blob خارج المعاملة باستخدام storage key المقروء من claim.
3. معاملة قصيرة تطابق token وتثبت `purged`; إذا كان blob غير موجود تعامل العملية كنجاح idempotent.

إذا فشل حذف blob تبقى claim قابلة للاستعادة بعد lease. لا يستطيع attach ربط record في `cleanup_claimed`. لا تصف حذف PostgreSQL وobject storage بأنه atomic.

صحح magic bytes إلى `%PDF-` = `0x25 0x50 0x44 0x46 0x2D` في domain والعقد والخطط.

- [ ] **Step 5: Verify and commit**

Run: `python3 -m scripts.docs_checks.run --group semantics`

Expected: PASS لقواعد purchase attempts وoutbox fencing والتنظيف وPDF والمعاملات.

Run: `rg -n 'idempotency_keys|First 4 bytes must be `%PDF`|deletes underlying storage blobs atomically' specs docs --glob '!docs/superpowers/specs/2026-09-12-rehla-package-structure-and-contract-alignment-design.md' --glob '!docs/superpowers/plans/2026-09-12-rehla-package-structure-and-contract-alignment.md'`

Expected: no matches.

```bash
git add scripts/docs_checks/semantic_contracts.py tests/documentation/test_semantic_contracts.py specs docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md docs/superpowers/plans/2026-09-11-rehla-02-identity-platform-services.md docs/superpowers/plans/2026-09-11-rehla-04-wallet-topups.md docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md docs/superpowers/plans/2026-09-11-rehla-06-reporting-integrations.md
git commit -m "docs: harden transaction and retry contracts"
```

### Task 7: Make Forms, Fulfillment, and Reporting Deterministic

**Files:**
- Modify: `scripts/docs_checks/semantic_contracts.py`
- Modify: `tests/documentation/test_semantic_contracts.py`
- Modify: `specs/domains/application-forms.md:91-130`
- Modify: `specs/domains/documents.md:142-150`
- Modify: `specs/domains/fulfillment.md:45-198`
- Modify: `specs/contracts/service-fulfillment-sop.md:23-51`
- Modify: `specs/test-vectors/state-machines-and-transitions.md:33-61`
- Modify: `specs/test-vectors/form-schema-validation-and-evaluation.md:9-55`
- Modify: `specs/journeys/journey-06-execution-tracking-and-customer-action.md:34-150`
- Modify: `specs/domains/reporting.md:16-115`
- Modify: `specs/domains/wallet-and-ledger.md:145-152`
- Modify: `specs/test-vectors/reporting-metrics-calculations.md:9-90`
- Modify: `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md:564-586,653-673`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-03-catalog-forms-content.md:90-165`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md:145-285`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-06-reporting-integrations.md:23-94`
- Modify: `docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md:120-210`

**Interfaces:**
- Consumes: captured fulfillment policy and metric definitions.
- Produces: one transition set, policy-conditional completion, deterministic metric formulas, and no Wallet-to-Reporting dependency.

- [ ] **Step 1: Add transition and reporting fixture tests**

استخدم مجموعة الانتقالات القانونية التالية كمرجع وحيد:

```python
EXPECTED_EXECUTION_TRANSITIONS = {
    ("received", "under_review"),
    ("received", "processing"),
    ("under_review", "processing"),
    ("processing", "under_review"),
    ("under_review", "action_required"),
    ("processing", "action_required"),
    ("action_required", "action_received"),
    ("action_received", "under_review"),
    ("action_received", "processing"),
    ("under_review", "completed"),
    ("processing", "completed"),
    ("action_received", "completed"),
    ("received", "cancelled"),
    ("under_review", "cancelled"),
    ("processing", "cancelled"),
    ("action_required", "cancelled"),
    ("action_received", "cancelled"),
}
```

اكتب parser لأسطر `source -> target` وقارن set في SOP وdomain وtest vector. إذا كان domain يعرض اختصارًا بصريًا، أضف فيه جدولًا قانونيًا آلي القراءة بدل استنتاج الانتقالات من الرسم.

```python
TRANSITION = re.compile(
    r"`?(received|under_review|processing|action_required|action_received|completed|cancelled)`?"
    r"\s*(?:->|──►)\s*"
    r"`?(received|under_review|processing|action_required|action_received|completed|cancelled)`?"
)


def extract_transitions(text: str) -> set[tuple[str, str]]:
    return set(TRANSITION.findall(text))


def require_transition_set(path: Path) -> None:
    actual = extract_transitions(read_text(path))
    require(
        actual == EXPECTED_EXECUTION_TRANSITIONS,
        f"{path}: transition mismatch missing={sorted(EXPECTED_EXECUTION_TRANSITIONS-actual)} extra={sorted(actual-EXPECTED_EXECUTION_TRANSITIONS)}",
    )
```

اختبر أيضًا fragments التقارير: `as_of` required، `approved / terminal`, zero `0.00%`, `order_count`, `order_gross_value_minor`. ارفض `approved count / rejected count`, `null if denominator zero`, وأي قول إن Reporting يقرأ Wallet ledger لقيمة الطلب.

- [ ] **Step 2: Prove current RED**

Run: `python3 -m scripts.docs_checks.run --group semantics`

Expected: FAIL لأن domain Fulfillment لا يسرد الانتقالين `received -> processing` و`processing -> under_review`، ولأن خطة Reporting تستخدم مقام rejected و`null`، ولأن Wallet تنسب القيمة المالية للتقارير إلى ledger.

- [ ] **Step 3: Align the fulfillment graph and conditional completion**

اجعل domain وSOP ومتجه الاختبار والخطة تستعمل المجموعة ذات 17 انتقالًا حرفيًا. كل انتقال غير موجود فيها يرجع `execution.invalid_transition`. تظل `completed` و`cancelled` نهائيتين.

غير `CompleteExecution` ليقبل `issued_document_id` اختياريًا. إذا كانت `captured_policy.requires_issued_document = true` يجب أن يكون المستند موجودًا و`clean` وتصنيفه `issued_document`; وإلا يسمح الإكمال بلا مستند. حدث journey 06 ليعرض مسارًا بكل حالة، ومتجه الاختبار ليحدد قيمة السياسة ولا يقبل نتيجتين.

- [ ] **Step 4: Finish the Forms/Documents responsibility split**

اجعل Form test vectors تختبر أن القيمة opaque string أوarray حسب schema وأن required/count shape صحيح. ضع اختبارات `clean`, owner, classification, attachment race في Documents/Purchasing plans فقط. حدث Cross-Domain sections كي تقول إن Forms تحمل references ولا تستعلم من Documents.

- [ ] **Step 5: Replace the Reporting plan formulas exactly**

استخدم التعريفات الآتية:

```text
M03-A order_count = count paid orders created in cohort
M03-B order_gross_value_minor = sum Orders.price_paid_minor for those orders
M04 top_up_completion_rate = terminal submitted top-ups / all submitted top-ups
M05 average_review_seconds = sum(decision_at - submitted_at) / terminal top-ups
M06 transfer_approval_ratio = approved terminal top-ups / all terminal top-ups
M08 average_fulfillment_seconds = sum(completed_at - received_at) / completed executions
```

كل query/filter يأخذ `as_of: CarbonImmutable` إلزاميًا. المقام الصفري يعيد `0` duration أو`0.00%`. تبقى M01–M12 الأخرى مطابقة لـ`specs/domains/reporting.md`. احذف اعتماد Reporting على Wallet وعبارة قراءة ledger من Wallet؛ مصادر قيمة الطلب هي snapshots في Orders.

- [ ] **Step 6: Verify and commit**

Run: `python3 -m scripts.docs_checks.run --group semantics && python3 -m scripts.docs_checks.run --group package`

Expected: PASS مع 17 transition متطابقًا، completion مشروطًا بالسياسة، وصيغ M01–M12 حتمية، ورسم الاعتماد بلا Wallet edge إلى Reporting.

```bash
git add scripts/docs_checks/semantic_contracts.py tests/documentation/test_semantic_contracts.py specs docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md docs/superpowers/plans/2026-09-11-rehla-03-catalog-forms-content.md docs/superpowers/plans/2026-09-11-rehla-05-orders-purchasing-fulfillment.md docs/superpowers/plans/2026-09-11-rehla-06-reporting-integrations.md
git commit -m "docs: make fulfillment and reporting deterministic"
```

### Task 8: Prove Full Corpus and Plan Coverage

**Files:**
- Create: `scripts/docs_checks/inventory.py`
- Create: `tests/documentation/test_inventory.py`
- Create: `docs/reviews/2026-09-12-rehla-documentation-manifest.csv`
- Create: `docs/reviews/2026-09-12-rehla-package-contract-alignment-audit.md`
- Modify: `docs/reviews/2026-09-11-rehla-specs-completeness-audit.md:1-12`
- Modify: `specs/README.md:1-100`
- Modify: `specs/GOVERNANCE.md:1-55`
- Modify: `specs/coverage-manifest.csv:1-66`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-plan-coverage.csv:1-66`
- Modify: `docs/superpowers/plans/2026-09-11-rehla-platform-build.md:1-180`
- Modify: `docs/plans/REHLA-LARAVEL-PACKAGE-IMPLEMENTATION.md:1-260`
- Modify: all seven `docs/superpowers/plans/2026-09-11-rehla-0*.md`
- Verify unchanged unless a product contradiction is found: `docs/REHLA-PROJECT-CONCEPT-AND-USER-JOURNEY.md`

**Interfaces:**
- Consumes: complete final `docs/` and `specs/` trees.
- Produces: exact file manifest, link validation, R01–R65 proof, plan-task proof, final gap matrix, and one `--group all` release command.

- [ ] **Step 1: Write inventory and link-check tests**

```python
from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase

from scripts.docs_checks.common import CheckFailure
from scripts.docs_checks.inventory import validate_requirement_ids, validate_relative_links


class InventoryTest(TestCase):
    def test_requirement_ids_must_be_exactly_r01_through_r65(self) -> None:
        rows = [{"requirement_id": f"R{number:02d}", "status": "covered"} for number in range(1, 66)]
        validate_requirement_ids(rows)
        with self.assertRaises(CheckFailure):
            validate_requirement_ids(rows[:-1])

    def test_broken_relative_link_fails(self) -> None:
        with TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / "source.md"
            source.write_text("[missing](missing.md)", encoding="utf-8")
            with self.assertRaisesRegex(CheckFailure, "missing.md"):
                validate_relative_links(root, [source])
```

- [ ] **Step 2: Implement dynamic corpus validation**

اجمع كل ملفات `.md`, `.csv`, `.json` تحت `docs/` و`specs/` مع استثناء `.git`. تحقق من:

- كل relative Markdown link يحل إلى ملف موجود.
- `coverage-manifest.csv` له 65 صفًا، ids تساوي `R01..R65` مرة واحدة، status كلها `covered`، وكل primary/support path موجود تحت `specs/`.
- plan coverage له 65 صفًا بنفس IDs وكل primary plan موجود وكل task label يظهر في الملف المشار إليه.
- documentation manifest يساوي set الملفات المكتشفة مع استثناء manifest نفسه، ولا يوجد صف duplicate.
- كل صف manifest له `path,kind,line_count,review_status,evidence_group`، و`review_status = reviewed`.

نفذ التحقق الأساسي هكذا:

```python
def validate_requirement_ids(rows: list[dict[str, str]]) -> None:
    expected = [f"R{number:02d}" for number in range(1, 66)]
    actual = [row["requirement_id"] for row in rows]
    require(actual == expected, f"requirement ids mismatch: {actual}")
    require(all(row["status"] == "covered" for row in rows), "non-covered requirement row")


def validate_relative_links(root: Path, paths: list[Path]) -> None:
    failures: list[str] = []
    for source in paths:
        for target in relative_markdown_links(source):
            resolved = (source.parent / target).resolve()
            try:
                resolved.relative_to(root.resolve())
            except ValueError:
                failures.append(f"{source}: link escapes repository: {target}")
                continue
            if not resolved.exists():
                failures.append(f"{source}: missing relative link: {target}")
    require(not failures, "\n".join(failures))
```

Run: `python3 -m unittest tests.documentation.test_inventory -v`

Expected: tests PASS.

Run: `python3 -m scripts.docs_checks.run --group inventory`

Expected: FAIL لأن manifest النهائي لم ينشأ ولأن authority/plan references لم تحدث بعد.

- [ ] **Step 3: Update governance and plan authority**

أضف إلى `specs/GOVERNANCE.md` و`specs/README.md` رابط التصميم المعتمد والخرائط الثلاث ومدقق الوثائق. ثبت ترتيب السلطة: مفهوم المنتج، specs، تصميم المحاذاة فيما يخص الحزم والعقود، معمارية Laravel، ثم الخطط. اذكر أن `specs/` لا يحتاج `foundation/` أو`architecture/` لأن مواضعها الحالية ذات مالك واضح.

احذف النص القديم في الخطة المختصرة الذي يقول إن المستودع غير صالح أوإن القرارات غير موثقة. اجعل الخطتين الرئيسيتين تربطان هذه الخطة والتصميم، وتذكران أن التطبيق غير منفذ بعد وأن حالة مهام Laravel `planned`.

- [ ] **Step 4: Reconcile all seven plans and both coverage registers**

راجع كل task header وFiles وInterfaces وخطوات RED/GREEN وcommit في الخطط السبع. ثبت أن كل مسار package مطابق، وكل dependency مستعمل موجود في map، وكل interface موجود في contract map، وكل API operation تظهر في خطة الواجهة، وكل formula/transition يطابق specs.

حدث `specs/coverage-manifest.csv` فقط عندما تغير supporting evidence أووصف المصدر؛ لا تغير IDs أوstatus. في `2026-09-11-rehla-plan-coverage.csv` اجعل قيمة `task` في كل واحد من الصفوف الخمسة والستين substring حرفيًا لعنوان Task موجود في `primary_plan`. حدث أدلة R50 وR52 وR62 وR64 إلى اختبارات العقود والحراس المحدثة.

- [ ] **Step 5: Generate the full documentation manifest**

اكتب صفًا لكل ملف مكتشف عدا manifest نفسه، مرتبة lexicographically:

```csv
path,kind,line_count,review_status,evidence_group
```

يولد كل صف فعليًا بهذه العملية، فلا تحتوي الخانة العددية قيمة يدوية:

```python
writer.writerow({
    "path": path.relative_to(ROOT).as_posix(),
    "kind": classify(path),
    "line_count": len(path.read_text(encoding="utf-8").splitlines()),
    "review_status": "reviewed",
    "evidence_group": evidence_group(path),
})
```

عرف التصنيف ومجموعة الدليل بقواعد ثابتة:

```python
def classify(path: Path) -> str:
    relative = path.relative_to(ROOT).as_posix()
    if relative == "docs/REHLA-PROJECT-CONCEPT-AND-USER-JOURNEY.md" or relative == "specs/product-overview.md":
        return "product"
    if relative == "docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md":
        return "architecture"
    if relative.startswith("docs/architecture/"):
        return "machine-map"
    if relative.startswith("docs/superpowers/specs/"):
        return "design"
    if "/plans/" in relative:
        return "plan"
    if relative.startswith("docs/reviews/"):
        return "review"
    if relative in {"specs/README.md", "specs/GOVERNANCE.md"}:
        return "governance"
    if relative == "specs/coverage-manifest.csv":
        return "coverage"
    for directory, kind in {
        "contracts": "contract",
        "domains": "domain",
        "journeys": "journey",
        "cross-cutting": "cross-cutting",
        "test-vectors": "test-vector",
    }.items():
        if relative.startswith(f"specs/{directory}/"):
            return kind
    raise CheckFailure(f"unclassified documentation file: {relative}")


def evidence_group(path: Path) -> str:
    kind = classify(path)
    if kind in {"architecture", "machine-map", "design"}:
        return "package-and-contract-alignment"
    if kind == "plan":
        return "implementation-plans"
    if kind == "review":
        return "audit-evidence"
    return "product-and-behavior-contracts"
```

يحسب `line_count` من نص UTF-8 باستخدام `splitlines()`. يولد script الصفوف من الشجرة الحالية ثم يعيد قراءتها ويثبت set equality.

- [ ] **Step 6: Write the final audit report**

يحتوي التقرير النهائي:

1. commit range المفحوص.
2. العدد الديناميكي للملفات والأسطر حسب المجموعة.
3. جدول كل فجوة في التصميم، حالتها، الملفات المصححة، وأمر الإثبات.
4. نتائج: 19 package، 98 edge، 98 contract records، صفر cycles، 28 API operations، 65/65 requirements، 65/65 plan rows، صفر broken links، صفر package runtime paths خارج `src/`.
5. تصريح واضح أن النتيجة تثبت اتساق المواصفات والخطط ولا تثبت تنفيذ تطبيق Laravel.

أضف في أعلى تقرير 2026-09-11 ملاحظة `Superseded for package/contract alignment by ...` مع رابط نسبي إلى التقرير الجديد؛ لا تمح سجل التدقيق السابق.

- [ ] **Step 7: Run narrow and broad fresh verification**

Run: `python3 -m unittest discover -s tests/documentation -v`

Expected: all validator self-tests PASS.

Run: `python3 -m scripts.docs_checks.run --group package`

Expected: PASS: layout, 19 packages, 98 edges, 98 contract records, zero cycles, table ownership.

Run: `python3 -m scripts.docs_checks.run --group api`

Expected: PASS: 28 operations and canonical parameters/errors/tracing.

Run: `python3 -m scripts.docs_checks.run --group semantics`

Expected: PASS: registration, Forms/Documents, transactions, outbox, cleanup, transitions, reporting.

Run: `python3 -m scripts.docs_checks.run --group inventory`

Expected: PASS: complete manifest, 65 requirement rows, 65 plan rows, zero broken links.

Run: `python3 -m scripts.docs_checks.run --group all && git diff --check`

Expected: all four groups PASS from a fresh process and no whitespace errors.

- [ ] **Step 8: Inspect scope and commit**

Run: `git status --short && git diff --stat && git diff -- docs specs scripts tests/documentation`

Expected: only files named by this plan are changed; the diff contains documentation validators and contract alignment, with no Laravel application scaffold.

```bash
git add docs specs scripts/docs_checks tests/documentation
git commit -m "docs: prove complete Rehla contract alignment"
```

### Task 9: Final Independent Plan-to-Spec Review

**Files:**
- Modify if findings require it: files changed in Tasks 1-8
- Modify: `docs/reviews/2026-09-12-rehla-package-contract-alignment-audit.md`

**Interfaces:**
- Consumes: design sections 1-15 and final repository state.
- Produces: one evidence row per design section and a clean final verification result.

- [ ] **Step 1: Build the design coverage matrix**

أضف إلى تقرير التدقيق جدولًا بالأعمدة:

```text
design_section | implementing_task | changed_files | validation_group | result
```

أنشئ صفوفًا للأقسام 1 إلى 15. ترتبط الأقسام التعريفية 1 و2 و14 و15 بـTask 8، والبنية 3 بـTask 2، والاعتماد 4 والخرائط 10 بـTask 3، والعقود 5 بـTask 4، وAPI 6 والأخطاء 7 بـTask 5، والمعاملات 8 بـTask 6، وتسويات المجالات 9 بـTask 7، ونطاق الملفات 11 والاستراتيجية 12 والبوابات 13 بـTask 8.

- [ ] **Step 2: Re-read the complete changed-file set**

Run: `git diff --name-only f45def9..HEAD | sort`

Expected: كل ملف يظهر في documentation manifest وله سبب في gap matrix؛ لا يوجد ملف غير مراجع.

- [ ] **Step 3: Run the final gate from a clean process**

Run: `python3 -m unittest discover -s tests/documentation -v && python3 -m scripts.docs_checks.run --group all && git diff --check && git status --short`

Expected: all tests/checks PASS. يسمح `git status --short` فقط بتعديل تقرير التدقيق الناتج من إضافة evidence matrix في هذه المهمة.

- [ ] **Step 4: Commit the review evidence**

```bash
git add docs/reviews/2026-09-12-rehla-package-contract-alignment-audit.md
git commit -m "docs: record package alignment review evidence"
```

- [ ] **Step 5: Confirm final repository state**

Run: `git status --short && git log --oneline --decorate -10`

Expected: working tree clean، وتسعة commits تنفيذية للمهام إضافة إلى commit وثيقة الخطة فوق `f45def9`، ولا يوجد push إلى remote.
