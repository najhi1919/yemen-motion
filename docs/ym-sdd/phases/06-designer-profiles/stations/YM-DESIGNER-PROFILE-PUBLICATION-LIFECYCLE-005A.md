# YM-DESIGNER-PROFILE-PUBLICATION-LIFECYCLE-005A — معاينة الملف، شروط الجاهزية، النشر والإخفاء

| الحقل | القيمة |
|---|---|
| Station ID | `YM-DESIGNER-PROFILE-PUBLICATION-LIFECYCLE-005A` |
| الحالة | `visually-approved` |
| المرحلة التابعة | Phase 6 — Designer Profiles |
| الاعتماديات | إغلاق `004A` والهوية الأساسية ووسائطها |
| نقطة الأساس | `c0f30279a71df7d16b15a217be9f571c5ea4aeac` |
| تاريخ الفتح | `2026-08-01` |
| تاريخ الإغلاق | — |

## الهدف

بناء Publish Readiness ومعاينة الزائر ودورة نشر الملف وإخفائه أوإلغاء نشره، مع فصل account status عنpublication status وتطبيق شروط الاكتمال وAudit ورسائل ما قبل النشر.

## In Scope

- Publish Readiness وقائمة الموانع.
- معاينة الملف كما يراه الزائر.
- نشر وإخفاء أوإلغاء نشر الملف.
- فصل حالة الحساب عنحالة النشر.
- شروط الاكتمال وAudit ورسائل ما قبل النشر.

## Out of Scope

- تنفيذ الصفحة العامة النهائية.
- اختيار الأعمال المميزة.
- Account Settings وطلبات الخدمة.

## القرارات المعتمدة

`DP-DEC-005` و`DP-DEC-006` و`DP-DEC-012`. اعتمد عقد Backend foundation في الخطوة `005A-1`، ثم بُنيت فوقه تجربة Workspace في الخطوة `005A-2`. تبقى الصفحة العامة النهائية خارج المحطة.

## الملفات المنفذة

- `database/migrations/2026_08_01_000100_add_publication_lifecycle_to_designer_profiles_table.php`
- `app/Models/DesignerProfile.php`
- `app/Services/Designer/DesignerProfilePublicationService.php`
- `app/Http/Controllers/Api/DesignerProfilePublicationController.php`
- `app/Http/Requests/Designer/DesignerProfilePublicationShowRequest.php`
- `app/Http/Requests/Designer/DesignerProfilePublicationActionRequest.php`
- `app/Http/Resources/DesignerProfilePublicationResource.php`
- `app/Http/Resources/DesignerProfilePreviewResource.php`
- `routes/api.php`
- `tests/Feature/Designer/DesignerProfilePublicationLifecycleTest.php`
- `frontend/types/designer-profile.ts`
- `frontend/types/designer-profile-publication.ts`
- `frontend/composables/useDesignerProfilePublication.ts`
- `frontend/components/designer/profile/DesignerProfilePublicationPanel.vue`
- `frontend/components/designer/profile/DesignerProfilePreviewDrawer.vue`
- `frontend/components/designer/profile/DesignerProfilePublicationConfirmDialog.vue`
- `frontend/pages/designer/index.vue`

## العقود والواجهات

- الحالات المخزنة: `draft`, `published`, `hidden`. الجاهزية محسوبة وليست حالة مخزنة.
- readiness موحدة من `11` شرطًا وتعيد blockers آمنة.
- `GET /api/designer/profile/publication` للحالة والجاهزية والإجراءات و`expected_updated_at`.
- `GET /api/designer/profile/publication/preview` لمعاينة Owner-only تطبق خصوصية الأقسام.
- `PATCH /api/designer/profile/publication/publish` و`PATCH /api/designer/profile/publication/hide` بعقد `expected_updated_at` فقط.
- transitions ذرية مع row locking وoptimistic concurrency وno-op بلا touch أوAudit.
- Audit للنشر والإخفاء بقائمة metadata مغلقة.
- لا Public Route في `005A-1`.
- تعرض واجهة Workspace حالات `draft` و`published` و`hidden`، وتربط blockers بمحررات البيانات الأساسية والصورة الشخصية والبيانات المهنية الموجودة.
- توفر Owner Preview Drawer للقراءة فقط، مع تطبيق المحتوى الذي أعاده عقد الخصوصية دون إنشاء صفحة عامة.
- تستخدم إجراءات publish وhide حوار تأكيد، وتعرض حالات Loading وError وSuccess وتعيد تحميل الحالة مرة واحدة عند version conflict.

## اختبارات التحقق

- `DesignerProfilePublicationLifecycleTest`: نجح `18 tests` و`215 assertions`.
- الحزمة المرتبطة Publication + Professional + Bootstrap + Media: نجحت `82 tests` و`579 assertions`.
- شُغّل Pint على ملفات PHP المعدلة في النطاق ونجح بعد تنسيق `routes/api.php`.
- نجح Production Build سابق أثناء تنفيذ الواجهة، بينما Final production build بعد آخر التصحيحات البصرية ما يزال `pending`.

## المراجعة البصرية

اعتمد المستخدم بصريًا واجهة Workspace بتاريخ `2026-08-01`. شمل الاعتماد:

- المسودة غير الجاهزة والمسودة الجاهزة.
- معاينة الملف كزائر، والنشر، والإخفاء، وإعادة النشر.
- تزامن الحالة بين بطاقة بيانات الملف وقسم نشر الملف.
- Desktop وMobile وRTL.
- إغلاق Drawer وDialog واستعادة Focus بعد العمليات.
- عدم ظهور رابط التخطي بصورة غير مقصودة وعدم وجود تمرير أفقي ظاهر.
- تطبيق إعدادات الخصوصية في المعاينة.
- وضوح مستويات المهارات والبرامج واللغات.

لا يعني هذا اعتمادًا شاملًا لـLTR أوWCAG أوإغلاق المرحلة كاملة.

## Commit

—

## Push

—

## CI

—

## المشاكل المكتشفة والتصحيحات

- لم تكن Migration مطبقة محليًا في أول محاولة نشر، فغاب العمود `hidden_at`. طُبقت Migration محليًا بنجاح لأغراض التحقق اليدوي.
- بقيت بطاقة بيانات الملف على حالة قديمة بعد النشر؛ رُبط نموذج عرض البطاقة بالحالة الفعلية المعادة من إجراء النشر.
- انتقل Focus إلى رابط التخطي بعد إغلاق Dialog؛ صُحح مسار استعادة Focus واعتمد بصريًا.
- كانت تسميات مستويات المهارات والأدوات واللغات ضعيفة الوضوح؛ حُولت إلى شارات محايدة واضحة ومترابطة مع أسماء العناصر.

## الحدود المتبقية

Final production build بعد آخر التصحيحات البصرية وGit closure ما يزالان `pending`. الصفحة العامة النهائية خارج النطاق، ولا Closure Commit للمحطة بعد.

## المحطة التالية

`YM-DESIGNER-PUBLIC-PROFILE-006A` بعد إغلاق هذه المحطة.

## سجل زمني Append-only

| التاريخ | الحدث | الدليل | الحالة |
|---|---|---|---|
| `2026-08-01` | تسجيل المحطة في خطة Phase 6 دون عقد نهائي. | `PHASE-PLAN.md` | `planned` |
| `2026-08-01` | فتح المحطة واعتماد `005A-1` لبناء Publication Readiness and Lifecycle Backend Foundation. | Baseline `c0f30279a71df7d16b15a217be9f571c5ea4aeac` | `in-progress` |
| `2026-08-01` | تنفيذ Backend foundation والاختبار المتخصص دون Frontend أوPublic Route. | ملفات النطاق وعقد `005A-1` | `in-progress` |
| `2026-08-01` | نجاح الاختبار المتخصص وحزمة الاختبارات المرتبطة مع بقاء المحطة مفتوحة للخطوات التالية. | `18 tests / 215 assertions`; related `82 tests / 579 assertions` | `in-progress` |
| `2026-08-01` | تنفيذ `005A-2` لواجهة نشر الملف داخل Workspace: Publication Panel وإجراءات blockers ومعاينة المالك وحوارات النشر والإخفاء وحالات Loading/Error/Success. | ملفات Frontend المحددة وعقد Backend المثبت | `in-progress` |
| `2026-08-01` | تطبيق Migration محليًا بنجاح لأغراض التحقق اليدوي بعد ظهور غياب `hidden_at` في المحاولة الأولى. | التحقق اليدوي المحلي | `in-progress` |
| `2026-08-01` | إصلاح مزامنة حالة بطاقة بيانات الملف واستعادة Focus ووضوح مستويات القوائم. | التصحيحات البصرية والتفاعلية | `in-progress` |
| `2026-08-01` | اعتماد المستخدم بصريًا حالات المسودة والمعاينة والنشر والإخفاء وإعادة النشر على Desktop وMobile وRTL، مع بقاء Final build وGit closure معلقين. | المراجعة البصرية النهائية | `visually-approved` |
