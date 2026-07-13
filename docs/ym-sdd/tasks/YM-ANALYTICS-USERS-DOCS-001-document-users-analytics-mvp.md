# YM-ANALYTICS-USERS-DOCS-001 — Users Analytics MVP

## Scope

توثيق أول Users Analytics MVP فعلي من الخادم والواجهة دون تغيير أي منطق برمجي.

## Backend API

- endpoint: `GET /api/admin/analytics/users`.
- الوصول محصور على `super-admin`؛ وتمنع أدوار `admin` و`staff` و`client` و`designer` حتى مع صلاحيات عرضية.
- التحليلات Aggregated فقط، وتدعم فلاتر `from`, و`to`, و`role`, و`period`.
- يدعم `period`: `day`, و`week`, و`month`, و`year`.
- تعيد الاستجابة `summary`, و`trend`, و`role_mix`, و`comparison`, و`filters`, و`generated_at`.
- يعرض `summary`: `current_period_users`, و`previous_period_users`, و`absolute_change`, و`percentage_change`, و`verified_rate`, و`unverified_rate`.

## Frontend page

- المسار: `/admin/analytics`.
- تستخدم الصفحة API الحقيقي وتعرض summary cards وcomparison block وtrend مع `cumulative_count` و`role_mix`.
- تغطي حالات auth pending وloading وerror وempty وforbidden وsuccess.
- تنتظر `authStore.isInitialized` قبل طلب البيانات، وتستخدم HTML وCSS دون مكتبة charts خارجية.

## Comparison behavior

- عند غياب `from` و`to` يكون المدى الحالي آخر 30 يومًا، ويكون المدى السابق الثلاثين يومًا التي تسبقه مباشرة.
- يساوي المدى السابق المدى الحالي في عدد الأيام ويسبقه مباشرة.
- تعيد `percentage_change` القيمة `null` عندما يكون السابق صفرًا والحالي موجبًا، والقيمة `0` عندما يكون كلاهما صفرًا.

## Security constraints

- لا تعرض التحليلات قائمة مستخدمين أو بيانات شخصية.
- لا تعرض `email` أو `name` أو `password` أو `remember_token` أو `token` أو `cookie`.
- يبقى الخادم حارس التفويض النهائي، ولا تكفي الصلاحيات العرضية للوصول دون دور `super-admin`.

## Data boundaries

- لا يعيد API user models أو relations أو raw pivot data.
- لا توجد عمليات تعديل أو حذف عبر صفحة التحليلات، ولا يوجد Export.

## Future work

- هذا الإنجاز بداية Analytics فقط.
- تحليلات الطلبات والمالية والأعمال وExport ما زالت أعمالًا مستقبلية غير جاهزة.
- لم تضف مكتبة charts خارجية.

Latest commit expected before this docs step: `817095f`.
