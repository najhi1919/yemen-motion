# YM-DESIGNER-PROFILE-ORGANIZATION-BRAND-008A — بيانات المنشأة والعلامة التجارية

| الحقل | القيمة |
|---|---|
| Station ID | `YM-DESIGNER-PROFILE-ORGANIZATION-BRAND-008A` |
| الحالة | `planned / contract approved` |
| المرحلة التابعة | Phase 6 — Designer Profiles |
| الاعتماديات | `004A`, `005A`, `006A` |
| نقطة الأساس | `8759b6ed8b1cbbd4d83334f8ff6659cce498d3f5` |
| تاريخ الفتح | `2026-08-07` |
| تاريخ الإغلاق | — |
| Current step | `design approved — awaiting implementation` |

## الهدف

توفير كيان بيانات للمنشأة/العلامة التجارية (Organization/Brand) المرتبطة بالمصمم، لعرضها ضمن الملف العام. البيانات اختيارية بالكامل ولا تؤثر على حالة اكتمال الملف أوجاهزية النشر، وتدير خصوصيتها بشكل مستقل، مع التعامل الآمن مع التزامن وصور الشعار.

## In Scope

- جدول مستقل `designer_profile_organizations` يعود بملكية `0..1` حصرية للمصمم.
- `show_publicly=false` يعني إخفاء المنشأة عن الجمهور فقط. تبقى البيانات محفوظة للمالك. `DELETE organization` هو العملية الوحيدة التي تزيل المنشأة كاملة.
- التعامل مع حقول: `organization_name`, `organization_type`, `description`, `website_url`, `logo_path`, `show_publicly`.
- حماية التزامن للكيان والشعار (Optimistic Concurrency) استنادًا إلى `organization.updated_at`.
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

## التصميم المعماري (Approved Contract)

### 1. Data Model (PostgreSQL)

```sql
CREATE TABLE designer_profile_organizations (
    id BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    designer_profile_id BIGINT NOT NULL UNIQUE REFERENCES designer_profiles(id) ON DELETE CASCADE,

    organization_name VARCHAR(160) NOT NULL,
    organization_type VARCHAR(32) NOT NULL, -- 'studio', 'agency', 'company', 'brand', 'other'
    description TEXT, -- validation max 1000 characters
    website_url VARCHAR(2048), -- https فقط
    logo_path VARCHAR(512),
    show_publicly BOOLEAN NOT NULL DEFAULT true,

    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
```

### 2. Endpoints (Owner Scope)

- **GET /api/designer/profile/organization**: عند عدم وجود Organization يجب أن يعيد 200 وبنية ثابتة: `organization: null`, `updated_at: null`. وعند وجودها يعيد `organization: { name, type, description, has_logo, website_url, show_publicly }` مع `updated_at`. لا يعيد `logo_path`.
- **PUT /api/designer/profile/organization**: تحديث أوإنشاء المنشأة (Upsert). `expected_updated_at`: required but nullable دائمًا. عند الإنشاء الأول `expected_updated_at = null` (العميل يتوقع عدم وجودها). إذا وُجدت بالفعل يعيد `409 organization_version_conflict`. لا تجعل الحقل اختياريًا.
- **DELETE /api/designer/profile/organization**: لحذف المنشأة بالكامل. يتطلب `expected_updated_at`. يتم الحذف من قاعدة البيانات داخل العملية الآمنة. يُثبّت حذف قاعدة البيانات، وبعد النجاح يحاول حذف ملف Logo من Storage. إذا فشل حذف الملف لا يعكس حذف المنشأة ويُسجل فشل تنظيف لاحق. لا تبن Scheduled Job الآن.
- **POST /api/designer/profile/organization/logo**: يتطلب `expected_updated_at` (required non-null). يفشل بـ `409 organization_version_conflict` إذا كانت النسخة قديمة. يُخزن الملف الجديد أولًا في مسار جديد. يتم تحديث `logo_path` وتثبيت قاعدة البيانات. بعد النجاح يُحذف الملف القديم. إذا فشلت قاعدة البيانات يُحذف الملف الجديد ولا يُمَس القديم. إذا فشل حذف القديم يُسجل فشل تنظيف ولا يعكس النجاح. يعاد `updated_at` الجديد.
- **DELETE /api/designer/profile/organization/logo**: يتطلب `expected_updated_at`. إذا لم يوجد Logo أصلاً يُعتبر `changed=false`، بلا `updated_at` جديد ولا Audit. إذا وُجد يعتبر تغييرًا حقيقيًا.

### 3. Concurrency Model

يُستخدم الحقل `updated_at` في جدول `designer_profile_organizations` كمرجع وحيد للتزامن.
- **409 Conflict**: يُرد الخطأ `organization_version_conflict` عند عدم تطابق `expected_updated_at`.
- التعديل لا يمسّ `updated_at` الخاص بـ`designer_profiles`.

### 4. Audit Scope

وثّق الأحداث المعتمدة:
- `designer.profile.organization.created`
- `designer.profile.organization.updated`
- `designer.profile.organization.deleted`
- `designer.profile.organization.logo_uploaded`
- `designer.profile.organization.logo_removed`

- تُفلتر الـMetadata باستخدام Allowlist المسموح فقط: `['profile_id', 'organization_id', 'changed_fields', 'visibility_changed', 'logo_changed', 'name_changed', 'operation']`. ويمكن تسجيل: `['previous_type', 'current_type', 'previous_show_publicly', 'current_show_publicly']`.
- ممنوع تخزين القيم الكاملة لـ: `organization_name`, `description`, `website_url`, `logo_path`.

### 5. Public Endpoint Contract

في الحالتين: A) لا توجد Organization، B) و`show_publicly=false`، يجب أن تعيدا نفس الشكل تمامًا:
`{ "organization": { "visible": false } }`
لا تحذف `organization` key ولا تستخدم `organization=null` للعامة.

عند `show_publicly=true` يعاد:
`visible`, `name`, `type`, `description`, `logo_url`, `website_url`.
`logo_url=null` إذا لا يوجد Logo.

## المحطة التالية

تنفيذ الـMigrations والـBackend Controllers والـFrontend Components الخاصة بهذه المحطة، متبوعةً بالاختبارات والـRuntime Validation قبل إغلاقها.
