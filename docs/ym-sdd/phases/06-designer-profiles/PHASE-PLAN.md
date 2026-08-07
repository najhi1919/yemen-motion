# خطة Phase 6 — Designer Profiles

## الهدف الكامل

إنشاء منظومة ملف المصمم من Workspace داخلي آمن إلى حضور عام قابل للنشر: الهوية الأساسية، الصورة والغلاف، البيانات المهنية، التوفر، خصوصية الأقسام، جاهزية النشر، المعاينة العامة، النشر والإخفاء، `/designers/{username}`، الأعمال المميزة وجميع الأعمال العامة، بيانات المنشأة عند انطباقها، الإشراف الإداري، والإغلاق البصري والوصولي.

## الاعتماديات

- الاعتمادية العليا: المرحلة 5 — Staff Management.
- المرحلة التابعة: المرحلة 7 — Works Management + Public Homepage Feed.
- أعمال المصمم المنفذة حاليًا قدرة مجاورة يعتمد عليها العرض العام، وليست إغلاقًا لهذه المرحلة.

## مسارات العمل

| المسار | النطاق | الحالة عند نقطة الأساس |
|---|---|---|
| A. Internal Workspace | الهوية الأساسية، الاسم المهني واسم المستخدم، الصورة والغلاف ونقطة التركيز، النبذة، البيانات المهنية، التوفر، الخصوصية المخزنة، الاكتمال والحفظ الآمن | `implemented / largely verified` |
| B. Publication Lifecycle | شروط الجاهزية، معاينة الزائر، النشر، الإخفاء أوإلغاء النشر، الرسائل والتدقيق | `closed`؛ محطة `005A`، Closure Commit `d7a954d2ee0a3c964de3395b50a398b33bb5954a` |
| C. Public Profile | الصفحة العامة `/designers/{username}`، البيانات العامة، الحالات العامة، SEO | `closed`؛ Closure Commit `3e1553c136d6d396055296a4b00aeb0ef771643d` |
| D. Featured Works | اختيار الأعمال العامة، الحد والترتيب والصلاحية | `closed`؛ Closure Commit `ff6c862fd215e7b1703ae68e4490337b13b21b55` |
| E. Organization/Brand Data | بيانات المنشأة أوالعلامة عند انطباقها | `planned / contract approved` |
| F. Admin Oversight | إدارة ومراجعة ملفات المصممين إداريًا | `not implemented / ID unassigned` |
| G. Final Accessibility and Responsive Closure | Desktop وTablet وMobile وRTL وLTR وKeyboard وZoom والحالات | `partially-met / ID unassigned` |

## الترتيب المعتمد للمحطات التالية

| الترتيب | Station ID | الحالة | الاعتمادية |
|---|---|---|---|
| 1 | `YM-DESIGNER-PROFILE-PUBLICATION-LIFECYCLE-005A` | `closed`؛ `d7a954d2ee0a3c964de3395b50a398b33bb5954a` | Workspace والبيانات المهنية |
| 2 | `YM-DESIGNER-PUBLIC-PROFILE-006A` | `closed`؛ `3e1553c136d6d396055296a4b00aeb0ef771643d` | إغلاق `005A` |
| 3 | `YM-DESIGNER-PROFILE-FEATURED-WORKS-007A` | `closed`؛ `ff6c862fd215e7b1703ae68e4490337b13b21b55` | العقود النهائية في `005A` و`006A` |
| 4 | `YM-DESIGNER-PROFILE-ORGANIZATION-BRAND-008A` | `planned / contract approved` | — |

## نطاقات مستقبلية بلا Station ID معتمد

- Admin Oversight.
- Final Accessibility and Responsive Closure.

لا يُخترع معرف لهذه النطاقات قبل اعتماد محطة مستقلة.

## خارج دمج المرحلة

- `YM-DESIGNER-ACCOUNT-SETTINGS-001A` محطة مستقلة.
- `YM-ADMIN-MEDIATED-REQUESTS-001A` محطة مستقلة، ولا تعرض أزرار طلب خدمة معطلة قبل اكتمالها.
