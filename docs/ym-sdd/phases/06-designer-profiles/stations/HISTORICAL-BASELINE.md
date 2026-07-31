# Historical Baseline — Designer Profile capabilities

## الغرض والمنهج

يوثق هذا الملف القدرات الموجودة قبل إنشاء نظام phase documentation. راجع التدقيق القرائي تاريخ Git للمسارات التالية: `frontend/pages/designer/`، `frontend/components/designer/profile/`، `frontend/composables/useDesignerProfile*`، `frontend/types/designer-profile*`، `app/Models/DesignerProfile.php`، `app/Http/Controllers/Api/DesignerProfileController.php`، و`tests/Feature/Designer/`.

لم تظهر رسائل Commit أووثائق مرتبطة تثبت Station ID أصليًا للقدرات أدناه. لذلك ينطبق عليها جميعًا:

`Historical implementation — original station ID not recoverable from repository evidence.`

## القدرات المثبتة

| التاريخ | Commit | القدرات المثبتة | ملفات محورية | Station ID الأصلي |
|---|---|---|---|---|
| `2026-07-28` | `e80f4a4c8bf282d1d7fc1467aad1c6c264f8b488` | إنشاء Workspace الملف؛ الاسم المهني واسم المستخدم والمسمى والتخصص والنبذة؛ اكتمال أساسي؛ حفظ واسترجاع؛ بطاقة عرض داخلية | `DesignerProfileController.php`, `DesignerProfile.php`, migration الملف، `DesignerProfileOverview.vue`, `DesignerProfileSetupDrawer.vue`, `useDesignerProfile.ts`, `DesignerProfileBootstrapTest.php` | غير قابل للاسترجاع |
| `2026-07-28` | `e235939b558b2bd41453243dc8126274fb3d72c4` | الصورة الشخصية وصورة الغلاف ونقطة التركيز وإدارة وسائط الهوية | `DesignerProfileMediaController.php`, migration الوسائط، `DesignerProfileIdentityMedia.vue`, `DesignerProfileMediaDialog.vue`, `useDesignerProfileMedia.ts`, `DesignerProfileMediaTest.php` | غير قابل للاسترجاع |

## حدود الإثبات

- يثبت Commit الأول وجود الهوية الأساسية واكتمال الملف الأساسي عند تلك النقطة، ولا يثبت نشر الملف أوصفحة عامة.
- يثبت Commit الثاني وسائط الملف ونقطة التركيز، ولا يثبت دورة نشر أوSEO.
- رسائل Commits هي `feat(designer): add profile bootstrap workspace` و`feat(designer): add profile identity media` بلا Station ID.
- لا تُنسب هذه القدرات إلى IDs مفترضة مثل `001A` أو`002A` أو`003A`.
- بطاقة الهوية الداخلية ليست Public Profile Preview كاملة.

## سجل زمني Append-only

| التاريخ | الحدث | الدليل |
|---|---|---|
| `2026-07-28` | إدخال Workspace الملف الأساسي. | `e80f4a4c8bf282d1d7fc1467aad1c6c264f8b488` |
| `2026-07-28` | إدخال وسائط الهوية ونقطة التركيز. | `e235939b558b2bd41453243dc8126274fb3d72c4` |
| `2026-08-01` | تدقيق التاريخ وتسجيل تعذر استرجاع Station IDs الأصلية. | Git log/show للمسارات المحددة |
