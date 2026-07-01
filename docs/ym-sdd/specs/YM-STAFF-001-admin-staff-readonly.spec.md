# YM-STAFF-001 — Admin Staff Read-only Foundation

## اسم المهمة

`YM-STAFF-001 — Admin Staff Read-only Foundation`

## المشكلة

- `/admin/staff` حاليًا يذهب إلى صفحة تحت البناء (no real listing).
- لدينا بالفعل `/admin/users` و endpoint `GET /api/admin/users` الذي يدعم فلترة الدور عبر query param `role`.

## الهدف

- إنشاء صفحة `/admin/staff` للقراءة فقط.
- عرض المستخدمين الذين لديهم `role=staff` فقط، بالاعتماد على endpoint الموجود `/admin/users?role=staff`.

## النطاق

- Frontend: صفحة واحدة فقط (`frontend/pages/admin/staff/index.vue`).
- Read-only فقط.
- استخدام endpoint الموجود `/admin/users` مع `role=staff`.

## خارج النطاق

- Backend جديد.
- routes.
- controllers.
- إنشاء موظف.
- تعديل موظف.
- حذف موظف.
- تغيير role.
- تغيير permissions.
- Staff dashboard `/staff`.
- Sidebar / AppTopBar.
- Auth flow.
- models / migrations / seeders.

## الملفات المسموحة

```text
frontend/pages/admin/staff/index.vue
docs/ym-sdd/specs/YM-STAFF-001-admin-staff-readonly.spec.md
docs/ym-sdd/tasks/YM-STAFF-001-admin-staff-readonly.md
```

## معايير القبول

- [x] `/admin/staff` تعرض صفحة حقيقية بدل under construction.
- [x] الصفحة تطلب `/admin/users` مع `role=staff`.
- [x] الجدول يعرض الموظفين فقط.
- [x] الصفحة تحتوي loading / error / empty states.
- [x] لا توجد أزرار تعديل أو حذف أو إنشاء.
- [x] لا يوجد تعديل خارج الملفات المسموحة.

## تفاصيل تقنية

### آلية الربط

- استخدام `useApiClient()` الموجود في المشروع بدون تعديل ملفه.
- استدعاء `apiFetch<AdminStaffResponse>('/admin/users', { query: { role: 'staff', page, per_page, search } })`.
- لا فلتر role في الـ UI لأن الصفحة مخصصة للموظفين فقط.
- استدعاء `fetchStaff()` داخل `onMounted` + watcher على `search`.

### الاستراتيجية

- Pagination prev/next (لا role filter).
- loading / error / empty states.
- جدول read-only بدون أي أزرار create/edit/delete.

### ملاحظة

أوامر التحقق اليدوية (`npm run build`, `route:list`, `git diff --check`) تُنفّذ خارج الوكيل بواسطة المستخدم، حسب تعليمات المهمة.
