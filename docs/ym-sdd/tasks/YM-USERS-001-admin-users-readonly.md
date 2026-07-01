# YM-USERS-001 — Task

## المهمة

إنشاء صفحة `/admin/users` للقراءة فقط + endpoint `GET /api/admin/users`.

## الملفات المسموح تعديلها / إنشاؤها

```text
routes/api.php
app/Http/Controllers/Api/Admin/UserController.php
frontend/pages/admin/users/index.vue
docs/ym-sdd/specs/YM-USERS-001-admin-users-readonly.spec.md
docs/ym-sdd/tasks/YM-USERS-001-admin-users-readonly.md
```

## خطوات التنفيذ

1. [x] قراءة `routes/api.php`.
2. [x] إنشاء `app/Http/Controllers/Api/Admin/UserController.php`.
3. [x] إضافة route:
   - `GET /api/admin/users`
4. [x] حماية route بـ `auth:sanctum`.
5. [x] داخل controller السماح فقط لمن لديه role `admin`.
6. [x] إرجاع users paginated مع roles (بدون password/remember_token).
7. [x] إنشاء صفحة `frontend/pages/admin/users/index.vue`.
8. [x] استخدام `useApiClient()` بدون تعديل ملفه.
9. [x] عرض loading/error/empty states.
10. [x] عرض جدول read-only.
11. [x] تشغيل:
    - `php artisan route:list --path=api/admin/users`
    - `git diff --check`
    - `cd frontend && npm run build`
12. [x] كتابة تقرير نهائي.

## الحالة

- مكتمل.
