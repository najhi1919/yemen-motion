# YM-DESIGNER-PROFILE-PROFESSIONAL-DATA-004A — البيانات المهنية الموسعة وحالة التوفر

| الحقل | القيمة |
|---|---|
| Station ID | `YM-DESIGNER-PROFILE-PROFESSIONAL-DATA-004A` |
| الحالة | `closed` |
| المرحلة التابعة | Phase 6 — Designer Profiles |
| الاعتماديات | Historical basic profile workspace and identity media |
| نقطة الأساس | `4533c78710c9a70947e1a8315fc011113b9061b1` |
| تاريخ الفتح | `2026-07-31` |
| تاريخ الإغلاق | `2026-08-01` |
| Closure Commit | `382d2c3256f0a1eeb32787d475d097f07e035d9d` |

## الهدف

إضافة بيانات مهنية منظمة للمصمم وحالة توفر وخصوصية على مستوى الأقسام، مع حفظ ذري وآمن وواجهة داخلية متجاوبة، دون بناء Public Profile أوPublication Lifecycle.

## In Scope

- جداول normalized للتخصصات والمهارات والأدوات واللغات.
- `years_of_experience` و`professional_note` وحقول `show_*_publicly`.
- `GET` و`PUT /api/designer/profile/professional`.
- Full Replace-All، `expected_updated_at`، no-op، transactions، Audit، وprofessional completion.
- الخدمات والأساليب، المهارات ومستوياتها، الأدوات وشاراتها، اللغات ومستوياتها.
- اقتراحات سياقية وإدخال يدوي، حماية المسودات، Drawer وOverview.

## Out of Scope

- Public route أوPublic Profile.
- Publish Readiness أوالنشر أوالإخفاء.
- تطبيق الخصوصية أمام الجمهور.
- الأعمال المميزة، بيانات المنشأة، Admin Oversight، أوAccount Settings.

## القرارات المعتمدة

- availability تدار من البيانات المهنية مع توافق API الأساسي.
- «المناسبة» أزيلت من واجهة الملف المهني وتُرسل `occasion: []` للتوافق؛ تبقى المناسبة في تصنيف العمل.
- الاقتراحات لا تمنع الإدخال اليدوي، والأداة غير المعروفة تحصل على monogram محلي.
- إغلاق القسم عند `100%` يعرض تأكيدًا بدل progressbar.
- إعدادات الخصوصية تخزن فقط حتى بناء المستهلك العام.

## الملفات المنفذة

نفذ Closure Commit عدد `26 files`:

1. `app/Http/Controllers/Api/DesignerProfileController.php`
2. `app/Http/Controllers/Api/DesignerProfileProfessionalController.php`
3. `app/Http/Requests/Designer/DesignerProfileProfessionalShowRequest.php`
4. `app/Http/Requests/Designer/DesignerProfileProfessionalUpdateRequest.php`
5. `app/Http/Requests/Designer/UpsertDesignerProfileRequest.php`
6. `app/Http/Resources/DesignerProfileProfessionalResource.php`
7. `app/Models/DesignerProfile.php`
8. `app/Models/DesignerProfileLanguage.php`
9. `app/Models/DesignerProfileSkill.php`
10. `app/Models/DesignerProfileSpecialty.php`
11. `app/Models/DesignerProfileTool.php`
12. `app/Services/Designer/DesignerProfileProfessionalService.php`
13. `database/migrations/2026_07_31_000200_create_designer_profile_professional_data.php`
14. `frontend/components/designer/profile/DesignerProfileOverview.vue`
15. `frontend/components/designer/profile/DesignerProfileProfessionalDrawer.vue`
16. `frontend/components/designer/profile/DesignerProfileProfessionalListEditor.vue`
17. `frontend/components/designer/profile/DesignerProfileProfessionalOverview.vue`
18. `frontend/components/designer/profile/DesignerProfileSetupDrawer.vue`
19. `frontend/composables/useDesignerProfileProfessional.ts`
20. `frontend/data/designer-professional-catalog.ts`
21. `frontend/pages/designer/index.vue`
22. `frontend/types/designer-profile-professional.ts`
23. `frontend/types/designer-profile.ts`
24. `routes/api.php`
25. `tests/Feature/Designer/DesignerProfileBootstrapTest.php`
26. `tests/Feature/Designer/DesignerProfileProfessionalTest.php`

## العقود والواجهات

- `GET` يعيد professional data وcompletion وoptions دون IDs أوnormalized names.
- `PUT` عقد Full Replace-All؛ المفاتيح الستة للقوائم `present` وتقبل `[]`.
- optimistic concurrency عبر `expected_updated_at` بعد `lockForUpdate`.
- no-op يعيد `changed: false` دون touch أوإعادة بناء علاقات أوAudit.
- التغيير الفعلي يستبدل العلاقات داخل transaction واحدة ويسجل Audit allowlisted.
- `publication_status` يبقى `draft`، ولا يوجد public route.

## تفاصيل الواجهة

- الخدمات والأساليب تعرض اقتراحات بحسب التخصص مع خيار «أخرى» وإدخال يدوي.
- المهارات والأدوات تضاف افتراضيًا بمستوى `intermediate`، واللغات بمستوى `basic`، مع إمكانية التعديل.
- شارات الأدوات محلية بلا أصول خارجية، مع fallback من أول حرفين.
- `commitPending()` يثبت المسودات قبل save واحد؛ فشل أي مسودة يمنع إرسال Payload.
- Combobox يغلق عند Escape أوالنقر خارجه أوخروج التركيز مع حفظ النص المكتوب.
- Drawer معتم داخل Teleport، مع Focus trap وbody scroll lock وReduced Motion.
- Dirty state يقارن canonical snapshot بعد hydration؛ لا يعد الفتح تعديلًا.
- زر الحفظ وزر الإضافة يستخدمان ألوانًا صريحة داخل Teleport.
- عناصر الخصوصية labels قابلة للنقر وتعرض الحالة بوضوح.
- عند `100%` تختفي progressbars وتظهر حالة اكتمال نصية مع أيقونة.

## اختبارات التحقق

- شغّلت بوابة الإغلاق النهائية `DesignerProfileProfessionalTest` و`DesignerProfileBootstrapTest` فقط.
- النتيجة المسجلة: `43 tests passed`.
- assertions: `297`.

## المراجعة البصرية

- passed للنطاق المنفذ على Desktop وMobile.
- راجعت Drawer، الشفافية، Dirty state، الخصوصية، حفظ المسودات، الاقتراحات، حالات الاكتمال، وشارات البرامج.
- لا تمثل هذه المراجعة Final Phase Accessibility Closure.

## Commit

`382d2c3256f0a1eeb32787d475d097f07e035d9d` — `feat(designer): add professional profile data`.

## Push

تم Push إلى `origin/main`، ويطابق `origin/main` نقطة الإغلاق عند التوثيق.

## CI

لم تظهر نتيجة في الاستعلام الفوري الوحيد بعد Push. لم ينفذ polling. لا تسجل حالة CI نجاحًا أوفشلًا.

## المشاكل المكتشفة والتصحيحات

- صحح helper الاختبارات ليولد Full Replace-All Payload كاملًا.
- استبدلت `required` بـ`present` للمصفوفات التي يجب حضورها ويجوز أن تكون فارغة.
- ثُبت canonical baseline بعد hydration لمنع Dirty state الزائف.
- أضيفت خلفيات معتمة وعزل Drawer بعد Teleport.
- قربت checkboxes من labels ووسعت هدف النقر.
- استبدلت ألوان أزرار تعتمد على CSS variables بألوان صريحة.
- أضيف `commitPending` لمنع فقد النصوص غير المثبتة.
- أضيف الكتالوج والـCombobox والإغلاق عند النقر الخارجي أوTab.
- أزيلت «المناسبة» من UI مع `occasion: []` في Payload.
- حُسنت حالات `100%` وشارات الأدوات وتوازن شبكة Overview.

## الحدود المتبقية

لا Publication Readiness، لا نشر أوإخفاء، لا Public Profile، لا privacy enforcement عام، لا featured works، ولا مراجعة وصولية شاملة للمرحلة.

## المحطة التالية

`YM-DESIGNER-PROFILE-PUBLICATION-LIFECYCLE-005A`.

## سجل زمني Append-only

| التاريخ | الحدث | الدليل | الحالة |
|---|---|---|---|
| `2026-07-31` | فتح المحطة من baseline. | `4533c78710c9a70947e1a8315fc011113b9061b1` | `in-progress` |
| `2026-08-01` | نجاح بوابة Professional/Bootstrap والـProduction Build والمراجعة البصرية. | `43 tests`, `297 assertions`, production build | `technically-verified / visually-approved` |
| `2026-08-01` | Commit وPush. | `382d2c3256f0a1eeb32787d475d097f07e035d9d`, `origin/main` | `closed` |
| `2026-08-01` | الاستعلام الفوري عن CI بلا نتيجة؛ لا polling. | سجل الإغلاق | `closed; CI result not observed` |
