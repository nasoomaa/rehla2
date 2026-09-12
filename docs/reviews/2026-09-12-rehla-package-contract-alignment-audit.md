# تقرير تدقيق محاذاة حزم وعقود وخطط رحلة

**التاريخ:** 2026-09-12  
**نطاق Git المفحوص:** `f45def9a97dda08b66142db2984acb52cc0b3f61..HEAD`  
**النطاق:** جميع ملفات Markdown وCSV وJSON تحت `docs/` و`specs/`، مع الخرائط والمدققات وخطط التنفيذ.

## الحكم

تتبع المواصفات والخطط مشروع رحلة كما تصفه وثيقة المفهوم R01–R65، وأصبحت حدود الحزم والعقود والجداول والمعاملات والمسارات قابلة للتحقق آليًا. هذا الحكم يثبت اكتمال واتساق **التصميم وخطة التنفيذ**؛ لا يثبت أن تطبيق Laravel أوmigrations أوواجهات الإنتاج قد نُفذت.

لا يحتاج `specs/` إلى مجلدي `foundation/` أو`architecture/`. يملك `product-overview.md` أساس المنتج، وتملك المجالات والعقود والرحلات السلوك، بينما تملك `docs/` معمارية Laravel والخرائط الآلية. تكرار هذه السلطات سيخلق مصدرين قابلين للانحراف.

## دليل الشمول

يولد `scripts/docs_checks/inventory.py` manifest من الشجرة نفسها، ويقارن المسارات والتصنيف وعدد الأسطر في كل تشغيل. لا يعتمد العدد على عينة أو قائمة يدوية.

| المجموعة | الملفات المراجعة | دليل المراجعة |
|---|---:|---|
| `specs/` | 41 | `coverage-manifest.csv` + فحص الروابط + semantics |
| `docs/` عدا manifest | 26 | manifest + package/api/plans validators |
| الإجمالي عدا manifest نفسه | 67 | `python3 -m scripts.docs_checks.run --group inventory` |

## الفجوات التي أغلقت

| الفجوة | التصحيح | ملفات الإثبات | أمر الإثبات | الحالة |
|---|---|---|---|---|
| موارد package خارج `src/` | نقل config/database/resources/routes/openapi تحت `src/` ووضع providers تحت `src/Providers` | المعمارية وخطط 01–10 | `--group package` | مغلقة |
| اعتماد وعقد وملكية غير قابلة للمقارنة | خرائط كاملة لـ19 حزمة و98 حافة و98 سجل عقد و40 جدولًا | `docs/architecture/*.json` | `--group package` | مغلقة |
| منافذ التسجيل وإنشاء التنفيذ متعارضة | منافذ synchronous داخل المعاملة وعكس اعتماد `ExecutionCreator` | specs والخطط 02 و04 و05 | `--group semantics` | مغلقة |
| API ومسارات الخطأ والتتبع غير متطابقة | عقد 28 عملية ومعاملات canonical وProblem Details مع trace/correlation | عقد REST وخطة 08 | `--group api` | مغلقة |
| retry وOutbox والتنظيف قابل للسباق | `purchase_attempts` بإصدارات ملتقطة، lease token fencing، وتنظيف blob ثلاثي المراحل | عقود الشراء والإشعارات والمستندات | `--group semantics` | مغلقة |
| آلات الحالات وصيغ التقارير غير حتمية | 17 انتقالًا موحدًا، إكمال مشروط بالسياسة، `as_of` ومقامات صفرية ومصادر Orders | fulfillment/reporting/test vectors والخطط | `--group semantics` | مغلقة |
| إنشاء الحزم قد يتجاوز الترجمة | provider وEN/AR وparity test إلزامية داخل كل مهمة مالكة | خطة Foundation والخطط 01–09 | `--group plans` | مغلقة |
| Web وAPI وAdmin والتشغيل مخفية في خطة واحدة | أربع خطط مستقلة وخطة قديمة إحالة فقط | الخطط 07–10 | `--group plans` | مغلقة |
| تغطية الوثائق والخطط تعتمد قراءة بشرية | manifest ديناميكي و65/65 صف مواصفات و65/65 صف خطة وفحص روابط | manifest وسجلا التغطية | `--group inventory` | مغلقة |

## النتائج القابلة للقياس

| العقد | النتيجة المطلوبة |
|---|---:|
| الحزم | 19 |
| حواف الاعتماد | 98 |
| سجلات عقود الحواف | 98 |
| الدورات | 0 |
| مالك كل جدول | 1 |
| عمليات REST v1 | 28/28 |
| انتقالات Execution | 17/17 |
| أقسام المتطلبات | 65/65 |
| صفوف خطة المتطلبات | 65/65 |
| الخطط التنفيذية | 10 |
| روابط Markdown المكسورة | 0 |
| مسارات runtime للحزم خارج `src/` | 0 |

## بوابة التحقق

```bash
python3 -m unittest discover -s tests/documentation -v
python3 -m scripts.docs_checks.run --group package
python3 -m scripts.docs_checks.run --group api
python3 -m scripts.docs_checks.run --group semantics
python3 -m scripts.docs_checks.run --group inventory
python3 -m scripts.docs_checks.run --group plans
python3 -m scripts.docs_checks.run --group all
git diff --check
```

تسجل النتيجة النهائية الفعلية بعد إعادة القراءة المستقلة في قسم «مصفوفة تغطية التصميم» الذي تضيفه بوابة المراجعة الأخيرة.
