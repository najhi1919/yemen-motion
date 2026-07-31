# YM-DESIGNER-PROFILE-PUBLICATION-LIFECYCLE-005A — معاينة الملف، شروط الجاهزية، النشر والإخفاء

| الحقل | القيمة |
|---|---|
| Station ID | `YM-DESIGNER-PROFILE-PUBLICATION-LIFECYCLE-005A` |
| الحالة | `closed` |
| المرحلة التابعة | Phase 6 — Designer Profiles |
| الاعتماديات | إغلاق `004A` والهوية الأساسية ووسائطها |
| نقطة الأساس | `c0f30279a71df7d16b15a217be9f571c5ea4aeac` |
| تاريخ الفتح | `2026-08-01` |
| تاريخ الإغلاق | `2026-08-01` |

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
- لم تُعد اختبارات Backend بعد التصحيحات اللاحقة لأن ملفات Backend لم تتغير بعدها.
- شُغّل Pint على ملفات PHP المعدلة في النطاق ونجح بعد تنسيق `routes/api.php`.
- نجح Final Nuxt Production Build بعد آخر التصحيحات البصرية.

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

## أدلة الإغلاق

- طُبقت Migration الخاصة بـ`hidden_at` محليًا بنجاح لأغراض التحقق اليدوي.
- اختُبرت عمليات النشر والإخفاء وإعادة النشر يدويًا ونجحت.
- نجحت مزامنة الحالة بين بطاقة بيانات الملف وقسم نشر الملف.
- صُححت استعادة Focus بعد Dialog واعتمدت بصريًا.
- اعتُمدت واجهات Desktop وMobile وRTL بصريًا.
- طبقت Owner Preview إعدادات الخصوصية ولم تعرض الأقسام المخفية.
- نجح Final Nuxt Production Build بعد آخر التصحيحات البصرية.

## Commit

- Closure Commit: `d7a954d2ee0a3c964de3395b50a398b33bb5954a`.
- الرسالة: `feat(designer): add profile publication lifecycle`.
- الإحصاءات: `24 files changed`, و`2383 insertions`، و`81 deletions`.

## Push

نجح Push إلى `origin/main`، وكان remote مطابقًا للـClosure Commit.

## CI

لم يُظهر الاستعلام الفوري الوحيد تشغيلًا، ولم يُنفذ Polling. لا توجد نتيجة نجاح أوفشل مرصودة.

## المشاكل المكتشفة والتصحيحات

- لم تكن Migration مطبقة محليًا في أول محاولة نشر، فغاب العمود `hidden_at`. طُبقت Migration محليًا بنجاح لأغراض التحقق اليدوي.
- بقيت بطاقة بيانات الملف على حالة قديمة بعد النشر؛ رُبط نموذج عرض البطاقة بالحالة الفعلية المعادة من إجراء النشر.
- انتقل Focus إلى رابط التخطي بعد إغلاق Dialog؛ صُحح مسار استعادة Focus واعتمد بصريًا.
- كانت تسميات مستويات المهارات والأدوات واللغات ضعيفة الوضوح؛ حُولت إلى شارات محايدة واضحة ومترابطة مع أسماء العناصر.

## الحدود المتبقية

أُغلقت المحطة تقنيًا وبصريًا ووُثق Closure Commit. الصفحة العامة النهائية والأعمال المميزة خارج نطاقها، وتبقى Phase 6 بحالة `in-progress`.

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
| `2026-08-01` | نجاح Final Nuxt Production Build بعد آخر التصحيحات، وإنشاء Closure Commit بالرسالة المعتمدة وPush مطابق إلى `origin/main`. | `d7a954d2ee0a3c964de3395b50a398b33bb5954a`; `24 files changed`, `2383 insertions`, `81 deletions` | `closed` |
| `2026-08-01` | الاستعلام الفوري الوحيد عن CI لم يُظهر تشغيلًا، ولم يُنفذ Polling. | لا توجد نتيجة CI مرصودة | `closed` |
