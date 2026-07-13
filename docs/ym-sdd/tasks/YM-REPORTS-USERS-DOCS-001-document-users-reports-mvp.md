# YM-REPORTS-USERS-DOCS-001 — Users Reports MVP

## Scope

توثيق أول Reports MVP فعلي للمستخدمين من الخادم والواجهة دون تغيير أي منطق برمجي.

## Backend API

- endpoint: `GET /api/admin/reports/users`.
- الوصول محصور على `super-admin`؛ وتمنع أدوار `admin` و`staff` و`client` و`designer` حتى مع صلاحيات عرضية.
- التقرير Aggregated فقط، ويدعم فلاتر `from`, و`to`, و`role`, و`period`.
- يدعم `period`: `day`, و`week`, و`month`, و`year`.
- تعيد الاستجابة `summary`, و`role_breakdown`, و`registrations_series`, و`filters`, و`generated_at`.

## Frontend page

- المسار: `/admin/reports`.
- تستخدم الصفحة API الحقيقي وتعرض summary cards وتوزيع الأدوار وسلسلة التسجيلات.
- تغطي حالات auth pending وloading وerror وempty وforbidden وsuccess.
- تنتظر `authStore.isInitialized` قبل طلب البيانات، وتستخدم HTML وCSS دون مكتبة charts خارجية.

## Security constraints

- لا يعرض التقرير قائمة مستخدمين أو بيانات شخصية.
- لا يعرض `email` أو `name` أو `password` أو `remember_token` أو `token` أو `cookie`.
- يبقى الخادم حارس التفويض النهائي، ولا تكفي الصلاحيات العرضية للوصول دون دور `super-admin`.

## Data boundaries

- لا يعيد API user models أو relations أو raw pivot data.
- تقتصر الصفحة على المؤشرات التجميعية والحقول المعتمدة في استجابة التقرير.
- لا توجد عمليات تعديل أو حذف عبر صفحة التقارير.

## Future work

- هذا الإنجاز بداية Reports فقط.
- Analytics وExport وتقارير الطلبات والمالية والأعمال ما زالت أعمالًا مستقبلية غير جاهزة.

Latest commit expected before this docs step: `1eec9df`.
