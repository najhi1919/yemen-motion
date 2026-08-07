# YM-DESIGNER-PROFILE-ORGANIZATION-BRAND-008A — بيانات المنشأة والعلامة التجارية

| الحقل | القيمة |
|---|---|
| Station ID | `YM-DESIGNER-PROFILE-ORGANIZATION-BRAND-008A` |
| الحالة | `closed` |
| المرحلة التابعة | Phase 6 — Designer Profiles |
| الاعتماديات | `004A`, `005A`, `006A` |
| نقطة الأساس | `8759b6ed8b1cbbd4d83334f8ff6659cce498d3f5` |
| تاريخ الفتح | `2026-08-07` |
| تاريخ الإغلاق | `2026-08-08` |
| Current step | `closed — Git closure complete` |

## الهدف

توفير كيان بيانات للمنشأة/العلامة التجارية (Organization/Brand) المرتبطة بالمصمم، لعرضها ضمن الملف العام. البيانات اختيارية بالكامل ولا تؤثر على حالة اكتمال الملف أو جاهزية النشر، وتدير خصوصيتها بشكل مستقل، مع التعامل الآمن مع التزامن وصور الشعار.

## In Scope

- جدول مستقل `designer_profile_organizations` يعود بملكية `0..1` حصرية للمصمم.
- `show_publicly=false` يعني إخفاء المنشأة عن الجمهور فقط. تبقى البيانات محفوظة للمالك. `DELETE organization` هو العملية الوحيدة التي تزيل المنشأة كاملة.
- التعامل مع حقول: `organization_name`, `organization_type`, `description`, `website_url`, `logo_path`, `show_publicly`.
- حماية التزامن للكيان والشعار (Optimistic Concurrency) استنادًا إلى `organization.updated_at` بدقة المايكروثانية.
- إدارة الشعار المستقلة: دورة رفع، تحديث، وحذف ملفات Storage القديمة بشكل مطابق لدورة Avatar/Cover. (الحد الأقصى 2MB، الصيغ: jpg, jpeg, png, webp، يمنع SVG).
- العرض العام: إخفاء بيانات الكيان بالكامل عند `show_publicly=false` بإرجاع `{ "organization": { "visible": false } }`.
- سجل Audit مخصص يقتصر على الحقول المسموحة (allowlist) ويتجاهل الحقول الوصفية كـ`description` و`logo_path`.

## Out of Scope

يجب أن يبقى خارج 008A:
- Admin Oversight
- Account Settings
- Service Requests
- Organization Accounts
- Teams
- Billing
- Authentication
- Branches
- Legal Verification
- Generic Storage Cleanup
- جاهزية النشر (Publication Readiness) واكتمال الملف. المنشأة اختيارية ولا تمنع المصمم المستقل من نشر ملفه.

## القرارات المعتمدة

`DP-DEC-015`: تم اعتماد فصل `Organization/Brand Data` في كيان مستقل وعدم إضافة حقول إضافية لجدول `designer_profiles`. غياب المنشأة يترجم إلى عدم وجود صف مرتبط. دورة التزامن تعتمد `updated_at` الخاص بالمنشأة، ولا تؤثر على `designer_profiles.updated_at`.

## الملفات المنفذة

نُفذ Backend وFrontend كاملان:

**Backend (12 ملفًا):**
- `database/migrations/2026_08_07_160000_create_designer_profile_organizations_table.php`
- `app/Models/DesignerProfileOrganization.php`
- `app/Http/Requests/Designer/UpsertDesignerProfileOrganizationRequest.php`
- `app/Http/Requests/Designer/UploadDesignerProfileOrganizationLogoRequest.php`
- `app/Http/Requests/Designer/DesignerProfileOrganizationVersionRequest.php`
- `app/Http/Controllers/Api/DesignerProfileOrganizationController.php`
- `tests/Feature/Designer/DesignerProfileOrganizationTest.php`
- ملفات معدّلة: `app/Models/DesignerProfile.php`, `app/Services/Designer/PublicDesignerProfileService.php`, `app/Http/Resources/PublicDesignerProfileResource.php`, `app/Http/Controllers/Api/PublicDesignerProfileMediaController.php`, `routes/api.php`

**Frontend (9 ملفات):**
- `frontend/types/designer-profile-organization.ts`
- `frontend/composables/useDesignerProfileOrganization.ts`
- `frontend/components/designer/profile/DesignerProfileOrganizationPanel.vue`
- `frontend/components/designer/profile/DesignerProfileOrganizationDrawer.vue`
- `frontend/components/designer/profile/DesignerProfileOrganizationDeleteDialog.vue`
- ملفات معدّلة: `frontend/pages/designer/index.vue`, `frontend/types/public-designer-profile.ts`, `frontend/pages/designers/[username].vue`, `frontend/components/public/designer/PublicDesignerProfessionalSections.vue`

## العقود والواجهات

**Owner API:**
- `GET /api/designer/profile/organization` — عند عدم وجود Organization يعيد `200` بـ `organization: null` و `updated_at: null`.
- `PUT /api/designer/profile/organization` — Upsert مع `expected_updated_at` required but nullable. يعيد `409 organization_version_conflict` عند التعارض.
- `DELETE /api/designer/profile/organization` — حذف المنشأة بالكامل مع تنظيف الشعار لاحقًا.
- `GET /api/designer/profile/organization/logo/content` — بث الشعار للمالك.
- `POST /api/designer/profile/organization/logo` — رفع شعار جديد مع `expected_updated_at` non-null.
- `DELETE /api/designer/profile/organization/logo` — حذف الشعار مع كشف no-op.

**Public API:**
- في `GET /api/designers/{username}` أصبح `organization` top-level sibling لـ`professional`.
- عند `show_publicly=false` أو غياب المنشأة: `{ "organization": { "visible": false } }`.
- عند `show_publicly=true`: `visible`, `name`, `type`, `description`, `logo_url`, `website_url`.
- الشعار العام عبر `/designers/{username}/organization/logo`.

## اختبارات التحقق

- `DesignerProfileOrganizationTest`: `23 tests / 95 assertions` — ناجحة.
- 008A Task-Specific TypeScript Gate: `PASS` (لا أخطاء جديدة في ملفات المحطة).
- Global TypeScript Debt: `229` خطأ مستقل عن المحطة (لا يجوز فتح مهمة لإصلاحه داخل 008A).
- Final Production Build (`npm run build`): `exit 0` — `Build complete`.

## المراجعة البصرية

نجحت المراجعة اليدوية على `http://127.0.0.1:3000/designer` وشملت:
- Empty state.
- فتح Drawer.
- Dirty-close guard.
- إنشاء المنشأة.
- رفع الشعار.
- hidden-by-default.
- Private summary.
- make public.
- public rendering.
- حذف الشعار.
- إعادة رفع الشعار.
- حذف المنشأة بالكامل.
- العودة لحالة Empty.

## Commit

`1ec3a88` — `feat(designer): add profile organization brand`.
- `21 files changed`, `2378 insertions`, `4 deletions`.
- شمل الـCommit: 12 ملف Backend + 9 ملفات Frontend.
- بقيت خارج الـCommit: `PROJECT_MAP.md`, `reports/`, 26 ملفًا معدّلاً غير متعلق بـ008A, 17 ملفًا untracked, نقل `DesignerWorksReviewSubmissionController` import (بقي unstaged عمدًا).

## Push

تم الدفع إلى `origin/main`، وتم التحقق من أن `origin/main` يشير إلى `1ec3a88`.

## CI

—

## المشاكل المكتشفة والتصحيحات

### 1. Drawer/Teleport Design Tokens
اكتُشف أن زر "حفظ" في Organization Drawer لم يكن ظاهرًا بصريًا رغم وجوده هندسيًا داخل الشاشة. السبب الجذري: Organization Drawer يستخدم `<Teleport to="body">` بينما CSS design tokens كانت scoped على container داخل صفحة المصمم لا على `body/:root`.

**الإصلاح:** تثبيت Design Tokens على Root `<aside>` المنقول نفسه، مع إعادة هيكلة Shell إلى:
- Backdrop مستقل fixed.
- aside: fixed / inset-y-0 / left-0 / h-dvh / min-h-dvh / max-h-dvh / overflow-hidden.
- middle: min-h-0 / flex-1 / overflow-y-auto.
- footer: shrink-0.

**الدرس المعماري:** أي Component يستخدم `Teleport` ويعتمد على scoped CSS variables يجب فحص inheritance؛ لا تفترض وصول Tokens بعد نقل العنصر إلى `body`.

### 2. Trailing Whitespace
اكتُشف في Final Staged Audit وجود مسافات زائدة في نهايات الأسطر في 3 ملفات:
- `app/Http/Controllers/Api/DesignerProfileOrganizationController.php` (10 مواقع).
- `database/migrations/2026_08_07_160000_create_designer_profile_organizations_table.php` (1 موقع).
- `tests/Feature/Designer/DesignerProfileOrganizationTest.php` (10 مواقع).

**الإصلاح:** استخدام `sed -i 's/[[:space:]]*$//'` على الـ3 ملفات فقط، ثم إعادة تجهيزها للإرسال. نجح `git diff --cached --check` بعدها بمخرج فارغ.

### 3. Partial Success Contract
عند نجاح PUT وفشل Logo operation: لا Rollback. الـDrawer يبقى مفتوحًا، local server token يتحدث، file selection يحتفظ به قدر الإمكان، والرسالة: "تم حفظ بيانات المنشأة، لكن تعذر تحديث الشعار. حاول مرة أخرى."

## الحدود المتبقية

أُغلقت المحطة بالـClosure Commit `1ec3a88` بعد نجاح التحقق التقني والبصري وRuntime QA وFinal Staged Audit، ثم دفع الـCommit والتحقق من تطابق `origin/main`. لا يوجد نطاق Runtime متبقٍ داخل `008A`، وتبقى النطاقات المستقبلية في Phase 6 منفصلة.

## المحطة التالية

غير معيّنة حتى الآن. تبقى Admin Oversight وFinal Accessibility and Responsive Closure نطاقات مستقبلية بلا Station ID معتمد، ولا يُخترع معرف قبل اعتماد محطة مستقلة.

## سجل زمني Append-only

| التاريخ | الحدث | الدليل | الحالة |
|---|---|---|---|
| `2026-08-07` | فتح محطة `008A` من baseline إغلاق `007A` واعتماد فصل Organization/Brand Data في كيان مستقل. | Baseline `8759b6ed8b1cbbd4d83334f8ff6659cce498d3f5`؛ `DP-DEC-015` | `planned / contract approved` |
| `2026-08-07` | اكتمال Backend وFrontend لمحطة `008A` واجتياز التحقق البرمجي التفاضلي. | Backend `23 tests / 95 assertions`؛ Frontend Source Test؛ TypeScript introduced errors `0` في نطاق 008A؛ Production Build `exit 0` | `technically-verified` |
| `2026-08-07` | اكتشاف وإصلاح مشكلة Drawer/Teleport Design Tokens. | Browser Console measurements؛ Design Tokens على Root `<aside>` المنقول | `technically-verified / visually-approved` |
| `2026-08-08` | نجاح Runtime/Visual QA لمحطة `008A` على `http://127.0.0.1:3000/designer`. | Empty state؛ Drawer؛ dirty-close guard؛ create؛ logo upload؛ hidden default؛ make public؛ public rendering؛ logo delete؛ re-upload؛ whole delete | `technically-verified / visually-approved; Git closure pending` |
| `2026-08-08` | Final Staged Audit: 21 ملفًا، `git diff --cached --check` clean، `PROJECT_MAP.md` و`reports/` غير مُدرجين، `routes/api.php = MM` مقصود. | 6 فحوصات Git read-only | `Git closure pending` |
| `2026-08-08` | إصلاح trailing whitespace في 3 ملفات وإعادة التجهيز. | `sed -i 's/[[:space:]]*$//'` على 3 ملفات محددة | `Git closure pending` |
| `2026-08-08` | إنشاء Commit التنفيذ والإغلاق ودفعه إلى `origin/main`. | `1ec3a88`؛ `21 files changed`، `2378 insertions`، `4 deletions`؛ staged scope مطابق؛ forbidden/review scope خارج الـCommit؛ remote verification ناجح | `closed` |
