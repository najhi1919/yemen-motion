# YM-DESIGNER-PROFILE-ORGANIZATION-BRAND-008A-CLOSURE

## الحالة

مكتملة ومغلقة بتاريخ 2026-08-08.

## المحطة

- الاسم: بيانات المنشأة والعلامة التجارية.
- المسارات المعتمدة:
  - Owner API: `/api/designer/profile/organization` و`/api/designer/profile/organization/logo`.
  - Public API: `/api/designers/{username}` (top-level `organization` sibling).
  - Public Logo: `/designers/{username}/organization/logo`.
- Frontend Owner UI: `/designer` (Organization Panel + Drawer + Delete Dialog).
- Frontend Public UI: `/designers/{username}` (Organization Card).

## الكيان المعتمد

- جدول مستقل: `designer_profile_organizations`.
- العلاقة: `DesignerProfile 1 → 0..1 Organization`.
- ليس `freelancer row` ولا جزءًا مضمنًا داخل Professional JSON.
- الأنواع: `studio`, `agency`, `company`, `brand`, `other`.
- `show_publicly`, `name`, `type`, `description`, `website_url`, `logo_path`.
- تعديل Organization لا يلمس parent DesignerProfile timestamp.

## Logo Lifecycle

- الصيغ المسموحة: `jpg`, `jpeg`, `png`, `webp`.
- الحد الأقصى: `2MB`.
- SVG ممنوع.
- HTTPS validation على `website_url`.
- Description max 1000 حرف.
- First create: `show_publicly = false` افتراضيًا.

## Concurrency

- كل mutation تعتمد `expected_updated_at`.
- عند التعارض: `409 organization_version_conflict`.
- Backend locking: parent/profile lock ثم Organization `lockForUpdate()`.
- Microsecond timestamp precision عبر `protected $dateFormat = 'Y-m-d H:i:s.u'` و`$table->timestampsTz(6)`.

## النطاق المكتمل

- Empty state مع CTA لإضافة منشأة.
- Organization Drawer مستقل مع Logo، name، type، description، website، show publicly، delete.
- Dirty-close guard.
- إنشاء المنشأة مع first-create hidden default.
- رفع الشعار مع sequencing آمن: PUT organization ← receive new updated_at ← POST logo with new updated_at.
- Partial Success Contract: لا Rollback عند فشل Logo operation بعد نجاح PUT.
- Private summary وmake public.
- Public rendering عند `show_publicly=true`.
- حذف الشعار (لا يحتاج confirm).
- إعادة رفع الشعار.
- حذف المنشأة بالكامل مع تأكيد.
- العودة لحالة Empty.
- Design Tokens ثابتة على Root `<aside>` المنقول عبر Teleport.

## التحقق

- `DesignerProfileOrganizationTest`: `23 tests / 95 assertions` — ناجحة.
- 008A Task-Specific TypeScript Gate: `PASS`.
- Global TypeScript Debt: `229` خطأ مستقل.
- Final Production Build (`npm run build`): `exit 0`.
- Browser UX Journey على `http://127.0.0.1:3000/designer`: `PASS`.
- Final Staged Audit: 21 ملفًا، `git diff --cached --check` clean.
- `PROJECT_MAP.md` و`reports/`: بقيا خارج نطاق المحطة.
- نقل `DesignerWorksReviewSubmissionController` import: بقي unstaged عمدًا.

## المشاكل المكتشفة والتصحيحات

### 1. Drawer/Teleport Design Tokens
زر "حفظ" لم يكن ظاهرًا بصريًا رغم وجوده هندسيًا. السبب: scoped CSS variables لم تصل إلى `<aside>` المنقول عبر `<Teleport to="body">`. الإصلاح: تثبيت Design Tokens على Root `<aside>` نفسه مع إعادة هيكلة Shell (backdrop مستقل + aside fixed + middle flex-1 + footer shrink-0).

### 2. Trailing Whitespace
اكتُشف في Final Staged Audit مسافات زائدة في 3 ملفات (Controller + Migration + Test). الإصلاح: `sed -i 's/[[:space:]]*$//'` على الـ3 ملفات فقط.

## Commit

`1ec3a88` — `feat(designer): add profile organization brand`.
- `21 files changed`, `2378 insertions`, `4 deletions`.

## Push

تم الدفع إلى `origin/main`، وتم التحقق من أن `origin/main` يشير إلى `1ec3a88`.

## CI

—

## التحذيرات المعروفة غير المانعة

- Global TypeScript Debt: `229` خطأ مستقل عن المحطة.
- `authStore` mixed static/dynamic import warning.
- chunk size warning.
- sourcemap warning.

## حدود الإغلاق

- لا تغيير في Auth أوPermissions أو`.env` أوcredentials.
- لا تغيير في `PROJECT_MAP.md` (يحتاج محطة توثيق منفصلة).
- لا Admin Oversight (نطاق مستقبلي).
- لا Account Settings (نطاق مستقل).
- لا Service Requests (نطاق مستقل).
- لا تفعيل لـ`media_limits` على Organization logo (خارج نطاق المحطة).
