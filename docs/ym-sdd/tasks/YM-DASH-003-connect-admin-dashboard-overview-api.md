# YM-DASH-003 — Connect Admin Dashboard to Overview API Task

## 1. الهدف

ربط صفحة `/admin` بعقد:

```http
GET /api/dashboard/overview
```

بشكل تدريجي وآمن، مع الحفاظ على التصميم الحالي والفallback.

## 2. سبب المهمة

بعد تنفيذ `YM-DASH-002` أصبح لدى backend endpoint موحد للـ dashboard overview.
الخطوة التالية هي جعل `/admin` تبدأ في استهلاك هذا العقد بدل الاعتماد الكامل على static/demo data.

## 3. الملفات المسموح تعديلها عند التنفيذ لاحقًا

المسموح مبدئيًا:

- `frontend/pages/admin/index.vue`

مسموح عند الحاجة فقط:

- `frontend/composables/useApiClient.ts`

يجب عدم تعديل أي component مشترك إلا إذا ظهر blocker واضح ويُراجع قبل التنفيذ.

## 4. الملفات الممنوع لمسها عند التنفيذ لاحقًا

- `frontend/pages/staff/index.vue`
- `frontend/pages/client/index.vue`
- `frontend/pages/designer/index.vue`
- `frontend/components/AppTopBar.vue`
- `frontend/components/AppSidebar.vue`
- `frontend/layouts/`
- `frontend/components/dashboard/`
- `app/`
- `routes/`
- `tests/`
- `database/`
- `PROJECT_MAP.md`
- `docs/`
- `.env` و `.env.*`
- dependency files

## 5. خطوات التنفيذ المقترحة لاحقًا

1. فحص `frontend/pages/admin/index.vue` وتحديد مصادر static data الحالية.
2. إضافة state للـ overview:
   - loading
   - error
   - data
3. استخدام `useApiClient` أو النمط المعتمد في المشروع لجلب:
   - `/api/dashboard/overview?period=month`
4. تحويل cards computed لاستخدام `overview.cards` عند توفرها.
5. تحويل sections/filter لاستخدام `overview.sections` عند توفرها.
6. تحويل chart data لاستخدام `overview.charts` عند توفرها.
7. تحويل activity feed لاستخدام `overview.activities` عند توفرها.
8. الإبقاء على static data كـ fallback مؤقت.
9. تنفيذ build.
10. تنفيذ فحص بصري سريع.

## 6. اختبارات/تحققات مطلوبة عند التنفيذ لاحقًا

- `npm run build`
- `git --no-pager diff --check`
- التأكد أن الملفات المعدلة ضمن النطاق.
- التأكد أن `/admin` لا ينكسر بدون API response.
- التأكد أن `/admin` يعرض API data عند نجاح الطلب.
- التأكد أن Light/Dark مستقران.
- التأكد أن mobile/tablet لم يتأثرا.

## 7. خارج النطاق

- لا backend changes.
- لا tests backend.
- لا migrations.
- لا real activity model.
- لا staff wiring.
- لا client/designer wiring.
- لا UI polish عام.
- لا إعادة تصميم.

## 8. أوامر التحقق عند التنفيذ لاحقًا

```bash
cd /home/kali/projects/yemen-motion

git --no-pager status --short
git --no-pager diff --name-status
git --no-pager diff --stat
git --no-pager diff --check

cd frontend && npm run build
```

## 9. ملاحظات

هذه المهمة يجب أن تحافظ على واجهة `/admin` المستقرة بصريًا.
الهدف هو التحويل التدريجي إلى data-driven dashboard، وليس تحسين الشكل.
