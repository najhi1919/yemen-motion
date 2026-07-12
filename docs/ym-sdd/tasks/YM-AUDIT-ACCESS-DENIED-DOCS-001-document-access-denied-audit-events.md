# YM-AUDIT-ACCESS-DENIED-DOCS-001 — Access Denied Audit Events

## Scope

توثيق baseline تسجيل محاولات الوصول المرفوضة إلى مسارات API الداخلية المحددة دون تغيير أي منطق برمجي. لا يشمل هذا الإنجاز Reports أو Analytics أو export أو تعديل سجلات Audit أو حذفها.

## Implemented behavior

- ينفذ middleware المركزي `App\Http\Middleware\RecordAccessDeniedAuditEvent` التسجيل دون تغيير استجابة الرفض الأصلية.
- يسجل حالتي `401` و`403` بالهوية التالية: `event_type = access.denied`, و`category = access_control`, و`severity = warning`.
- يسجل `action = access_denied` و`outcome = denied`.

## Tracked internal paths

- `/api/admin` و`/api/admin/*`.
- `/api/dashboard` و`/api/dashboard/*`.
- `/api/audit` و`/api/audit/*`.
- `/api/user`.
- `/api/auth/logout`.

## Excluded paths

- `/api/auth/login`.
- `/api/auth/register`.
- `/api/auth/forgot-password`.
- `/api/auth/reset-password`.
- health/public endpoints.

## Safe metadata

- `method`.
- `path` فقط دون query string.
- `status`.
- `route_name` عند توفره.
- `source = access_denied_tracking`.

## Security constraints

- لا يحفظ full URL أو query string أو referrer.
- لا يحفظ payload أو request كاملًا أو headers أو Authorization.
- لا يحفظ cookies أو tokens أو passwords أو email أو name.
- يمنع تكرار الحدث للطلب نفسه عبر request attribute داخلي.

## Verification baseline

- الاختبار الكامل بعد التنفيذ: `207 passed / 1306 assertions`.
- Latest commit expected before this docs step: `2004071`.
