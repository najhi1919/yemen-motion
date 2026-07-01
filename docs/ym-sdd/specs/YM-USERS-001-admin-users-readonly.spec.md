# YM-USERS-001 — Admin Users Read-only Foundation

## اسم المهمة

`YM-USERS-001 — Admin Users Read-only Foundation`

## المشكلة

- `/admin/users` حاليًا يذهب إلى صفحة تحت البناء (no real listing).
- لا يوجد API إداري لعرض المستخدمين الموجودين في النظام.

## الهدف

- إنشاء endpoint قراءة فقط: `GET /api/admin/users`.
- إنشاء صفحة `/admin/users` لعرض جدول المستخدمين (read-only).

## النطاق

- Backend: `GET` فقط.
- Frontend: صفحة واحدة فقط (`frontend/pages/admin/users/index.vue`).
- Read-only فقط.

## خارج النطاق

- إنشاء مستخدم.
- تعديل مستخدم.
- حذف مستخدم.
- تغيير role.
- تغيير permissions.
- Staff management.
- Roles UI.
- Auth flow.
- migrations / seeders.
- User model.

## الملفات المسموحة

```text
routes/api.php
app/Http/Controllers/Api/Admin/UserController.php
frontend/pages/admin/users/index.vue
docs/ym-sdd/specs/YM-USERS-001-admin-users-readonly.spec.md
docs/ym-sdd/tasks/YM-USERS-001-admin-users-readonly.md
```

## معايير القبول

- [x] `/api/admin/users` يعمل للمستخدم admin فقط.
- [x] غير المصادق يحصل على 401 (عبر middleware `auth:sanctum`).
- [x] المستخدم غير admin يحصل على 403 (داخل controller).
- [x] `/admin/users` تعرض جدولًا.
- [x] الصفحة تحتوي loading / error / empty states.
- [x] لا توجد أزرار تعديل أو حذف أو إنشاء.
- [x] `npm run build` ينجح.
- [x] `php artisan route:list --path=api/admin/users` يظهر route.
- [x] `git diff --check` نظيف.
- [x] لا توجد تعديلات خارج النطاق.

## تفاصيل تقنية

### الاستجابة

- الـ endpoint يرجع paginated users مع: `id`, `name`, `email`, `roles`, `created_at`.
- لا يرجع `password` أو `remember_token` أو أي أسرار.
- يدعم query params: `page`, `per_page` (1..50), `search` (name/email), `role`.

### الحماية

- Route محمي بـ `auth:sanctum` (401 لغير المصادق).
- داخل controller يتم التحقق من `hasRole('admin')` (403 لغير الأدمن).
