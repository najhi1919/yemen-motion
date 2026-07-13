# YM-INSIGHTS-AUDIT-GENERATED-DOCS-001 — Insights Generated Audit Events

## Scope

توثيق تسجيل أحداث Audit آمنة عند نجاح إنشاء تقرير المستخدمين وتحليلات المستخدمين، دون تغيير أي منطق برمجي.

## Events

- يسجل `reports.users.generated` بعد بناء بيانات التقرير بنجاح وقبل إرجاع الاستجابة، بقيم `category = reports`, و`severity = info`, و`target_type = report`, و`target_id = null` لأن الحقل رقمي، و`action = generate`, و`outcome = success`.
- يسجل `analytics.users.generated` بعد بناء بيانات التحليلات بنجاح وقبل إرجاع الاستجابة، بقيم `category = analytics`, و`severity = info`, و`target_type = analytics`, و`target_id = null` لأن الحقل رقمي، و`action = generate`, و`outcome = success`.
- تبقى رفضيات `401/403` ضمن access denied middleware، ولا تسجل أخطاء validation بحالة `422` generated events.

## Reports metadata

- `source`.
- `period`.
- `has_from_filter`, و`has_to_filter`, و`has_role_filter`.
- `users_in_range`.
- `role_breakdown_count`.
- `series_points_count`.

## Analytics metadata

- `source`.
- `period`.
- `has_from_filter`, و`has_to_filter`, و`has_role_filter`.
- `current_period_users`, و`previous_period_users`, و`absolute_change`.
- `percentage_change_available`.
- `trend_points_count`, و`role_mix_count`.

## Security boundaries

- لا تحفظ قيم `from` أو `to` أو `role` الخام، ولا query string أو full URL.
- لا تحفظ raw request أو payload، ولا `email` أو `name` أو `password` أو `token` أو `cookie`.
- لا تحفظ user list أو user models أو relations أو raw pivot data.

## Verification

- التنفيذ: `app/Http/Controllers/Api/Admin/Reports/UserReportController.php`.
- التنفيذ: `app/Http/Controllers/Api/Admin/Analytics/UserAnalyticsController.php`.
- الاختبار: `tests/Feature/Admin/InsightsGeneratedAuditEventsTest.php`.
- `InsightsGeneratedAuditEventsTest`: `4 tests / 93 assertions`.
- Full suite: `241 passed / 1623 assertions`.

## Future notes

- لا يضيف هذا baseline Export.
- تحليلات الطلبات والمالية والأعمال ما زالت أعمالًا مستقبلية غير جاهزة.

Latest commit expected before this docs step: `9cfc604`.
