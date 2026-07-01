# YM-DASH-004 — Connect Staff Dashboard to Overview API

## اسم المهمة

`YM-DASH-004 — Connect Staff Dashboard to Overview API`

## المشكلة

- صفحة `/staff` ما زالت تستخدم بيانات ثابتة (hardcoded) لبطاقات KPI والنشاطات.
- صفحة `/admin` أصبحت مربوطة بـ dashboard overview API (`GET /api/dashboard/overview`).
- صفحة `/staff` متخلفة عن `/admin` ولا تستفيد من البيانات الحية القادمة من الـ API.

## الهدف

- ربط صفحة `/staff` بـ `/api/dashboard/overview?period=month`.
- استخدام بيانات `cards` و `activities` القادمة من API عند توفرها.
- الاحتفاظ بالبيانات التجريبية الحالية كـ fallback عند فشل الاتصال.

## النطاق

- Frontend فقط.
- Staff page فقط (`frontend/pages/staff/index.vue`).

## خارج النطاق

- Backend.
- Auth.
- Routes (`routes/api.php`).
- Components مشتركة (`frontend/components/`).
- Layouts.
- UI polish أو إعادة تصميم.
- Tests.
- Database.

## الملفات المسموح تعديلها

```text
frontend/pages/staff/index.vue
docs/ym-sdd/specs/YM-DASH-004-connect-staff-dashboard-overview-api.spec.md
docs/ym-sdd/tasks/YM-DASH-004-connect-staff-dashboard-overview-api.md
```

## معايير القبول

- [x] استدعاء API عند فتح `/staff`.
- [x] استخدام API cards عند توفرها.
- [x] استخدام API activities عند توفرها.
- [x] fallback يعمل عند فشل API.
- [x] `npm run build` ينجح.
- [x] `git diff --check` نظيف.
- [x] لا يوجد تعديل خارج الملفات المسموحة.

## تفاصيل تقنية

### آلية الربط

- استخدام `useApiClient()` الموجود في المشروع بدون تعديل ملفه.
- استدعاء `apiFetch<DashboardOverviewResponse>('/dashboard/overview', { query: { period: 'month' } })`.
- استدعاء `fetchDashboardOverview()` داخل `onMounted`.

### استراتيجية fallback

- `visibleKpis`: يرجع إلى `fallbackKpis` عند عدم توفر `cards`.
- `visibleActivities`: يرجع إلى `fallbackActivities` عند عدم توفر `activities`.
- `overviewStatusMessage`: يعرض حالة التحميل/الخطأ داخل prototype notice.
