# YM-AUDIT-EVENTS-QUICK-FILTERS-DOCS-001 — Audit Events Quick Filters

## Scope

توثيق تحسين صفحة سجل التدقيق باختصارات جاهزة للأحداث المهمة، دون تغيير أي منطق برمجي في خطوة التوثيق.

## Frontend change

- الصفحة: `/admin/audit-events`.
- الملف: `frontend/pages/admin/audit-events/index.vue`.
- كان التغيير Frontend فقط، وبقي Backend API والتفويض دون تغيير.

## Quick filters

- كل السجلات.
- تقارير المستخدمين: `event_type = reports.users.generated`, و`category = reports`, و`outcome = success`.
- تحليلات المستخدمين: `event_type = analytics.users.generated`, و`category = analytics`, و`outcome = success`.
- زيارات صفحات الإدارة: `event_type = admin.page.viewed`, و`category = page_view`, و`outcome = success`.
- الوصول المرفوض: `event_type = access.denied`, و`category = access_control`, و`outcome = denied`.

## Behavior

- تطبق الاختصارات الفلاتر مباشرة وتعود إلى الصفحة الأولى.
- تحافظ على `per_page` وتمسح القيود اليدوية الأخرى عند اختيار اختصار.
- تظهر الشريحة النشطة بصريًا، ويعيد زر إعادة الضبط الصفحة إلى كل السجلات.
- لا يرسل `action` كفلتر لأن API القراءة لا يدعمه حاليًا.

## Security and read-only boundaries

- بقيت الصفحة قراءة فقط دون تعديل أو حذف.
- لم يضف Export أو `v-html` أو console logs.
- لم تتغير صلاحيات الوصول أو شكل استجابة API.

## Verification

- نجح `npm run build`.
- `AuditEventsReadApiTest`: `17 tests / 70 assertions`.
- `InsightsGeneratedAuditEventsTest`: `4 tests / 93 assertions`.
- نجح الفحص البصري الأولي للشرائح.
- لا يوجد commit إضافي للفحص البصري.

## Future notes

- المراجعة البصرية الشاملة مؤجلة حتى اكتمال بناء بقية الصفحات والأقسام.
- التحسينات البصرية العميقة للجدول والكثافة والتجاوب ستنفذ في مرحلة UI review منفصلة لاحقًا.

Latest commit expected before this docs step: `594dbce`.
