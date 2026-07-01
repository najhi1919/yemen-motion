# YM-STAFF-001 — Task

## المهمة

إنشاء صفحة `/admin/staff` للقراءة فقط بالاعتماد على endpoint الموجود `/admin/users?role=staff`.

## الملفات المسموح إنشاؤها

```text
frontend/pages/admin/staff/index.vue
docs/ym-sdd/specs/YM-STAFF-001-admin-staff-readonly.spec.md
docs/ym-sdd/tasks/YM-STAFF-001-admin-staff-readonly.md
```

## خطوات التنفيذ

1. [x] قراءة صفحة `/admin/users` الحالية (لاستئناس النمط دون تعديلها).
2. [x] إنشاء صفحة `frontend/pages/admin/staff/index.vue`.
3. [x] استخدام `useApiClient()` بدون تعديل ملفه.
4. [x] استدعاء `/admin/users` مع query:
   - `role: 'staff'`
   - `page`
   - `per_page`
   - `search`
5. [x] عرض loading / error / empty states.
6. [x] عرض جدول read-only للموظفين.
7. [x] عدم إضافة create / edit / delete.
8. [x] كتابة تقرير نهائي.

## ملاحظة

أوامر التحقق اليدوية (`npm run build`, `php artisan route:list`, `git diff --check`) تُنفّذ خارج الوكيل بواسطة المستخدم، حسب تعليمات المهمة.

## الحالة

- مكتمل.
