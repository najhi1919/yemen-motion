# YM-AUDIT-LOGS-READ-DOCS-001 — Audit Logs Read Access

## Scope

توثيق baseline قراءة سجلات Audit من الخادم والواجهة دون تغيير أي منطق برمجي.

## Backend read API

- endpoint: `GET /api/admin/audit-events`.
- القراءة محصورة على `super-admin`، وتمنع `admin` و`staff` و`client` و`designer`.
- يدعم pagination وفلاتر allowlist: `event_type`, `category`, `severity`, `outcome`, `actor_id`, `target_type`, `target_id`, `from`, `to`, `per_page`, `page`.
- يرفض معاملات البحث غير المعتمدة، ومنها `email`, `name`, `payload`, `request`, `token`, `password`, `cookie`, و`metadata`.
- يعيد حقول `audit_events` المعتمدة فقط دون علاقات users أو models كاملة.

## Frontend read-only page

- المسار: `/admin/audit-events`.
- تعرض الصفحة حالات loading وerror وempty وforbidden وsuccess، مع فلاتر وترقيم مرتبطين بالـAPI.
- تعرض metadata كنص JSON فقط داخل عنصر قابل للتوسيع، وليس كـHTML.
- يظهر رابط Sidebar للمدير الأعلى فقط.

## Security constraints

- لا تحاول الواجهة تحميل البيانات عندما لا يكون الدور `super-admin`.
- يبقى الخادم حارس التفويض النهائي.
- لا توجد فلاتر للبيانات الحساسة أو بحث حر داخل metadata.
- لا تعرض الاستجابة علاقات مستخدم أو models كاملة.

## Boundaries / Future work

Reports وAnalytics وexport ما زالت أعمالًا مستقبلية. لا توجد عمليات update أو delete لسجلات Audit ضمن هذا baseline.

Latest commit expected before this docs step: `19124a8`.
