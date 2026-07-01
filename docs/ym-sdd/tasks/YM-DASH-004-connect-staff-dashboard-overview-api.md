# YM-DASH-004 — Task

## المهمة

ربط صفحة `/staff` بـ `/api/dashboard/overview?period=month`.

## الملفات المسموح تعديلها

```text
frontend/pages/staff/index.vue
docs/ym-sdd/specs/YM-DASH-004-connect-staff-dashboard-overview-api.spec.md
docs/ym-sdd/tasks/YM-DASH-004-connect-staff-dashboard-overview-api.md
```

## خطوات التنفيذ

1. [x] قراءة `frontend/pages/staff/index.vue`.
2. [x] إضافة types محلية صغيرة للـ overview response.
3. [x] إضافة state:
   - `dashboardOverview`
   - `dashboardOverviewLoading`
   - `dashboardOverviewError`
   - `overviewPeriod`
4. [x] تحويل `kpis` إلى `fallbackKpis`.
5. [x] تحويل `activities` إلى `fallbackActivities`.
6. [x] إضافة computed:
   - `dashboardOverviewCards`
   - `visibleKpis`
   - `dashboardOverviewActivities`
   - `visibleActivities`
   - `overviewStatusMessage`
7. [x] إضافة `fetchDashboardOverview()`.
8. [x] استدعاء `fetchDashboardOverview()` داخل `onMounted`.
9. [x] تعديل template لاستخدام:
   - `visibleKpis`
   - `visibleActivities`
   - `overviewStatusMessage`
10. [x] تشغيل:
    - `git diff --check` (نظيف)
    - `npm run build` (نجح)
11. [x] كتابة تقرير نهائي.

## الحالة

- مكتمل.
