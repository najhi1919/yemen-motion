# YM-DASH-002 — Dashboard API Overview Contract Task

## 1. الهدف

تنفيذ أول contract backend صغير للمرحلة 2 Dashboard Core عبر endpoint:

`GET /api/dashboard/overview`

## 2. سبب المهمة

هذه المهمة هي أول خطوة تنفيذية بعد YM-DASH-001 Gap Audit، لأن الواجهة الحالية ثابتة وتحتاج عقد بيانات موحد قبل أي ربط frontend.

## 3. الملفات المسموح تعديلها عند التنفيذ لاحقًا

- `routes/api.php`
- `app/Http/Controllers/Api/DashboardController.php`
- `tests/Feature/DashboardOverviewTest.php` أو اسم اختبار مناسب

## 4. الملفات الممنوع لمسها عند التنفيذ لاحقًا

- `frontend/`
- `PROJECT_MAP.md`
- `docs/ym-sdd/memory/`
- `database/migrations/`
- `composer.json`
- `package.json`
- `.env` و `.env.*`

## 5. خطوات التنفيذ المقترحة لاحقًا

1. مراجعة `DashboardController` الحالي.
2. إضافة route محمي:
   `GET /api/dashboard/overview`
3. بناء response shape ثابت.
4. تحديد role من المستخدم المصادق.
5. بناء sections/cards/charts مبدئية حسب الدور.
6. دعم period query.
7. إضافة feature tests.
8. تشغيل الاختبارات المناسبة.
9. عدم تعديل frontend.

## 6. اختبارات مطلوبة

- unauthenticated request returns 401.
- authenticated admin gets valid overview JSON shape.
- authenticated staff gets valid overview JSON shape.
- staff response does not include admin-only sections.
- `period=day/week/month/year` reflected in response.
- invalid period handled consistently.

## 7. خارج النطاق

- لا migrations.
- لا Activity model.
- لا realtime.
- لا ربط frontend.
- لا UI polish.
- لا Users Management.

## 8. أوامر التحقق عند التنفيذ لاحقًا

```bash
cd /home/kali/projects/yemen-motion

php artisan test --filter=DashboardOverviewTest

git --no-pager diff --check
git --no-pager status --short
```

## 9. ملاحظات

Laravel موجود فعليًا في جذر المشروع حاليًا، وليس داخل `backend/`.
لا يتم تغيير الهيكلة في هذه المهمة.
