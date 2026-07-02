# YM-ADMIN-UI-001B — Rebuild Admin Management Pages Around Dashboard Identity

## اسم المهمة

`YM-ADMIN-UI-001B — Rebuild Admin Management Pages Around Dashboard Identity`

## المشكلة

- محاولة `YM-ADMIN-UI-001` السابقة فشلت بصريًا لأنها تعاملت مع الصفحات كجداول CRUD مسطحة.
- الصفحات الفرعية `/admin/users` و`/admin/staff` و`/admin/roles` يجب أن تكون امتدادًا بصريًا من صفحة لوحة التحكم الرئيسية `/admin`.
- يوجد حقل بحث عام في الشريط العلوي `AppTopBar`، لذلك لا يجب تكرار حقول البحث النصية داخل الصفحات الفرعية.

## الهدف

- إعادة بناء الواجهة البصرية للصفحات الثلاث حول هوية `/admin`.
- حذف search inputs الداخلية من users/staff.
- الحفاظ على الوظائف الحالية بدون إضافة عمليات حساسة.
- إبقاء الصفحات read-only.

## المرجع البصري الأساسي

```text
frontend/pages/admin/index.vue
```

مع قراءة `AppTopBar` فقط للتأكد من وجود البحث العام.

## الصفحات المستهدفة

```text
frontend/pages/admin/users/index.vue
frontend/pages/admin/staff/index.vue
frontend/pages/admin/roles/index.vue
```

## النطاق

* Frontend فقط.
* Vue template + script cleanup + CSS scoped داخل الصفحات الثلاث فقط.
* حذف search الداخلي من users/staff.
* إبقاء role filter في users فقط.
* إبقاء pagination في users/staff.
* إبقاء roles بدون search وبدون pagination.
* استخدام dashboard-like hero/cards/tables/states.
* الحفاظ على API endpoints الحالية.

## خارج النطاق

* Backend.
* routes.
* Controllers.
* Models.
* migrations / seeders.
* Auth flow.
* Sidebar / AppTopBar.
* تعديل صفحة `/admin` الرئيسية.
* تعديل مكونات Dashboard.
* إنشاء/تعديل/حذف users.
* إنشاء/تعديل/حذف staff.
* إنشاء/تعديل/حذف roles.
* تغيير permissions.
* إضافة مكتبات جديدة.
* تشغيل `npm install`.

## قواعد البحث

* ممنوع وجود search input داخل `/admin/users`.
* ممنوع وجود search input داخل `/admin/staff`.
* ممنوع إرسال `search` query param من هذه الصفحات.
* الاعتماد البصري والوظيفي على وجود البحث العام في `AppTopBar`.

## معايير القبول

* [x] `/admin/users` لا تحتوي search input داخلي.
* [x] `/admin/staff` لا تحتوي search input داخلي.
* [x] `/admin/roles` لا تحتوي search input.
* [x] `/admin/users` يحتفظ بفلتر الدور فقط.
* [x] `/admin/users` يحتفظ بالـ pagination.
* [x] `/admin/staff` يحتفظ بالـ pagination.
* [x] `/admin/roles` يعرض الأدوار فقط.
* [x] الصفحات الثلاث تستخدم hero/cards/tables/states قريبة بصريًا من `/admin`.
* [x] لا يوجد تعديل Backend.
* [x] لا يوجد تعديل routes.
* [x] لا يوجد تعديل Auth.
* [x] لا توجد أزرار create/edit/delete.
* [x] لا توجد عمليات تغيير صلاحيات.
* [x] لا يوجد تعديل خارج الملفات المسموحة.

## ملاحظات تنفيذية

* المرجع التصميمي هو `/admin`.
* لا تستخدم CRUD table style مسطح.
* لا تستخدم search داخلي.
* استخدم نفس روح لوحة التحكم: gradient hero, chips, summary panel, large-radius cards, soft shadows, table card, metric/summary cards.
* أوامر التحقق اليدوية (`npm run build`, `git diff --check`) تُنفّذ خارج الوكيل بواسطة المستخدم، حسب تعليمات المهمة.
