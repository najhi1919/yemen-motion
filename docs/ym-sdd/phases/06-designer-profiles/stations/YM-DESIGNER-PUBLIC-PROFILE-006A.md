# YM-DESIGNER-PUBLIC-PROFILE-006A — الصفحة العامة للمصمم

| الحقل | القيمة |
|---|---|
| Station ID | `YM-DESIGNER-PUBLIC-PROFILE-006A` |
| الحالة | `closed` |
| المرحلة التابعة | Phase 6 — Designer Profiles |
| الاعتماديات | إغلاق `YM-DESIGNER-PROFILE-PUBLICATION-LIFECYCLE-005A` |
| نقطة الأساس | `890d7a468ba875856050e84b9da5ed24697a0bcc` |
| تاريخ الفتح | `2026-08-01` |
| تاريخ الإغلاق | `2026-08-03` |
| Closure Commit | `3e1553c136d6d396055296a4b00aeb0ef771643d` |
| المسار المعتمد | `/designers/{username}` |

## الهدف

بناء الصفحة العامة للمصمم على مرحلتين: عقد Backend عام وآمن، ثم واجهة Nuxt تعرض الهوية والبيانات المهنية وجميع الأعمال العامة مع تطبيق الخصوصية.

## In Scope

- API عام `GET /api/designers/{username}`.
- وسائط عامة محكومة للصورة والغلاف وغلاف العمل.
- حالات `404`, hidden, incomplete.
- تطبيق visibility flags في الاستجابة والعرض.
- جميع الأعمال المنشورة والعامة دون featured ordering.
- تستخدم واجهة Nuxt عقد SEO عبر `useSeoMeta` و`useHead` للعنوان والوصف وOpen Graph وTwitter وcanonical.

## Out of Scope

- دورة النشر نفسها؛ تنفذ في `005A`.
- منطق اختيار وترتيب الأعمال؛ ينفذ في `007A`.
- زر طلب خدمة قبل اكتمال المحطة المستقلة الخاصة به.
- معرض العمل الكامل وصفحة تفاصيل العمل.

## القرارات المعتمدة

`DP-DEC-003` و`DP-DEC-004` و`DP-DEC-006` و`DP-DEC-011`. اعتمدت `006A-1` المسار العام النهائي `/designers/{username}` وعقد API `GET /api/designers/{username}` مع إعادة استخدام readiness من `005A`.

## الملفات المنفذة

- `routes/api.php`
- `app/Models/Work.php`
- `app/Services/Designer/PublicDesignerProfileService.php`
- `app/Http/Controllers/Api/PublicDesignerProfileController.php`
- `app/Http/Controllers/Api/PublicDesignerProfileMediaController.php`
- `app/Http/Controllers/Api/PublicDesignerWorkMediaController.php`
- `app/Http/Resources/PublicDesignerProfileResource.php`
- `app/Http/Resources/PublicDesignerWorkResource.php`
- `tests/Feature/Designer/PublicDesignerProfileTest.php`
- `frontend/types/public-designer-profile.ts`
- `frontend/composables/usePublicDesignerProfile.ts`
- `frontend/components/public/designer/PublicDesignerHero.vue`
- `frontend/components/public/designer/PublicDesignerProfessionalSections.vue`
- `frontend/components/public/designer/PublicDesignerWorksGrid.vue`
- `frontend/components/public/designer/PublicDesignerWorkCard.vue`
- `frontend/pages/designers/[username].vue`
- `frontend/layouts/public.vue`
- `frontend/nuxt.config.ts`
- `frontend/tsconfig.json`
- `frontend/package.json`
- `frontend/package-lock.json`

## العقود والواجهات

- يظهر الملف فقط لحساب active بدور designer وملف published وreadiness مكتملة؛ كل حالات المنع تعيد `404` عامة موحدة.
- يطبق `PublicDesignerProfileResource` خصوصية availability وspecialties وskills وtools وlanguages وexperience، ولا يعيد occasion.
- يعيد `PublicDesignerWorkResource` حقول شبكة عامة محدودة دون IDs أوحالات إدارية، ويستخدم summary دون description.
- يعيد scope `publiclyVisible` الأعمال التي تجمع status published وvisibility public، مرتبة بـpublished_at ثم id تنازليًا.
- تبقى الوسائط في `works_private` ولا تخدم إلا عبر Controllers تتحقق من أهلية الملف والعمل والوسيط والغلاف المحدد وحالة ready.
- يعيد SEO contract: title وdescription وcanonical_path وimage_url وtype، دون HTML أوMeta Tags.
- تستخدم صفحة `/designers/{username}` جلب SSR عبر `useAsyncData` دون Authorization يدوي أوطلب مكرر عند hydration.
- تعرض الصفحة Hero الهوية والأقسام المهنية التي تحمل `visible=true` فقط، ثم جميع الأعمال العامة في شبكة مستقلة.
- تطبق الواجهة SEO وOpen Graph من عقد Backend، وتوفر حالات Loading وError و404 وEmpty دون صفحة تفاصيل أوطلب خدمة.

## اختبارات التحقق

نجح `PublicDesignerProfileTest` بعد التصحيحات المركزة: `12 passed` و`93 assertions`. يغطي القراءة كضيف، وغياب Sanctum، وحالات `404` الموحدة، والخصوصية، ومنع التسريب، وترشيح الأعمال وترتيبها، وحماية الوسائط، وعقد SEO.

## المراجعة البصرية

`approved` بتاريخ `2026-08-03`. اعتمدت الصفحة على Desktop وTablet وMobile وRTL وLTR وKeyboard و`200%` Zoom، كما اعتمدت الحالة الفارغة وحالات الخصوصية وإخفاء الملف والوسائط وإعادة النشر.

## Commit

`3e1553c136d6d396055296a4b00aeb0ef771643d` — `feat(designer): add public profile experience`.

## Push

—

## CI

—

## المشاكل المكتشفة والتصحيحات

- كانت Fixture الملف العام تترك `publication_status` بقيمة draft بسبب mass assignment؛ صُححت باستخدام `forceFill` داخل الاختبار.
- صُحح نطاق منع description ليخص عناصر الأعمال دون منع `seo.description`.
- أزيل اعتماد اختبارات الوسائط على ترتيب Cache-Control directives.

## الحدود المتبقية

أُغلقت المحطة بالـCommit `3e1553c136d6d396055296a4b00aeb0ef771643d` بعد نجاح التحقق التقني والبصري وProduction Build. يبقى Typecheck العام بـ`232` خطأ مسجلًا كدين تقني خارج نطاق المحطة، وتبقى Featured Works ضمن `007A`.

## المحطة التالية

`YM-DESIGNER-PROFILE-FEATURED-WORKS-007A` وفق العقود المعتمدة.

## سجل زمني Append-only

| التاريخ | الحدث | الدليل | الحالة |
|---|---|---|---|
| `2026-08-01` | تسجيل المسار والهدف المبدئي مع اعتماد صريح على `005A`. | `PHASE-PLAN.md`, `DP-DEC-003` | `planned` |
| `2026-08-01` | فتح المحطة من baseline المحدد وبدء `006A-1 — Public Profile Read Contract`. | `890d7a468ba875856050e84b9da5ed24697a0bcc` | `in-progress` |
| `2026-08-01` | تنفيذ Backend public read contract وكتابة اختبارات Feature دون تشغيل Tests أوBuild، مع بقاء Frontend والـvisual review pending. | ملفات نطاق `006A-1` | `in-progress` |
| `2026-08-01` | نجاح اختبار عقد القراءة العام بعد إصلاح Fixture ونطاق description وترتيب Cache-Control. | `12 passed`, `93 assertions` | `in-progress` |
| `2026-08-01` | تنفيذ `006A-2 — Public Profile Frontend` عبر SSR مع SEO وشبكة الأعمال والحالات العامة، دون Featured Works أوصفحة تفاصيل. | Frontend `implemented-pending-visual-review`; Final Build pending | `in-progress` |
| `2026-08-03` | اعتماد Desktop وTablet وMobile وRTL وLTR وKeyboard و`200%` Zoom والحالة الفارغة والخصوصية وإخفاء الملف وحجب وسائطه وإعادة النشر. | التحقق اليدوي والوظيفي على ملف `khal` | `visually-approved` |
| `2026-08-03` | تثبيت TypeScript Toolchain محليًا، وإزالة أخطاء النوع داخل نطاق `006A`، ونجاح Client وSSR وNitro Production Build. | task-scope errors `0`; global errors `232` خارج النطاق؛ Build complete | `technically-verified / Git closure pending` |
| `2026-08-03` | إنشاء Commit التنفيذ والإغلاق بعد التحقق من قائمة الـ29 ملفًا وحماية المسارات المستثناة. | `3e1553c136d6d396055296a4b00aeb0ef771643d`؛ `29 files changed`, `3756 insertions`, `40 deletions` | `closed` |
