# YM-DESIGNER-PROFILE-FEATURED-WORKS-007A — اختيار وترتيب الأعمال المميزة

| الحقل | القيمة |
|---|---|
| Station ID | `YM-DESIGNER-PROFILE-FEATURED-WORKS-007A` |
| الحالة | `technically-verified / visually-approved; Git closure pending` |
| المرحلة التابعة | Phase 6 — Designer Profiles |
| الاعتماديات | العقود النهائية لـ`005A` و`006A` |
| نقطة الأساس | `82f21d47f3effc7d6196c5e2b9c120db407e0eb6` |
| تاريخ الفتح | `2026-08-05` |
| تاريخ الإغلاق | — |
| Current step | `007A-3 — Final Verification and Git Closure` |

## الهدف

تمكين المصمم من اختيار ما يصل إلى 6 أعمال منشورة وعامة مملوكة له، وترتيبها يدويًا داخل قسم مستقل في ملفه العام، دون استخدام علامات الترويج الإداري `is_featured` أو`is_pinned`.

## In Scope

- اختيار 0 إلى 6 أعمال منشورة وعامة مملوكة للمصمم.
- ترتيب يدوي ذري محفوظ عبر `position`.
- منع الأعمال غير العامة.
- إزالة الاختيارات غير الصالحة عند فقدان الملكية أوالنشر أوالظهور العام، مع منع ظهورها دفاعيًا في عقود القراءة.
- فصل الأعمال المختارة عنجميع الأعمال.
- استخدام Fill/Fit وFocus Point في العرض.

## Out of Scope

- إنشاء أوتحرير الأعمال.
- نشر الملف أوإنشاء الصفحة العامة.
- تغيير lifecycle العمل أووسائطه.

## القرارات المعتمدة

`DP-DEC-007` و`DP-DEC-014`. يعتمد العقد جدولًا مستقلًا `designer_profile_featured_works`، وحدًا أعلى مقداره 6، وترتيبًا يدويًا، وOptimistic Concurrency عبر `DesignerProfile.updated_at`.

## الملفات المنفذة

نُفذ Backend وFrontend كاملان: جدول `designer_profile_featured_works` والنموذج والعلاقات وطلبات القراءة والتحديث والخدمة والموارد والمسارات، ودمج Owner Panel/Drawer في `/designer`، وقسم `featured_works` المستقل قبل `works` في `/designers/{username}` مع منع التكرار.

## العقود والواجهات

العقد المعتمد: `GET` و`PUT /api/designer/profile/featured-works`، بحد 6، وPayload يحتوي `expected_updated_at` و`work_ids` المرتبة. يستجيب العقد العام بـ`featured_works` مستقلة عن `works` مع منع التكرار.

## اختبارات التحقق

نجحت اختبارات Featured Works وPublic Profile واختبارات Regression المجاورة وFrontend Source Test. سجلت بوابة TypeScript التفاضلية صفر أخطاء جديدة خاصة بـ`007A`، ونجح CSS scope check وNuxt Production Build.

## المراجعة البصرية

نجحت المراجعة اليدوية على Desktop وKeyboard و`200%` Zoom وMobile `390×844` والملف العام. تم التحقق من الحفظ الفعلي وعدم التكرار وNo-op وRuntime Conflict `409`، ثم استعادة الحالة النهائية إلى عمل مميز واحد.

## Commit

—

## Push

—

## CI

—

## المشاكل المكتشفة والتصحيحات

أظهر التدقيق أن `is_featured` و`is_pinned` علامات ترويج إدارية وليستا عقد اختيار المصمم، ولا يوجد حاليًا موضع ترتيب مخصص للأعمال المميزة.

## الحدود المتبقية

لا يوجد نطاق Runtime متبقٍ داخل `007A`. المتبقي هو Final Git Scope Gate ثم Commit وPush المنضبطان. النطاقات المستقبلية في Phase 6 تبقى منفصلة.

## المحطة التالية

لا تحدد قبل إغلاق `007A` واعتماد النطاق التالي في Phase 6.

## سجل زمني Append-only

| التاريخ | الحدث | الدليل | الحالة |
|---|---|---|---|
| `2026-08-01` | تسجيل الهدف المبدئي والاعتماديات دون ادعاء تنفيذ. | `PHASE-PLAN.md`, `DP-DEC-007` | `planned` |
| `2026-08-05` | فتح `007A-1` واعتماد العقد المستقل للأعمال المميزة بحد 6 وترتيب يدوي، مع استبعاد علامات Admin. | Baseline `82f21d47f3effc7d6196c5e2b9c120db407e0eb6`؛ `DP-DEC-014` | `in-progress` |
| `2026-08-06` | اكتمال Backend وFrontend والتحقق البرمجي التفاضلي. | Backend/Public/Regression tests؛ Frontend Source Test؛ TypeScript introduced errors `0`؛ CSS scope وProduction Build ناجحان | `technically-verified` |
| `2026-08-07` | اكتمال Migration التطويرية وRuntime/Visual QA. | PostgreSQL schema؛ Owner/Public API؛ media؛ save؛ no-op؛ `409` conflict؛ deduplication؛ Desktop/Keyboard/200%/Mobile/Public Profile | `technically-verified / visually-approved; Git closure pending` |
