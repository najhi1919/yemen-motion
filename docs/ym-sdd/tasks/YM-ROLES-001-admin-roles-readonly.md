# YM-ROLES-001 — Task

## المهمة

إنشاء صفحة `/admin/roles` للقراءة فقط + endpoint `GET /api/admin/roles`.

## الملفات المسموح تعديلها / إنشاؤها

```text
routes/api.php
app/Http/Controllers/Api/Admin/RoleController.php
frontend/pages/admin/roles/index.vue
docs/ym-sdd/specs/YM-ROLES-001-admin-roles-readonly.spec.md
docs/ym-sdd/tasks/YM-ROLES-001-admin-roles-readonly.md
```

## خطوات التنفيذ

1. [x] قراءة `routes/api.php`.
2. [x] إنشاء `app/Http/Controllers/Api/Admin/RoleController.php`.
3. [x] إضافة route:

   * `GET /api/admin/roles`
4. [x] حماية route بـ `auth:sanctum`.
5. [x] داخل controller السماح فقط لمن لديه role `admin`.
6. [x] إرجاع roles read-only مع:

   * `id`
   * `name`
   * `guard_name`
   * `users_count`
   * `permissions_count`
   * `created_at`
7. [x] إنشاء صفحة `frontend/pages/admin/roles/index.vue`.
8. [x] استخدام `useApiClient()` بدون تعديل ملفه.
9. [x] عرض loading / error / empty states.
10. [x] عرض جدول read-only للأدوار.
11. [x] عدم إضافة create / edit / delete.
12. [x] عدم تغيير permissions.
13. [x] كتابة تقرير نهائي.

## ملاحظة

أوامر التحقق اليدوية (`npm run build`, `php artisan route:list`, `git diff --check`) تُنفّذ خارج الوكيل بواسطة المستخدم، حسب تعليمات المهمة.

## الحالة

* مكتمل.
