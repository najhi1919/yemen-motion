# YM-AUDIT-TRACKING-DOCS-001 — Audit Tracking Baseline

## Scope

توثيق baseline التنفيذي الحالي لـAudit Tracking دون تغيير أي منطق برمجي. يشمل الأساس المركزي، والأحداث المكتملة، وتتبع مسارات الإدارة والموظفين من الواجهة.

## Completed events

- Auth: `user.login.success`, `user.login.failed`, `user.logout`.
- Staff: `staff.created`.
- Dashboard: `dashboard.search.performed`.
- Roles: `role.created`, `role.updated`, `role.deleted`, `role.permissions.synced`.
- Permissions: `permission.created`, `permission.updated`, `permission.deleted`.
- Admin users: `user.roles.synced`.
- Page views: `admin.page.viewed` لمسارات `/admin` و`/staff`.

الأساس المنفذ يتكون من جدول `audit_events`، و`AuditEvent` model، وخدمة `AuditEventLogger`.

## Security constraints

- لا تحفظ passwords أو tokens أو cookies أو payload أو request كاملًا.
- لا تحفظ page views أي query string أو full URL أو referrer.
- أدوار `client` و`designer` ممنوعة من internal page view tracking حتى مع صلاحيات عرضية.
- فشل التسجيل لا يكسر runtime، ويعاد رمي الخطأ في testing لكشف العيوب.

## Verification baseline

- توجد اختبارات مستهدفة للخدمة والأحداث المكتملة وقيود التفويض والتنقيح.
- تتبع الواجهة مركزي، يستخدم `route.path` فقط، ويمنع التكرار المتتالي في الذاكرة.
- Audit logs are now being collected for key internal events.
- Reports/Analytics UI and audit log reading APIs are still future work.

Latest commit expected before this docs step: `f95f036`.
