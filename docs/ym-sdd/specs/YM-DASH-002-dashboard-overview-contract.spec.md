# YM-DASH-002 — Dashboard API Overview Contract Spec

## 1. المرجعية

- `PROJECT_MAP.md` هو المرجع الأعلى.
- المرحلة المرتبطة: المرحلة 2 — Dashboard Core المتقدم.
- آخر نقطة مستقرة: `da552be`.
- نتيجة YM-DASH-001: الموجود الحالي Dashboard UI Foundation فقط، وليس Production Dashboard Core.

## 2. المشكلة

- صفحات `/admin` و `/staff` تعتمد على بيانات static/demo.
- Backend لديه routes أولية فقط: `/api/dashboard/stats` و `/api/dashboard/activity` و `/api/dashboard/chart`.
- لا يوجد endpoint موحد لـ `/api/dashboard/overview`.
- لا يوجد contract موحد يربط cards/charts/sections/activities حسب الدور والفترة.
- يوجد اختلاف بين `PROJECT_MAP.md` الذي يطلب `/activities` بصيغة الجمع والحالي `/activity` بصيغة المفرد.

## 3. الهدف

إضافة contract صغير لـ:

`GET /api/dashboard/overview`

يرجع JSON shape موحدًا ومصادقًا عليه يمكن أن تستخدمه الواجهات لاحقًا.

## 4. نطاق التنفيذ المقترح

Backend فقط:

- `routes/api.php`
- `app/Http/Controllers/Api/DashboardController.php`
- `tests/Feature/DashboardOverviewTest.php` أو اسم اختبار مناسب

## 5. خارج النطاق

- لا frontend wiring.
- لا تعديل `/admin` أو `/staff`.
- لا Activity model جديد.
- لا migrations.
- لا realtime.
- لا DashboardSection model.
- لا إعادة هيكلة Laravel إلى `backend/`.
- لا تغيير routes القديمة إلا إذا كان ضروريًا جدًا.
- لا UI polish.

## 6. Contract المقترح

Response shape مبدئي:

```json
{
  "success": true,
  "data": {
    "role": "admin",
    "period": "month",
    "sections": [
      {
        "key": "users",
        "label": {
          "ar": "المستخدمون",
          "en": "Users"
        },
        "icon": "users",
        "color": "#000000",
        "permission": null,
        "is_active": true
      }
    ],
    "cards": [
      {
        "key": "users",
        "label": {
          "ar": "المستخدمون",
          "en": "Users"
        },
        "value": 0,
        "change": 0,
        "trend": "neutral",
        "section": "users"
      }
    ],
    "charts": [
      {
        "key": "users",
        "type": "bar",
        "section": "users",
        "points": [
          {
            "label": "اليوم",
            "value": 0
          }
        ]
      }
    ],
    "activities": []
  },
  "message": "تم جلب ملخص لوحة التحكم",
  "errors": null,
  "meta": {
    "periods": ["day", "week", "month", "year"],
    "selected_period": "month"
  }
}
```

## 7. قواعد الصلاحيات

- Endpoint محمي بالمصادقة.
- unauthenticated يرجع 401.
- admin يحصل على admin-level sections.
- staff يحصل فقط على staff-safe sections.
- لا يتم تسريب admin-only sections للـ staff.
- لا يجب الاعتماد على frontend لإخفاء البيانات الحساسة.

## 8. قواعد period

- يدعم query parameter:
  - `?period=day`
  - `?period=week`
  - `?period=month`
  - `?period=year`
- default = `month`.
- قيمة غير معروفة يجب أن تعود validation error أو fallback واضح حسب نمط المشروع الحالي، ويجب توثيق السلوك في الاختبار.

## 9. مصدر البيانات في هذه المهمة

- يمكن استخدام بيانات حقيقية بسيطة متاحة حاليًا مثل users count.
- باقي الأقسام يمكن أن تكون صفرية/placeholder من backend contract فقط.
- يجب أن تكون placeholder واضحة ومولدة من backend، لا من frontend.
- لا migrations جديدة في هذه المهمة.

## 10. معايير القبول

- `GET /api/dashboard/overview` موجود.
- endpoint محمي.
- JSON shape ثابت.
- admin يحصل على cards/sections مناسبة.
- staff لا يحصل على admin-only sections.
- period يظهر في response/meta.
- الاختبارات تمر.
- لا frontend changes.
