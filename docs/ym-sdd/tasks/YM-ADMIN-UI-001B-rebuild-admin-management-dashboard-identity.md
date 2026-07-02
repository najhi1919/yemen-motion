# YM-ADMIN-UI-001B — Task

## المهمة

إعادة بناء صفحات الإدارة الفرعية حول هوية لوحة التحكم الرئيسية `/admin`، مع حذف حقول البحث الداخلية من users/staff.

## المرجع البصري

```text
frontend/pages/admin/index.vue
```

## الملفات المسموح تعديلها / إنشاؤها

```text
frontend/pages/admin/users/index.vue
frontend/pages/admin/staff/index.vue
frontend/pages/admin/roles/index.vue
docs/ym-sdd/specs/YM-ADMIN-UI-001B-rebuild-admin-management-dashboard-identity.spec.md
docs/ym-sdd/tasks/YM-ADMIN-UI-001B-rebuild-admin-management-dashboard-identity.md
```

## ملفات قراءة فقط

```text
frontend/pages/admin/index.vue
frontend/components/AppTopBar.vue
frontend/components/DashboardMetricCard.vue
frontend/components/DashboardActivityFeed.vue
frontend/components/DashboardPeriodFilter.vue
frontend/components/DashboardSectionFilter.vue
frontend/components/DashboardSvgChart.vue
frontend/components/DashboardViewToggle.vue
frontend/components/AppSidebar.vue
```

## خطوات التنفيذ

1. [x] قراءة `/admin` لفهم hero/cards/control-panel/states.
2. [x] قراءة `AppTopBar` للتأكد من وجود البحث العام.
3. [x] قراءة `/admin/users`.
4. [x] قراءة `/admin/staff`.
5. [x] قراءة `/admin/roles`.
6. [x] حذف search الداخلي من users.
7. [x] حذف search الداخلي من staff.
8. [x] إزالة `search` state/watchers/query params غير المستخدمة.
9. [x] إبقاء role filter في users فقط.
10. [x] إعادة بناء users حول dashboard-like hero + summary cards + table card.
11. [x] إعادة بناء staff حول dashboard-like hero + summary cards + table card.
12. [x] إعادة بناء roles حول dashboard-like hero + summary cards + table card.
13. [x] الحفاظ على نفس API calls الأساسية.
14. [x] الحفاظ على loading/error/empty states.
15. [x] عدم إضافة create/edit/delete.
16. [x] عدم تعديل Backend أو routes أو Auth.
17. [x] كتابة تقرير نهائي.

## الحالة

* مكتمل.
