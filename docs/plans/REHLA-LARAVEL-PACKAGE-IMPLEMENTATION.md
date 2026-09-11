# خطة تنفيذ معمارية Laravel بالحزم لرحلة

مرجع البنية: [REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md](../REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md). مرجع المنتج: [REHLA-PROJECT-CONCEPT-AND-USER-JOURNEY.md](../REHLA-PROJECT-CONCEPT-AND-USER-JOURNEY.md). خريطة الاعتمادات: [rehla-package-map.json](../architecture/rehla-package-map.json).

الخطة التنفيذية التفصيلية وفق Superpowers تبدأ من [Rehla Platform Implementation Plan](../superpowers/plans/2026-09-11-rehla-platform-build.md). يبقى هذا الملف خريطة مراحل مختصرة؛ الملفات السبعة المرتبطة من الخطة الرئيسية هي المرجع التنفيذي للملفات والعقود ودورات RED/GREEN وأوامر التحقق.

حالة الخطة: جاهزة للتنفيذ بعد إغلاق قرارات S1. لا يحتوي المسار الحالي تطبيق Laravel أو مستودع Git صالحًا، لذلك الخطة تعمل بنمط تعديل مباشر ولا تفترض فروعًا أو PRs.

## ثوابت كل خطوة

- PostgreSQL هو مرجع اختبارات المال والتزامن، ولا تستبدله SQLite.
- لا كتابة مالية من Web أوApi أوAdmin.
- لا تعديل أو حذف ledger أوAudit أوOrder snapshots أوFormVersion منشور.
- لا أثر خارجي داخل المعاملات.
- لا حزم Cart/Inventory/Shipping/MultiCurrency/Refunds في الإصدار الأول.
- كل خطوة تحدث سجل R01–R65؛ عبارة «مكتمل» تحتاج دليل اختبار فعلي.
- يجب أن تطابق اعتمادات Composer خريطة الحزم، ويجب أن يكون ترتيب التنفيذ topological order صالحًا لها.

## شبكة التنفيذ المصححة

```text
S1 Product decisions
  ↓
S2 Laravel host + Core + package tooling + architecture guards
  ↓
S3 Identity + Audit
  ├── S4A Documents
  ├── S4B Travelers
  └── S4C Notifications contracts + Outbox foundation
       ↓
S5A Catalog → S5B Forms            # Content هو S5C موازٍ بعد S3
S6A Wallet → S6B TopUps            # TopUps بعد Documents/Notifications أيضًا
S7A Orders → S7B Purchasing contracts
  ↓
S8 Fulfillment                    # بعد Orders/Forms/Documents/Notifications
  ↓
S9 Complete Purchasing SubmitOrder transaction
  ↓
S10 Reporting contracts + minimal read models
  ↓
S11A Notification workers + Integrations
  ↓
S11B Web + API + Admin
  ↓
S12 Operations, full acceptance and launch gate
```

S4A وS4B وS4C متوازية بعد S3. يمكن بدء S6A بالتوازي مع S5A بعد S3، لكن S6B ينتظر S4A وS4C. داخل المراحل المرقمة، الحروف وترتيب الأسهم إلزامية: S5A قبل S5B، وS6A قبل S6B، وS7A قبل S7B، وS11A قبل S11B. لا يبدأ `SubmitOrder` قبل وجود تنفيذ حقيقي لكل المشاركين. يتحقق CI من أن كل substep لا يخالف dependency graph المستخرج من `composer.json` وملف الخريطة.

## S1 — إغلاق قرارات المنتج والبيانات

**السياق:** وثيقة المفهوم تترك سياسة الاسترداد وSOP لكل خدمة وحدود الملفات وآلية الدخول وبعض تفاصيل المبلغ غير محسومة.

**العمل:** توثيق أصغر وحدة SDG، قواعد تطبيع الجواز، أنواع الملفات وأحجامها والاحتفاظ بها، انتقالات كل خدمة، آلية Web/API authentication، token expiry/revocation إن استعملت، قنوات الإشعار، ودلالات الإلغاء. تحديد RPO/RTO وtimezone للمؤشرات. يبقى refunds مؤجلًا إن لم تعتمد سياسته.

**التحقق:** لكل قرار أمثلة قبول ورفض ورقم R مرتبط. لا يوجد افتراض مخفي في schema أو route contract.

**التراجع:** تعديل وثائق القرار قبل وجود بيانات إنتاجية؛ لا migration.

## S2 — مضيف Laravel وCore وأدوات الحزم

**السياق:** لا يوجد تطبيق حالي. المطلوب Composer path repositories وحزم `packages/Rehla/<Package>` واختبارات حدود.

**العمل:** إنشاء Laravel بالإصدارات المثبتة، PostgreSQL testing، root composer، Core Money/IDs/Clock/Error codes، template موحد للحزمة، package discovery، static analysis، formatter، Boost، CI، واختبارات architecture للاتجاهات والـnamespace وملكية الجداول. إنشاء registry للجداول وترتيب migrations.

**التحقق:** تثبيت نظيف من lockfile؛ package smoke test؛ فشل حارس معماري متعمد ثم نجاحه؛ اكتشاف tests داخل الحزم؛ topological sort؛ fresh migration؛ guard يرفض قاعدة غير اختبارية.

**التراجع:** إزالة قالب الحزم وإعادة lockfile قبل إدخال بيانات.

## S3 — Identity وAudit

**السياق:** كل المسارات تحتاج هوية وفاعلًا وصلاحية وتدقيقًا، وCore موجود ولا يعتمد على حزم رحلة.

**العمل:** users/staff/roles/abilities، customer/admin sessions، MFA للموظف الحساس، actor context، Audit append-only، وواجهات التسجيل الذري.

**التحقق:** deny-by-default، موظف محدود، حسابان معزولان، Admin guard مستقل، وAudit لا يقبل UPDATE/DELETE حتى عبر SQL مباشر.

**التراجع:** migrations عكسية في بيئة التطوير فقط؛ لا حذف Audit بعد وجود بيانات حقيقية.

## S4A — Documents

**السياق:** Catalog وTopUps وFulfillment وPurchasing تحتاج عقد ملفات آمنًا قبل بنائها.

**العمل:** private/public disks؛ metadata؛ الحالات `pending_scan/quarantined/clean/rejected/attached`؛ upload sessions؛ sniff/decoder/malware scanning؛ authorized download؛ atomic orphan claim والتنظيف.

**التحقق:** pending/rejected لا يدخلان الشراء أو المراجعة؛ mismatch/polyglot؛ حسابان؛ موظف محدود؛ race بين cleanup وattach؛ headers وrange/redirect عند الحاجة.

**التراجع:** حذف blobs الصناعية فقط؛ لا حذف ملف attached.

## S4B — Travelers

**السياق:** الحساب يملك عدة مسافرين، والجواز فريد عالميًا، وبيانات الماضي تحفظ في Order.

**العمل:** Traveler ownership، حقول الإصدار الأول، التطبيع والتفرد، Query/DTO وsnapshot contract.

**التحقق:** سباق تسجيل الجواز، رسالة لا تكشف حسابًا آخر، ownership، وعدم إضافة nationality/passport country.

**التراجع:** تغييرات التطبيع بعد البيانات تحتاج forward migration، لا تعديلًا صامتًا.

## S4C — Notifications foundation

**السياق:** TopUps وPurchasing وFulfillment تحتاج Outbox ذريًا قبل workers والقنوات الخارجية.

**العمل:** Outbox schema وappend contract وpayload versions وdedupe key وclaim/lease fields وin-app notification model. لا تنفذ adapters الخارجية بعد.

**التحقق:** الكتابة تنضم إلى transaction المستدعي؛ rollback يزيل Outbox؛ unique dedupe؛ claim بعمليتين؛ recovery بعد lease.

**التراجع:** تعطيل consumer؛ لا حذف أحداث مرتبطة بعمليات أعمال ناجحة.

## S5A — Catalog

**السياق:** الخدمة وأسعارها ومتطلباتها ونموذجها المنشور أساس بداية الشراء، وDocuments/Audit متاحان.

**العمل:** Service lifecycle وprice history وrequirements/media والنشر والتعطيل والترتيب.

**التحقق:** تعطيل خدمة لا يحذف تاريخها، والسعر authoritative وله history، وmedia تستخدم Documents contract.

**التراجع:** تعطيل خدمة بدل حذف تاريخ مستخدم.

## S5B — Forms

**السياق:** Catalog موجود، وكل نموذج وإصداراته مرتبطان بخدمة يملكها Catalog.

**العمل:** form drafts وimmutable versions وschema للأنواع11 وخصائص الحقول والنشر.

**التحقق:** نشر إصدار ثم منع UPDATE/DELETE عبر SQL، إصدار أحدث لا يغير القديم، والتحقق من جميع الأنواع والخصائص.

**التراجع:** التصحيح بإصدار جديد، لا تعديل المنشور.

## S5C — Content

**السياق:** Identity وAudit موجودان، ويمكن تنفيذ المحتوى بالتوازي مع S5A وS5B دون اعتماد على Catalog.

**العمل:** صفحات EN/AR وحالة النشر وفاعل التعديل ووقته.

**التحقق:** locale fallback، RTL rendering، وصلاحية إدارة المحتوى وتدقيقها.

**التراجع:** نشر إصدار محتوى مصحح مع حفظ سجل التغيير المطلوب.

## S6A — Wallet

**السياق:** الرصيد والقيد المالي أهم invariant، ويعتمد Wallet على Identity وAudit الموجودين.

**العمل:** Wallet وinteger Money وappend-only ledger وcredit/debit والتسوية.

**التحقق:** خصمان متزامنان ورصيد لواحد، ledger SQL immutability، correction، reconciliation وfailure injection على PostgreSQL باتصالين.

**التراجع:** لا يمسح ledger؛ توقف الكتابات الجديدة وتصحح أي قيمة مالية بقيد جديد.

## S6B — TopUps

**السياق:** Wallet وDocuments وNotifications foundation موجودة قبل TopUps.

**العمل:** Bank accounts وminimum setting وsubmit/review/approve/reject وreceipt links وAudit/Outbox.

**التحقق:** duplicate bank/reference، repeated/concurrent approval، رفض دون رصيد، تعطيل بنك مع حفظ التاريخ، reviewer permission وclean receipt فقط.

**التراجع:** تعطيل البنك أو الاستقبال؛ لا مسح لقيد مالي، والتصحيح بقيد جديد.

## S7A — Orders

**السياق:** السجل التجاري الثابت مستقل عن عملية التنسيق وFulfillment.

**العمل:** immutable Order وsnapshots وحماية DB وCreatePaidOrder contract.

**التحقق:** Order ولقطاته ترفض UPDATE/DELETE عبر SQL، وخدمة واحدة ومسافر واحد ومرجع debit فريد.

**التراجع:** قبل البيانات يمكن عكس migrations؛ بعد Order حقيقي لا حذف أو تعديل.

## S7B — عقود Purchasing

**السياق:** Orders موجود، وبقية العقود التي سيجمعها SubmitOrder موجودة من الخطوات السابقة.

**العمل:** purchase attempt schema؛ `ExecutionCreator` وبقية contracts/DTOs دون تنفيذ `SubmitOrder` النهائي.

**التحقق:** uniqueness لمفتاح `(account,key)`، fingerprint canonicalization وcompile contract tests.

**التراجع:** قبل الاستخدام يمكن تغيير العقود مع تحديث مستهلكيها؛ بعد الاستخدام تصدر العقود بدل كسرها صمتًا.

## S8 — Fulfillment

**السياق:** Orders وForms وDocuments وNotifications موجودة. Fulfillment ينفذ `ExecutionCreator` الذي عرفه Purchasing، وينفصل عن الحالة التجارية.

**العمل:** execution state machine/history، form responses، document links، action requests/responses، internal notes، Audit/Outbox، وربط implementation في Service Provider.

**التحقق:** transitions حسب SOP، staff abilities، customer action ownership، clean documents فقط، history append-only، cancellation دون refund تلقائي، وعقد إنشاء التنفيذ داخل transaction مستدعية.

**التراجع:** feature flag يمنع transitions الجديدة؛ لا يمحى التاريخ.

## S9 — إكمال معاملة SubmitOrder

**السياق:** جميع المشاركين الفعليين موجودون الآن. `Purchasing` هو المالك الوحيد للمعاملة المشتركة.

**العمل:** يبدأ transaction، ثم INSERT/lock لسجل idempotency، ثم إعادة تحقق وقفل وترتيب ثابت، Debit، Order، Execution، Audit، Outbox، وحفظ النتيجة قبل commit. كل المشاركين يستعملون الاتصال نفسه ولا يعملون commit أو IO خارجي.

**التحقق:** price/form changed، ownership/files/balance، same-key same/different payload، concurrent submission، concurrent insufficient balance، failure بعد debit وبعد Order وقبل Execution، snapshots، وعائلة بعدة Orders مستقلة.

**التراجع:** feature flag يوقف submissions؛ لا يمسح Order ناجح أو ledger.

## S10 — Reporting contracts والنماذج الدنيا

**السياق:** Admin Overview يحتاج مؤشرات قبل بناء الواجهة، والقسم62 يحدد12 مؤشرًا.

**العمل:** تعريف event time/timezone وnumerator/denominator لكل مؤشر، Queries/views والنماذج الدنيا، مع فصل «عدد الطلبات» عن «قيمة الطلبات».

**التحقق:** fixture معروف يعطي القيم المتوقعة لكل مؤشر؛ لا يكتب Reporting إلى source tables؛ يطابق تعريفات الوثيقة.

**التراجع:** إسقاط read models القابلة لإعادة البناء دون مس جداول المصدر.

## S11A — Notification workers وIntegrations

**السياق:** Outbox وFulfillment مستقران، لذلك يمكن إضافة المستهلكين والقنوات دون دورة اعتماد.

**العمل:** Outbox worker وat-least-once delivery وdead-letter/manual replay وIntegrations adapters للقنوات المعتمدة وWhatsApp inquiry.

**التحقق:** notification worker death/recovery، duplicate delivery، adapter failure، secret/PII handling وWhatsApp بلا أثر شراء.

**التراجع:** تعطيل adapter مع بقاء Outbox للـreplay.

## S11B — Web وREST API وAdmin

**السياق:** جميع حزم الأعمال وReporting وIntegrations موجودة؛ الواجهات الثلاث تستدعي use cases وQueries نفسها.

**العمل:** صفحات الاكتشاف والحساب؛ Route Contract Matrix وOpenAPI؛ Filament read-only projections وcustom command actions؛ localization/RTL/error mapping.

**التحقق:** OpenAPI contract و401/403/404 لكل object، rate limits، token/session policy، حسابان، Admin capability/field matrix، منع write APIs في Admin، Filament tests، private download، Idempotency-Key، وbrowser journeys مع keyboard/RTL.

**التراجع:** تعطيل route group أوpanel؛ لا rollback لبيانات أعمال ناجحة.

## S12 — التشغيل وبوابة قبول الإصدار الأول

**السياق:** النجاح هو رحلة القسم63 كاملة، ويجب إثبات النشر والاستعادة لا الاختبارات الوحدوية فقط.

**العمل:** immutable artifact، expand/backfill/contract، readiness/health، graceful worker restart، scheduler singleton، tracing/alerts، RPO/RTO، restore متناسق لـDB/blobs، performance/accessibility budgets، مراجعة الأمان والتراخيص، ومصفوفة R01–R65.

**التحقق:** fresh migration والترقية من آخر إصدار، restore rehearsal، alerts صناعية، كامل رحلتي العميل والإدارة، وجميع شروط قبول العقد. لا صف «مكتمل» بلا رابط اختبار أو قرار تأجيل معلل.

**التراجع:** عدم الإطلاق أو feature flags أوإعادة artifact المتوافق؛ لا عكس migration مدمرة ولا حذف تاريخ مالي.

## سجل المتطلبات الذرية

جدول R01–R65 في عقد المعمارية يثبت **تعيين الأقسام**. قبل تنفيذ كل خطوة يفكك القسم إلى acceptance IDs فرعية عند احتوائه قائمة. يشمل ذلك صراحة:

- `R08.01..R08.11` لأنواع الحقول، ثم IDs لخصائص label/order/required/helper/options/validation.
- IDs مستقلة لكل حقل في BankAccount وTopUp وOrder وExecution.
- `R40.01..R40.14` لأقسام Admin، مع القدرة والحقول والأوامر.
- IDs مستقلة لقدرات R47.
- `R62.01..R62.12` للمؤشرات وتعريفاتها.

يحمل السجل: source line، package owner، command/query، Web/API/Admin exposure، DB invariant، test evidence، status، وdeferred reason. لا تتحول عبارة «65/65 أقسام mapped» إلى «كل المتطلبات منفذة» قبل اكتمال هذا السجل وربطه بالاختبارات.

## بروتوكول تعديل الخطة

عند ظهور متطلب جديد يسجل مصدره وacceptance IDs والحزم والجداول والعقود المتأثرة، ثم يحدد هل يغير dependency graph أو المعاملة الحرجة. يمكن تقسيم خطوة أو إدخال خطوة قبل تابعها. لا يعاد ترتيب خطوة بعد بدء تابعها دون مراجعة migrations والعقود. أي إلغاء يذكر البيانات التي أنشئت وكيف ستظل قابلة للقراءة.
