# YM-DASH-003 — Connect Admin Dashboard to Overview API Spec

## 1. المرجعية

- `PROJECT_MAP.md` هو المرجع الأعلى للمشروع.
- المرحلة المرتبطة: المرحلة 2 — Dashboard Core المتقدم.
- آخر نقطة مستقرة: `76a6502 feat: add dashboard overview API contract`.
- يعتمد هذا العمل على `YM-DASH-002` الذي أضاف endpoint:
  - `GET /api/dashboard/overview`

## 2. المشكلة

صفحة `/admin` أصبحت مستقرة بصريًا، لكنها ما زالت تعتمد بدرجة كبيرة على بيانات static/demo داخل:

```text
frontend/pages/admin/index.vue
```

المطلوب الآن هو بدء تحويل `/admin` تدريجيًا من static dashboard إلى data-driven dashboard باستخدام عقد API الجديد، بدون كسر التصميم الحالي.

## 3. الهدف

ربط صفحة `/admin` بـ:

```http
GET /api/dashboard/overview
```

بحيث تبدأ البطاقات والأقسام والنشاطات والرسم البياني في استخدام بيانات backend contract بدل الاعتماد الكامل على static arrays.

## 4. نطاق التنفيذ المقترح

Frontend فقط، وبأضيق نطاق ممكن:

- `frontend/pages/admin/index.vue`
- عند الحاجة فقط:
  - استخدام `frontend/composables/useApiClient.ts`

لا يتم تعديل مكونات dashboard المشتركة إلا إذا ظهر blocker واضح، ويجب توثيقه قبل التنفيذ.

## 5. خارج النطاق

- لا تعديل `/staff`.
- لا تعديل `/client`.
- لا تعديل `/designer`.
- لا تعديل backend.
- لا تعديل endpoint `/api/dashboard/overview`.
- لا migrations.
- لا models.
- لا UI polish.
- لا إعادة تصميم.
- لا حذف كامل للبيانات static قبل وجود fallback آمن.
- لا تغيير AppTopBar أو Sidebar أو Layouts.

## 6. استراتيجية الربط الآمنة

يجب أن يكون الربط تدريجيًا وآمنًا:

1. قراءة overview من API عند تحميل `/admin`.
2. الاحتفاظ ببيانات static الحالية كـ fallback مؤقت إذا فشل الطلب.
3. تحويل cards أولًا لاستخدام `overview.cards` إذا كانت متاحة.
4. تحويل sections/filter لاستخدام `overview.sections` إذا كانت متاحة.
5. تحويل chart لاستخدام `overview.charts` إذا كانت متاحة.
6. تحويل activity feed لاستخدام `overview.activities` إذا كانت متاحة.
7. إضافة حالات loading/error/empty بشكل محدود وغير مؤثر بصريًا.

## 7. قواعد UX

- لا يجب أن يلاحظ المستخدم كسرًا بصريًا.
- في حالة فشل API، تبقى لوحة التحكم قابلة للعرض باستخدام fallback.
- لا يتم تغيير ألوان البطاقات أو توزيعها إلا عند الضرورة.
- لا يتم إعادة فتح mobile/tablet polish.
- لا يتم حذف أي تجربة مرئية مستقرة من المراحل السابقة.
- لا يوجد horizontal overflow جديد.
- Light/Dark يجب أن يبقيا مستقرين.

## 8. Contract المتوقع من API

الصفحة تتوقع من backend شكلًا قريبًا من:

```json
{
  "success": true,
  "data": {
    "role": "admin",
    "period": "month",
    "sections": [],
    "cards": [],
    "charts": [],
    "activities": []
  },
  "meta": {
    "periods": ["day", "week", "month", "year"],
    "selected_period": "month"
  }
}
```

## 9. Period Handling

إذا كانت الصفحة تحتوي على period filter، يجب ربطه تدريجيًا بـ query:

```http
/api/dashboard/overview?period=month
```

المراحل المقبولة:

- في أول تنفيذ، يمكن البدء بـ `period=month`.
- أو ربط period filter الموجود إذا كان ذلك لا يكسر الصفحة.
- يجب عدم إعادة تصميم الفلتر.

## 10. Error / Loading / Empty States

يجب إضافة حالات بسيطة:

- loading أثناء جلب البيانات.
- error غير مزعج إذا فشل الطلب.
- empty fallback إذا رجعت arrays فارغة.

لا يتم بناء نظام alerts جديد.

## 11. معايير القبول

- `/admin` يطلب `/api/dashboard/overview`.
- الصفحة لا تنكسر إذا فشل الطلب.
- cards تستخدم API data عند توفرها.
- sections تستخدم API data عند توفرها.
- charts تستخدم API data عند توفرها أو fallback آمن.
- activities تستخدم API data عند توفرها أو fallback آمن.
- لا تعديل backend.
- لا تعديل `/staff`.
- build ناجح.
- الفحص البصري على desktop/mobile/tablet جيد.
