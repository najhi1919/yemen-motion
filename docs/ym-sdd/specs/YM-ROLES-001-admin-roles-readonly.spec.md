# YM-ROLES-001 — Admin Roles Read-only Foundation

## اسم المهمة

`YM-ROLES-001 — Admin Roles Read-only Foundation`

## المشكلة

- `/admin/roles` حاليًا يذهب إلى صفحة تحت البناء.
- توجد جداول Spatie للأدوار والصلاحيات، لكن لا توجد صفحة إدارية لعرض الأدوار.
- توجد مجموعة routes إدارية حالية تحت `/api/admin`.

## الهدف

- إنشاء endpoint قراءة فقط: `GET /api/admin/roles`.
- إنشاء صفحة `/admin/roles` لعرض الأدوار الحالية.
- عرض معلومات الدور الأساسية بدون أي عمليات تعديل.

## النطاق

- Backend: `GET` فقط.
- Frontend: صفحة واحدة فقط (`frontend/pages/admin/roles/index.vue`).
- Read-only فقط.
- استخدام Spatie Role model للقراءة فقط.

## خارج النطاق

- إنشاء role.
- تعديل role.
- حذف role.
- تغيير permissions.
- إنشاء permissions.
- تعديل permissions.
- إدارة مستخدمي الدور.
- Auth flow.
- migrations / seeders.
- User model.
- Sidebar / AppTopBar.
- `/admin/users`.
- `/admin/staff`.

## الملفات المسموحة

```text
routes/api.php
app/Http/Controllers/Api/Admin/RoleController.php
frontend/pages/admin/roles/index.vue
docs/ym-sdd/specs/YM-ROLES-001-admin-roles-readonly.spec.md
docs/ym-sdd/tasks/YM-ROLES-001-admin-roles-readonly.md
```

## معايير القبول

* [x] `/api/admin/roles` يعمل للمستخدم admin فقط.
* [x] غير المصادق يحصل على 401 عبر `auth:sanctum`.
* [x] المستخدم غير admin يحصل على 403 داخل controller.
* [x] `/admin/roles` تعرض صفحة حقيقية بدل under construction.
* [x] الصفحة تعرض جدول أدوار read-only.
* [x] الصفحة تحتوي loading / error / empty states.
* [x] لا توجد أزرار إنشاء أو تعديل أو حذف.
* [x] لا توجد عمليات تغيير صلاحيات.
* [x] لا يوجد تعديل خارج الملفات المسموحة.

## تفاصيل تقنية

### الاستجابة

الـ endpoint يرجع roles مع:

* `id`
* `name`
* `guard_name`
* `users_count`
* `permissions_count`
* `created_at`

### الحماية

* Route محمي بـ `auth:sanctum`.
* داخل controller يتم التحقق من `hasRole('admin')`.

### ملاحظة

أوامر التحقق اليدوية (`npm run build`, `route:list`, `git diff --check`) تُنفّذ خارج الوكيل بواسطة المستخدم، حسب تعليمات المهمة.
