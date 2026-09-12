# تقرير تدقيق اكتمال خطط بناء رحلة ومهارات الوكلاء

**التاريخ:** 2026-09-12
**مرجع المنتج:** [مفهوم رحلة ورحلة المستخدم](../REHLA-PROJECT-CONCEPT-AND-USER-JOURNEY.md)
**عقد البرنامج:** [rehla-plan-contract.json](../architecture/rehla-plan-contract.json)
**Manifest الخطط:** [2026-09-12-rehla-plan-manifest.csv](../superpowers/plans/2026-09-12-rehla-plan-manifest.csv)
**فهرس المهارات:** [`.agents/README.md`](../../.agents/README.md)

## الحكم التنفيذي

أصبحت خطط رحلة برنامج بناء كاملًا من عشر مراحل مرتبة، لكل حزمة مالك إنشاء واحد ولكل جدول مهمة migration محددة ولكل R01–R65 مهمة أساسية ودليل نهائي في الخطة 10. أصبحت Web وREST API وAdmin والتشغيل خططًا مستقلة قابلة للمراجعة، وأصبح الملف الجامع القديم إحالة بلا مهام.

هذا الاكتمال يخص المواصفات والمعمارية والخطط والمدققات ومهارات الوكلاء. بدأ تنفيذ الخطة 01 وأصبح مضيف Laravel قائمًا، ولذلك حالتها `in_progress`؛ تبقى الخطط 02–10 بحالة `planned`. بدء Foundation لا يعني اكتمال المشروع، ولا يجوز تحويل أي خطة إلى `completed` قبل تنفيذ اختباراتها وتسجيل الأدلة التي تحددها.

## دليل شمول القراءة

| النطاق | الملفات | الأسطر | طريقة الإثبات |
|---|---:|---:|---|
| `docs/` و`specs/` عدا manifest الوثائق نفسه | 74 | 18068 | توليد ديناميكي ومقارنة set/line count |
| `.agents/README.md` والمهارات التسع | 10 | 380 | فهرس مساوي للمجلد + validator رسمي ومحلي |
| الإجمالي المراجع في هذا التدقيق | 84 | 18448 | لا توجد قراءة بالعينة |

يحصر [documentation manifest](2026-09-12-rehla-documentation-manifest.csv) كل ملف Markdown/CSV/JSON في `docs/` و`specs/` عدا نفسه. ويحصر manifest الخطط كل ملف Markdown داخل `docs/superpowers/plans`، بينما يثبت مدقق المهارات المجموعة الدقيقة تحت `.agents/skills`.

## تسلسل خطط البناء

| # | الخطة | المهام | الحزم المالكة | المتطلبات الأساسية | الحالة |
|---:|---|---:|---|---|---|
| 1 | Foundation & Core | 6 | Core | لا توجد؛ قرارات المنتج والخرائط جاهزة | in_progress |
| 2 | Identity & Platform Services | 5 | Identity, Audit, Documents, Travelers, Notifications | 01 | planned |
| 3 | Catalog, Forms & Content | 3 | Catalog, Forms, Content | 02 | planned |
| 4 | Wallet & Top-Ups | 3 | Wallet, TopUps | 03 | planned |
| 5 | Orders, Purchasing & Fulfillment | 5 | Orders, Purchasing, Fulfillment | 04 | planned |
| 6 | Reporting & Integrations | 3 | Reporting, Integrations | 05 | planned |
| 7 | Customer Web | 8 | Web | 06 | planned |
| 8 | Customer REST API | 8 | Api | 07 | planned |
| 9 | Admin Control Panel | 6 | Admin | 08 | planned |
| 10 | Operations, Security & Release | 4 | لا حزمة أعمال | 09 | planned |

يمنع العقد الاعتماد على خطة لاحقة. بوابات التسجيل الذري والشراء والتسليم الخارجي والإصدار تسجل `opened_by` و`closed_by` ودليل الإغلاق، ولا يسمح لخطة لاحقة بتغيير تعريف اكتمال خطة سابقة بصمت.

## ملكية الحزم وعقد الترجمة

كل صف أدناه يفرض `composer.json` و`README.md` وprovider تحت `src/Providers` و`src/resources/lang/en/messages.php` و`ar/messages.php` واختبار parity داخل مهمة المالك. يبدأ ملفا اللغة متطابقين ولو فارغين، ويحمل provider namespace `rehla-<package>`.

| Package | Owner plan/task | Provider + EN/AR + parity | Owned data |
|---|---|---|---:|
| Core | 01 / Task 5 | enforced | 0 |
| Identity | 02 / Task 1 | enforced | 7 |
| Audit | 02 / Task 2 | enforced | 1 |
| Documents | 02 / Task 3 | enforced | 2 |
| Travelers | 02 / Task 4 | enforced | 1 |
| Notifications | 02 / Task 5 | enforced | 3 |
| Catalog | 03 / Task 1 | enforced | 6 |
| Forms | 03 / Task 2 | enforced | 2 |
| Content | 03 / Task 3 | enforced | 1 |
| Wallet | 04 / Task 1 | enforced | 3 |
| TopUps | 04 / Task 2 | enforced | 3 |
| Orders | 05 / Task 1 | enforced | 4 |
| Purchasing | 05 / Task 2 | enforced | 1 |
| Fulfillment | 05 / Tasks 3–4 | enforced | 7 |
| Reporting | 06 / Task 1 | enforced | 0 tables; owned views |
| Integrations | 06 / Task 3 | enforced | 0 |
| Web | 07 / Task 1 | enforced | 0 |
| Api | 08 / Task 1 | enforced | 0 |
| Admin | 09 / Task 1 | enforced | 0 |

النتيجة الآلية: 19 package، 98 dependency edges، 98 contract-edge records، صفر cycles، 40 جدولًا بمالك وكاتب واحد، وصفر runtime paths للحزم خارج `src/`.

## اكتمال Web وAPI وAdmin

| السطح | نطاق الخطة | الحراس الإلزامية | بوابة الإثبات |
|---|---|---|---|
| Web | public/content/inquiry، auth/account، travelers/wallet، top-ups/receipt replacement، checkout، tracking/actions، documents/notifications، browser | session/CSRF، owner 404، لا Models/DB، لا استدعاء داخلي لـ`/api/v1`، EN/AR/RTL | 8 مهام + customer E2E/accessibility/responsive |
| REST API | auth/me، catalog/form، travelers/wallet/banks، top-ups، uploads/documents، purchase/orders/actions، notifications، OpenAPI | customer-only Sanctum، Form Requests/Resources، Problem Details، rate/content/ownership، لا staff abilities أوDB | 28/28 method/path في spec والمعمارية والخطة وOpenAPI |
| Admin | shell/MFA، 14 resource areas، command-only guards، sensitive/export، staff E2E، evidence handoff | deny-by-default، recent TOTP، readonly DTOs، named owner Commands، لا Models/DB/relationships، field/export audit | موظف كامل ومحدود، EN/AR/RTL، concurrency وcapability matrix |

## البوابات المشتركة

| المجال | القواعد المثبتة في الخطط | المدقق |
|---|---|---|
| المعاملات | مالك صريح، اتصال واحد، لا commit داخلي، Audit + in-app + Outbox ذريًا | semantics + task completeness |
| التزامن والمال | PostgreSQL حقيقي، locks وترتيب ثابت، integer minor units، failure injection | postgres skill + plans |
| السجلات الثابتة | DB-level منع UPDATE/DELETE لـLedger/Audit/Orders/FormVersions/history | package/semantics |
| الملفات | private default، MIME/magic/decoder/malware، attachment/cleanup fencing، تنزيل قصير | semantics + security skill |
| Outbox | at-least-once، lock token/lease fencing، stale worker denied، audited replay | semantics |
| Fulfillment | مجموعة 17 انتقالًا، policy captured، issued document مشروط | semantics |
| Reporting | `as_of` إلزامي، صيغ M01–M12، صفر حتمي، قيمة Orders لاWallet | semantics |
| الترجمة والوصول | EN/AR parity في كل حزمة، لا literals، RTL/keyboard/axe | plans + localization skill |
| الإصدار | E2E → observability → artifact/restore → security/performance/R01–R65 | release plan validator |

## تغطية المتطلبات

| سجل | النتيجة |
|---|---:|
| `specs/coverage-manifest.csv` | 65/65 covered |
| `rehla-plan-contract.json` | 65/65 owner plan/task + final evidence plan |
| `2026-09-11-rehla-plan-coverage.csv` | 65/65 exact existing task headings |
| Final evidence owner | 65/65 routed to plan 10 |
| Generic filename-only task references | 0 |
| Broken relative Markdown links | 0 |

## المهارات المحلية

| Skill | قرار الوكيل الذي تضبطه | التفعيل |
|---|---|---|
| rehla-implementation-gate | يختار plan/task/acceptance ويمنع تجاوز السلطة أوالمتطلبات | كل تنفيذ أوخطة executable |
| rehla-laravel-package-development | بنية الحزمة والprovider والخرائط والعقود والملكية | package work |
| rehla-localization | ملفات EN/AR والnamespace والparity وRTL | كل package task أوpublic text |
| rehla-api-contracts | 28 routes وOpenAPI وProblem Details وSanctum | API work |
| rehla-filament-admin | ReadModels وCommands والقدرات وMFA والحقول | Admin work |
| rehla-postgres-integrity | migrations/transactions/locks/immutability/concurrency | data or money work |
| rehla-security-and-privacy | auth/ownership/files/secrets/headers/exports | sensitive surface |
| rehla-testing-and-verification | RED/GREEN/expanded/fresh evidence | كل code/executable plan change |
| rehla-release-operations | processes/artifact/migrations/backup/restore/release | operations/release |

جميع المهارات التسع اجتازت `quick_validate.py`. يثبت المدقق المحلي تساوي أسماء المجلدات والـfrontmatter، ووجود شروط التفعيل والسلطات والقواعد وworkflow والتحقق وشروط التوقف ودليل التسليم، و8 روابط تخصصية من بوابة التنفيذ.

## الفجوات المصححة

| الفجوة قبل التدقيق | التصحيح النهائي | الحالة |
|---|---|---|
| خطة واحدة ضخمة تخفي Admin والتشغيل | خطط 07–10 مستقلة؛ القديم redirect بلا Tasks | مغلقة |
| لا عقد آلي لتسلسل الخطط أوملكية package task | عقد 10 خطط و19 مالكًا وcross-plan gates | مغلقة |
| يمكن إنشاء package بلا لغة | provider + EN + AR + parity إلزامية ومفحوصة داخل المهمة | مغلقة |
| المهام لا تصرح بكل جوانب التنفيذ | عقد اكتمال 15 بندًا في كل مهمة | مغلقة |
| بعض الجداول في الخريطة بلا migration task صريحة | table schedule كامل لـ40 جدولًا، وأضيفت الجداول الأربعة الناقصة | مغلقة |
| مسارات Web/API واسعة وغير قابلة للمراجعة | 8 مهام Web و8 مهام API مع ملفات وعقود واختبارات | مغلقة |
| API plan لا يثبت المجموعة نفسها | مدقق set equality يثبت 28/28 في ثلاثة مصادر | مغلقة |
| Admin resource واحد عام | 6 مهام ومصفوفة 14 منطقة وحراس command-only/sensitive/E2E | مغلقة |
| مصفوفة Admin استخدمت abilities غير موجودة في السجل الرسمي | ربط المصفوفة وصفوف R47 آليًا بالسجل القانوني ذي 30 قدرة | مغلقة |
| release gate غير محدد بما يكفي | fresh directory، empty PostgreSQL، artifact hash، encrypted restore، scans/budgets | مغلقة |
| لا مهارات محلية صارمة | 9 مهارات وفهرس ومدقق رسمي ومحلي | مغلقة |
| ادعاء الاكتمال بلا manifest | manifests ديناميكية للوثائق والخطط وتقرير أدلة | مغلقة |

## خارج النطاق المؤجل

لا تنشئ الخطط حزمًا أوschema للسلال أوالمخزون أوالشحن المادي أوتعدد العملات أوتعدد الجنسيات أوعدة مسافرين في Order واحد أوorder drafts أوrefunds/compensation. الإلغاء تشغيلي بلا أثر مالي تلقائي حتى تعتمد سياسة منتج مستقلة. لا تضيف مهارات الوكلاء إذنًا للنشر أوpush أوتعديل نظام خارجي.

## أوامر الإثبات ونتائجها

```text
python3 -m unittest discover -s tests/documentation -v
  PASS: 38 tests
python3 -m scripts.docs_checks.run --group package
  PASS: 19 packages, 98 edges, 98 contracts, 0 cycles, 1 owner per table
python3 -m scripts.docs_checks.run --group api
  PASS: 28/28 operations in spec, architecture, and plan
python3 -m scripts.docs_checks.run --group semantics
  PASS: orchestration, retries, 17 transitions, forms, reporting
python3 -m scripts.docs_checks.run --group inventory
  PASS: 69 documentation files, 65 specs rows, 65 plan rows, 0 broken links
python3 -m scripts.docs_checks.run --group plans
  PASS: 10 plans, 19 package owners, 65 requirements, 40 tables, 0 duplicate tasks
python3 -m scripts.docs_checks.run --group skills
  PASS: 9 skills, 8 specialist links, 0 broken references, 0 scaffold markers
python3 -m scripts.docs_checks.run --group all
  PASS: all registered groups
git diff --check
  PASS
```

## تسلسل الالتزامات الرئيسي

بدأت المحاذاة من `dd91b22`، ثم ثُبتت الحزم والعقود وAPI والمعاملات والحتمية والتغطية. بدأ برنامج الحوكمة من `660a43f` و`dcca5af`، ثم:

- `6b4d2bd` مدقق جودة الخطط.
- `f1b344c` عقد البرنامج ذي عشر خطط.
- `7e92b56` عقد الحزم والترجمة.
- `e1160ea` فصل Web وAPI وAdmin وOperations.
- `a6b96b7` حتمية Fulfillment وReporting.
- `6874172` و`62d301a` إثبات corpus والمراجعة المستقلة.
- `b3cb0af` بوابات المهام والاعتماد والجداول.
- `818d792` خطتا Web وAPI الكاملتان.
- `4678258` خطة Admin الكاملة.
- `bb9eaa3` خطة التشغيل والأمان والإصدار.
- `2c9ff92` المهارات المحلية التسع.

## القيود المتبقية

- أُنشئ مضيف Laravel وبدأ سجل القبول، لكن حزم الأعمال وواجهات المنتج لم تُنفذ بعد؛ لا توجد لذلك نتائج Playwright أوأدلة أعمال PostgreSQL حتى الآن.
- نتائج PASS الشاملة في هذا التقرير تخص اتساق المواصفات والخطط والمدققات والمهارات. تسجل مهام الخطة 01 أدلة Pest وVite تدريجيًا، وتبقى الخطة `in_progress` حتى اكتمال بوابتها.
- لم يحدث push أوdeploy أونشر خارجي ضمن هذا العمل.
