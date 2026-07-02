# PROJECT_MAP — يمن موشن (Yemen Motion)

> آخر تحديث: 2026-06-15  
> البيئة: PHP 8.4.21 / Node 24.15.0 / Composer 2.9.4  
> OS: Linux  
> **المرجع المعماري والبنائي الرسمي — الإصدار النهائي 2.0**  
> هذا الملف هو المرجع الوحيد المعتمد لبناء المنصة (Web) وتطبيق الهاتف الذكي (Mobile)

---

## 0. CURRENT IMPLEMENTATION STATUS — 2026-06-30

> هذا القسم يصف موضع التنفيذ الحالي فقط، ولا يغيّر المواصفات المعمارية أو خطة البناء الأصلية في هذا الملف.

### 0.1 Authoritative Source

`PROJECT_MAP.md` هو المرجع المعماري والبنائي الأعلى للمشروع.
ملفات `docs/ym-sdd/` تُستخدم كسجل تنفيذ وتسليم خفيف، ولا تستبدل هذا الملف.

### 0.2 Current Stable Point

آخر نقطة مستقرة موثقة:

- `6109972 docs: update YM-Lite SDD handoff after mobile density passes`

### 0.3 Completed UI Foundation Work

تم تثبيت أساس بصري مستقر لواجهات Dashboard التالية:

- `/admin`
- `/staff`

يشمل ذلك:

- تحسين App Shell وDashboard cards.
- استقرار Light/Dark.
- إصلاح Mobile Sidebar drawer.
- تحسين كثافة `/admin` و `/staff` على mobile/tablet.
- ضبط TopBar dropdowns داخل حدود الشاشة.
- معالجة ازدحام chart labels على mobile/tablet.

الـ commits المرجعية المختصرة:

- `aadbcea fix: improve mobile dashboard sidebar layout`
- `a7172a1 fix: improve admin mobile dashboard density`
- `c7dff54 fix: reduce staff mobile dashboard density`
- `6109972 docs: update YM-Lite SDD handoff after mobile density passes`

للتفاصيل التنفيذية المرحلية، راجع:

```
docs/ym-sdd/memory/CURRENT-HANDOFF-baseline-9a7c91a.md
```

### 0.4 Current Build Position

المنجز الحالي هو:

```
Dashboard UI Foundation
```

وليس:

```
Production Dashboard Core
```

بالتالي، المرحلة 2 من خطة البناء:

```
المرحلة 2 — Dashboard Core المتقدم
```

لم تكتمل إنتاجيًا بعد.

المتبقي من المرحلة 2 يشمل على الأقل:

- Dashboard API حقيقي وآمن.
- بيانات KPIs فعلية بدل البيانات التجريبية.
- فلترة period/sections.
- role-scoped statistics حسب الصلاحيات.
- Activity Feed من مصدر بيانات فعلي.
- ربط `/admin` و `/staff` بالـ API أو store بدل static arrays.
- حالات loading/error/empty.
- اختبارات API وAuthorization.

### 0.5 Product Direction Decision

بعد اكتمال أساس Dashboard البصري، لا يتم فتح polish بصري عام جديد إلا لمعالجة blocker واضح.

المرحلة التالية يجب أن تنتقل إلى إكمال البناء الوظيفي حسب هذا الملف.

### 0.6 Recommended Next Build Phase

```
Phase 2 Completion — Dashboard Core APIs & Real Data Integration
```

#### Reason

لأن `PROJECT_MAP.md` يضع Dashboard Core قبل Users Management، والمرحلة 3 تعتمد على اكتمال المرحلة 2.

#### Initial Scope

- فحص Dashboard endpoints الحالية.
- تحديد الفجوة بين الموجود فعليًا والمطلوب في المرحلة 2.
- إنشاء spec/task منفصل للربط الوظيفي.
- حماية Dashboard API بالصلاحيات.
- ربط Dashboard UI ببيانات فعلية تدريجيًا.

#### Out of Scope

- Users Management.
- Roles & Permissions UI.
- Staff Management.
- أي UI polish عام جديد.
- أي توسع في صفحات `/client` أو `/designer` قبل إغلاق الحد الأدنى من Dashboard Core.

---

## 1. TECH_STACK — المعمارية النهائية المعتمدة

### الـ Stack الكامل

| الطبقة | التقنية | الإصدار | الحالة |
|--------|---------|---------|--------|
| **Frontend Framework** | Nuxt 4 | الأحدث | ✅ مشروع منفصل في `frontend/` |
| **Rendering** | Hybrid (SSR + SPA + SSG) | — | ✅ |
| **Styling** | Tailwind CSS 4 | 4.3.0 | ✅ |
| **State Management** | Pinia | 3.0.4 | ✅ للعامة. `useState` للمحلية |
| **Backend Framework** | Laravel (API Only) | 13.14.0 | ✅ في `backend/` |
| **Database** | PostgreSQL 18 | — | ✅ من البداية — لا SQLite |
| **Cache + Queue** | Redis 8 | — | ✅ من البداية |
| **Auth (API)** | Laravel Sanctum | 4.3.2 | ✅ |
| **Authorization** | Spatie Permission | latest | ✅ |
| **Search (حالياً)** | PostgreSQL Full Text Search | مدمج | ✅ |
| **Search (مستقبلاً)** | Meilisearch | — | ✅ عند كبر الحجم |
| **Storage (Dev)** | Local (Laravel Storage) | — | ✅ |
| **Storage (Prod)** | Cloudflare R2 | — | ✅ بعد الإطلاق |
| **Email (Dev)** | Mailpit | — | ✅ |
| **Email (Prod)** | Postmark | — | ✅ |
| **Video Processing** | FFmpeg | — | ✅ مثبّت |
| **Animations** | GSAP | 3.15.0 | ✅ |
| **Charts** | SVG خالص | — | ✅ لا Chart.js ولا ApexCharts |
| **Mobile App** | Android + iOS | — | 🔜 بعد اكتمال منصة الويب نهائياً |

### قواعد Zero Tolerance

| القاعدة |
|---------|
| ❌ تغيير أي مكون أساسي بدون مراجعة معمارية |
| ❌ استخدام `@nuxt/laravel` |
| ❌ ترحيل تدريجي من Vue SPA — Nuxt من الصفر |
| ❌ استبدال Pinia كلياً بـ useState |
| ❌ الرجوع إلى Laravel 12 |
| ❌ استخدام SQLite |
| ❌ تأجيل Redis |
| ❌ وصول مباشر Client-Designer |
| ❌ بقاء `resources/js/` كجزء دائم من المشروع |
| ❌ بقاء Catch-All Route في Laravel |
| ❌ تجاهل الاختبارات الفاشلة نهائياً |
| ❌ استخدام مكتبات Charts خارجية (Chart.js, ApexCharts) |
| ❌ Chat مباشر بين العميل والمصمم — Admin هو الوسيط |

---

## 2. FULL SYSTEM SPECIFICATION v2.0

### 2.0 الفكرة العامة — 5 محاور تشغيلية

```
1. خلاصة أعمال (Feed)        ← الصفحة الرئيسية تجمع أعمال + مسابقات + نتائج (مثل فيسبوك)
2. مسابقات الإدارة فقط        ← الإدارة تنشئ، المصممون يشاركون، الجمهور يصوّت
3. طلبات (Orders) + RFQ      ← الإدارة وسيط، دفعات 30%/70%، مراجعة أسبوع + اعتماد تلقائي
4. حجوزات (Bookings)         ← حجز مبدئي، تأكيد قبل 5 أيام، إلغاء تلقائي بعد الموعد
5. مالية: محفظة + نقاط       ← مرجع YER، النقاط وحدة عرض، Super Admin فقط يغيّر السعر
```

### 2.1 الأدوار والصلاحيات — Spatie Permission

| الدور | الصلاحيات |
|-------|-----------|
| **Guest** | تصفح/فلترة/مشاهدة/طلب/تصويت — لا لايك ولا مفضلة |
| **Client** | كل ما سبق + مفضلة/لايك + لوحة طلباتي + إشعارات |
| **Designer** | نشر أعمال + تسعير + مسابقات + طلبات + سحب + اشتراك |
| **Super Admin** | كل شيء + سعر النقطة + حسم تعادل + إعدادات المنصة |
| **Finance** | شحن/سحب/سندات/تقارير مالية |
| **Order Manager** | تأكيد يدوي/RFQ/توجيه لمصمم/متابعة |
| **Booking Specialist** | حجوزات (قائمة/إنذار/تأكيد/إلغاء) |
| **Quality Reviewer** | فحص التسليم قبل إرسال للعميل |
| **Content Manager** | مراجعة أعمال + بلاغات + زر ثقة للمصممين |
| **Contest Moderator** | مسابقات (إنشاء/مراجعة/تقييم/نتائج) |
| **Dispute Manager** | نزاعات (فتح/تجميد/قرار/استثناءات) |

الإشعارات حسب الدور. Super Admin: كل الإشعارات فوراً.

### 2.2 خريطة الموقع — طرق العرض حسب نوع الصفحة

| النوع | طريقة العرض | المسارات |
|-------|-------------|---------|
| **عامة** | SSR | `/`, `/work/{id}`, `/designer/{id}`, `/contests`, `/contests/{id}`, `/services`, `/buy-points`, `/subscriptions`, `/auth/*` |
| **ثابتة** | SSG | `/policies/terms`, `/policies/privacy`, `/policies/rights`, `/policies/disputes` |
| **لوحات** | SPA | `/client/*`, `/designer/*`, `/staff/*`, `/admin/*` |

### 2.3 الصفحة الرئيسية — الـ Feed المتقدم (مثل فيسبوك)

**SSR — `/` — Public Homepage**

الصفحة الرئيسية هي واجهة المنصة العامة، يمكن للزائر والعميل والمصوت دخولها بدون تسجيل دخول. تصميمها جذاب بصرياً مع حركة محسوبة وألوان فخمة.

**قسم المسابقة الحالية:** يظهر في أعلى الصفحة بشكل بصري قوي مع غلاف المسابقة، عداد زمني ديناميكي، عدد المشاركات، وزر [شارك الآن]. بجانبه **قسم الفائزين السابقين** يعرض أصحاب المراكز الثلاث 🥇🥈🥉 مع أسمائهم وصورهم.

**زر [طلب خدمة] عام:** ثابت في أعلى الصفحة، يفتح نموذج طلب عام لا يعتمد على عمل محدد.

**الـ Feed:** تدفق رأسي مستمر يجمع أنواع المحتوى المختلطة بمقاسات موحدة:

| نوع البطاقة | المحتوى |
|-------------|---------|
| **WorkCard** | غلاف (600x400) + عنوان + @mention مصمم 👁 مشاهدات ❤ إعجابات + [طلب مشابه] |
| **ContestCard** | 🏆 أيقونة مسابقة + اسم + وقت متبقي + عدد مشاركات + [عرض التفاصيل] [مشاركة] |
| **ContestResultsCard** | ✅ مسابقة منتهية + فائزون بالمراكز الثلاث 🥇🥈🥉 + [عرض النتائج] |
| **Pinned Banner** | مسابقة حالية مثبتة في الأعلى |

**معايير الفلترة والترتيب:**
| المعيار | الوصف |
|---------|-------|
| الأكثر تفاعلاً | مشاهدات + إعجابات + طلبات مشابهة |
| الأكثر مشاهدة | views_count |
| الأكثر إعجاباً | likes_count |
| الأحدث | created_at تنازلي |
| الأقدم | created_at تصاعدي |
| التصنيفات | service / occasion / style |
| اسم المصمم | بحث |
| كلمات مفتاحية | PostgreSQL FTS |

**ترتيب الظهور:** زمني (الأحدث أولاً) مع تثبيت المسابقة النشطة في الأعلى.

### 2.4 نظام الأعمال (Works)

نشر: عنوان + وصف + سعر + مدة تسليم + رفع ملفات + غلاف (FFmpeg) + تصنيفات + معاينة + مراجعة.
الرفع: فيديو 5 دقائق/200MB، صور عالية الدقة.

نظام الثقة: مراجعة أولية لكل عمل. زر ثقة لكل مصمم (مفعّل = نشر مباشر). مدة المراجعة: 48 ساعة كحد أقصى + تذكيرات. حالتان: إرجاع للتعديل أو رفض + إعادة تقديم — كلها مسجلة.

حد 20 عمل مجاني: المؤرشيف يُحتسب، المرفوض لا. قابل للتغيير.

**مقاسات الوسائط الموحدة:**
| النوع | المقاس |
|-------|--------|
| Feed thumbnail | 600x400 |
| Work detail preview | 1280x720 |
| Avatar | 256x256 |
| Contest banner | 1400x500 |
| Winner card avatar | 128x128 |

يتم استخراج Thumbnail للفيديو عبر FFmpeg، وإنشاء نسخ متعددة الأحجام للصور، وتوحيد أبعاد العرض، وحفظ metadata للوسائط.

### 2.5 نظام الطلبات (Orders)

البداية: من الخلاصة/تفاصيل عمل/متجر ← [اطلب مشابه]. نوع: فوري / حجز.
الضيف: هاتف أو بريد + CAPTCHA. Manual Verification مؤتمت حسب وسيلة اتصال مقدم الطلب. لا إلغاء تلقائي.
الدفع خارج المنصة: تحويل يدوي ← تأكيد مختص ← صرف SAR/USD إلى YER ← تحويل إلى نقاط (1 نقطة = 200 YER).
الدفعات: 30/70 + عمولة 20% تُخصم عند التحرير.
المراجعة: تعديلان مجانيان، مهلة أسبوع، اعتماد تلقائي.

### 2.6 الحجوزات (Bookings)

Tentative → تأكيد قبل 5 أيام → إلغاء تلقائي. ألوان: أخضر (30+), أصفر (6-14), أحمر (0-5).

### 2.7 المسابقات (Contests) — SSR

تظهر في: صفحة المسابقات `/contests` + بطاقاتها في الـ Feed الرئيسي.
CAPTCHA + 3 أصوات + منع تكرار + تحقق بريد. معايير مخصصة. تقييم: درجات + حساب تلقائي + حسم تعادل يدوي. بانر مثبت + نتائج سابقة.

### 2.8 النظام المالي

1 نقطة = 200 YER. SAR/USD يُصارَف تلقائياً. تغيير السعر: Super Admin + خطوتين.
شحن: طلب ← دفع خارج المنصة ← تأكيد ← إضافة نقاط.
سحب: طلب ← مراجعة ← تحويل يدوي ← سند ← خصم نقاط.

### 2.9 الإشعارات

حالياً: In-App + Email. مستقبلاً: Push → WhatsApp API. فورية + مخصصة حسب الدور. Super Admin: الكل.

**الإشعارات الإدارية اليدوية:** الأدمن يستطيع إرسال إشعارات إلى فئات محددة (الكل، دور محدد، قسم معين، مستخدمين محددين) مع معاينة عدد المستلمين قبل الإرسال. تُسجّل جميع الإشعارات الإدارية مع سجل كامل للرجوع إليه.

### 2.10 لوحة الإدارة (Admin Dashboard) — SPA

KPIs + SVG Charts + Activity Feed فوري. فترات: يومي/أسبوعي/شهري/سنوي.

**النسخة المتقدمة:** تعرض إحصاءات كل قسم في المنصة بلا استثناء (الطلبات، الحجوزات، العملاء، الزيارات، المستخدمين، المصممين، الأعمال، المسابقات، التصويت، المحفظة، السحوبات، البلاغات، النزاعات، الاشتراكات، الإشعارات، التعليقات). يستطيع الأدمن التبديل بين عرض البطاقات وعرض الرسوم البيانية، وتحديد الفترة الزمنية، واختيار أقسام محددة أو عرض الكل. كل قسم له لون مخصص في البطاقة والرسم البياني. باقي الأدوار ترى فقط الإحصاءات المسموح بها حسب الصلاحيات.

### 2.11 Analytics — قسمان منفصلان

1. **Audit Logs:** عمليات حساسة (سعر النقطة، دفعات، نزاعات، نتائج مسابقات، تعديلات)
2. **Analytics Events:** زيارات، مشاهدات، إعجابات، مفضلة، طلبات، حجوزات

### 2.12 تطبيقات Android و iOS

تطبيقات الهاتف لا تُبنى الآن. يتم بناء Android و iOS بعد اكتمال منصة الويب نهائياً. خلال بناء المنصة، يجب أن تكون كل الـ APIs مستقلة وواضحة ومتوافقة مع أي عميل Frontend لاحقاً.

**الالتزامات الحالية لصالح تطبيق الهاتف لاحقاً:**
- REST API واضح ومستقل عن Nuxt.
- Token-based auth عبر Sanctum.
- JSON responses موحدة.
- Pagination و filtering موحدان في كل القوائم.
- رفع الوسائط عبر API.
- إشعارات قابلة للتوسعة لاحقاً إلى Push Notifications.
- عدم ربط أي business logic بواجهة Nuxt فقط.

### 2.13 مركز تحكم الأدمن الكامل

Super Admin يجب أن يستطيع التحكم بكل شيء من الواجهة بدون الرجوع إلى تعديل الكود: المسميات، الأيقونات، الألوان، التصنيفات، الأدوار، الصلاحيات، حدود الأعمال، إعدادات الصفحة الرئيسية، إعدادات التعليقات، إعدادات المسابقات، إعدادات الحماية، إعدادات الإشعارات، والإعدادات المالية.

كل قيمة تشغيلية مهمة يجب أن تكون قابلة للإدارة من لوحة التحكم أو من Settings model، ولا تكون hardcoded داخل الكود.

**المرحلة 16 (Admin Control Center & Settings)** تُنفّذ هذا القسم بالكامل — انظر Build Plan للمرحلة 16.

### 2.14 التعليقات

يتم تجهيز نظام التعليقات كاملاً، لكن قرار تفعيل التعليقات أو تعطيلها يكون من لوحة تحكم الأدمن. عند التعطيل تختفي التعليقات وأيقونتها بالكامل من الواجهة. عند التفعيل يمكن ضبطها كالتالي: للمسجلين فقط، السماح بالقراءة للزوار، التعليقات تحتاج مراجعة، أو إظهار مباشر.

**المرحلة 7 (Works + Public Homepage Feed + Comments Toggle)** تُنفّذ نظام التعليقات كاملاً مع إعدادات التفعيل. **المرحلة 16 (Admin Control Center)** تُتيح تعديل إعدادات التعليقات من الواجهة.

### 2.15 Security & API Standards

#### أمان الـ API

| المعيار | التفاصيل |
|---------|---------|
| **المصادقة** | Sanctum Token-based — جميع الـ endpoints المحمية تتحقق من Bearer token |
| **الصلاحيات** | Spatie Middleware — `->middleware('permission:edit-users')` |
| **Rate Limiting** | Redis-backed — 60 req/min للـ API العامة، 200 req/min للمصادق عليهم |
| **CORS** | مقيد بـ `localhost:3000` (dev) والنطاق الرسمي (prod) |
| **إدخال البيانات** | Laravel Validation + `max:` لكل الحقول + تعقيم HTML (`strip_tags`) |
| **SQL Injection** | Eloquent ORM يمنع — لا raw queries إلا في التقارير المعقّمة |
| **XSS** | Nuxt يهرب المخرجات تلقائياً + Content-Security-Policy |
| **CSRF** | Sanctum يحمي الـ SPA routes عبر cookies |
| **الملفات المرفوعة** | فحص MIME type + حجم الملف + فحص الفيروسات (ClamAV مستقبلاً) |
| **CORS للـ Mobile** | مسموح لأي Origin عند بناء التطبيق (API عام) |

#### توحيد الـ API Response

```json
{
  "success": true,
  "data": { ... },
  "message": "تم بنجاح",
  "errors": null,
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

#### قواعد التسمية للـ API

| القاعدة | مثال |
|---------|------|
| RESTful + جمع | `/api/users`, `/api/works` |
| لا أفعال في الـ URL | ❌ `/api/get-users` ✅ `/api/users` |
| Pagination موحد | `?page=1&per_page=15` |
| Filtering موحد | `?status=active&role=designer` |
| Sorting موحد | `?sort=created_at&order=desc` |
| Searching موحد | `?search=ahmed&search_by=name,email` |
| Error codes موحدة | 200, 201, 400, 401, 403, 404, 422, 429, 500 |

#### تسمية الـ Routes في Laravel

```php
// Resourceful routes
Route::apiResource('works', WorkController::class);

// Nested resources
Route::apiResource('works.comments', CommentController::class);

// Custom actions — أفعال واضحة
Route::post('works/{work}/like', [WorkController::class, 'like']);
Route::post('works/{work}/favorite', [WorkController::class, 'favorite']);
```

#### الـ HTTP Status Codes المستخدمة

| الرمز | الاستخدام |
|-------|-----------|
| 200 | نجاح (GET, PUT, PATCH) |
| 201 | إنشاء (POST) |
| 204 | حذف (DELETE) |
| 400 | خطأ في الطلب |
| 401 | غير مصادق |
| 403 | غير مصرح |
| 404 | غير موجود |
| 422 | Validation Error |
| 429 | Rate Limit |
| 500 | خطأ سيرفر |

#### معايير عامة

- كل الـ Endpoints تبدأ بـ `/api/`
- الترقيم (Pagination) إلزامي لكل القوائم
- العلاقات (relations) تُضمّن في الـ response عند الطلب عبر `?with=user,media`
- الأخطاء تُرجع رسائل واضحة وباللغة العربية (حسب `Accept-Language`)
- الـ Logging: `emergency`, `error`, `warning`, `info`, `debug`
- كل عملية حساسة تُسجّل في `audit_logs`

---

## 3. ARCHITECTURE

### 3.1 SYSTEM_FLOW

```
[Browser] / [Mobile App]
    ↕
[Nuxt 4 (Nitro Server)]    ← frontend/
    ↕ (HTTP API — JSON)
[Laravel 13 API]            ← backend/ — API Only
    ↕
[PostgreSQL 18 + Redis 8]
```

### 3.2 بنية الدليل النهائية

```
yemen-motion/
├── backend/                          # Laravel 13 API Only
│   ├── app/
│   ├── database/
│   ├── routes/
│   │   ├── api.php                   # فقط — جميع المسارات هنا
│   │   └── web.php                   # شبه فارغ (health check فقط)
│   └── ...
├── frontend/                         # Nuxt 4
│   ├── pages/                        # SSR/SPA/SSG حسب المسار
│   ├── layouts/
│   │   ├── default.vue               # عامة (SSR) — Top Bar فقط
│   │   ├── admin.vue                 # Super Admin (SPA)
│   │   ├── staff.vue                 # موظفين (SPA)
│   │   ├── designer.vue              # مصممين (SPA)
│   │   ├── client.vue                # عملاء (SPA)
│   │   └── auth.vue                  # تسجيل دخول (بدون Top Bar/Sidebar)
│   ├── components/
│   │   ├── ui/                       # MotionLogo, GlassLoginCard, إلخ
│   │   ├── feed/                     # WorkCard, ContestCard, ContestResultsCard
│   │   ├── dashboard/                # StatsCard, StatsChart, ActivityFeed
│   │   └── ...
│   ├── stores/                       # Pinia
│   ├── composables/
│   ├── locales/                      # ar.json, en.json
│   ├── assets/styles/                # design-tokens.css
│   ├── nuxt.config.ts
│   └── package.json
├── package.json (root)               # concurrently: تشغيل Nuxt + Laravel + Queue
└── PROJECT_MAP.md                    # هذا الملف — المرجع النهائي
```

### 3.3 مصير `resources/js/` — مؤقتاً ثم يُحذف

| المرحلة | الإجراء |
|---------|---------|
| **الآن** | يبقى كمرجع فقط — لا تطوير داخله |
| **أثناء البناء** | نقل الـ 8 مكونات إلى Nuxt |
| **بعد التأكد من اكتمال النقل** | حذف: `resources/js/`, `resources/css/`, `vite.config.js`, dependencies الخاصة بـ Vue |
| **النتيجة** | Laravel يصبح API Only بشكل كامل |

### 3.4 المكونات المنقولة من `resources/js/` إلى Nuxt

| المكون | الوجهة |
|--------|--------|
| `MotionLogo.vue` | `components/ui/` |
| `MotionName.vue` | `components/ui/` |
| `GlassLoginCard.vue` | `components/ui/` |
| `AnimatedLights.vue` | `components/ui/` |
| `ParticleBackground.vue` | `components/ui/` |
| `ThemeToggle.vue` | `components/ui/` |
| `LangToggle.vue` | `components/ui/` |
| `NotificationBell.vue` | `components/ui/` |
| `ar.json`, `en.json` | `locales/` |
| `authStore.js`, `i18nStore.js`, `themeStore.js` | `stores/` (converted to .ts) |
| `useI18n.js`, `useTheme.js` | `composables/` (converted to .ts) |
| `design-tokens.css` | `assets/styles/` |

**لا تُنقل:** `App.vue`, `router/index.js`, `LoginView.vue`, `AdminLayout.vue`, `DashboardView.vue`, `StatsCard.vue`, `StatsChart.vue`, `ActivityFeed.vue`, `Sidebar.vue`, `StatusBadge.vue`, `GlassPanel.vue`, `PageHeader.vue`, `UserAvatar.vue`, `BackgroundWatermark.vue` — تُبنى من الصفر في Nuxt حسب الحاجة.

### 3.5 مصير `routes/web.php`

| المسار القديم | الإجراء |
|---------------|---------|
| `Route::get('/{any?}', ...)` | **يُحذف** — Nuxt يتولى التوجيه |
| ما يبقى فقط | Health checks + Debug routes (إن وجدت) |

**النتيجة:** Laravel يصبح `/api/*` فقط.

### 3.6 تشغيل بيئة التطوير — `concurrently`

جذر المشروع يحتوي `package.json` مع:

```json
{
  "scripts": {
    "dev": "concurrently \"cd backend && php artisan serve\" \"cd frontend && npm run dev\" \"cd backend && php artisan queue:work\"",
    "dev:backend": "cd backend && php artisan serve",
    "dev:frontend": "cd frontend && npm run dev"
  }
}
```

أمر واحد: `npm run dev` ← يشغّل Laravel API + Nuxt Dev Server + Queue Worker معاً.
خاص بالتطوير فقط — لا علاقة له ببيئة الإنتاج.

---

## 4. DATABASE SCHEMA (موجزة)

### 4.1 users

`id, name, email, email_verified_at, password, role(via Spatie), avatar, phone, status, bio, interests(JSON), settings(JSON), wallet_balance, trusted, created_by, created_at, updated_at`

### 4.2 works + media

`works (id, designer_id, title, description, price, delivery_days, service_type, occasion, style, thumbnail, media_type, status(pending/approved/rejected/archived), rejection_reason, modification_request, reviewed_by, reviewed_at, views_count, likes_count, favorites_count, reports_count, soft_deletes)`
`work_media (id, work_id, type(video/image), path, thumbnail_path, order, size)`
`work_likes (id, user_id, work_id — unique)`
`work_favorites (id, user_id, work_id — unique)`
`reports (id, user_id, work_id, reason, description, status, reviewed_by, reviewed_at)`

### 4.3 orders

`orders (id, client_name, client_email, client_phone, client_type(guest/client), tracking_token, type(instant/booking), status, description, budget, commission_rate(default=20), commission_amount, assigned_designer_id, currency, currency_rate, amount_in_yer, points_equivalent, service_type, occasion, style, verification_method, verified_at, soft_deletes)`
`order_milestones (id, order_id, percentage(30/70), amount, status, paid_at, released_at)`
`order_timeline (id, order_id, action, description, user_id, created_at)`

### 4.4 bookings

`bookings (id, order_id, client_name, client_phone, client_email, designer_id, booking_date, delivery_date, status(tentative/confirmed/cancelled/auto-cancelled), color_code(green/yellow/red), specialist_id, notes, confirmed_at, cancelled_at, cancelled_reason)`

### 4.5 contests

`contests (id, title, description, conditions, prize_amount, start_date, end_date, status(pending/active/ended/finalized), created_by, pinned)`
`contest_criteria (id, contest_id, name, weight(%), order)`
`contest_submissions (id, contest_id, designer_id, work_id, description, submitted_at)`
`contest_scores (id, contest_id, submission_id, evaluator_id, scores(JSON), total_score)`
`contest_votes (id, contest_id, submission_id, voter_ip, voter_email, email_verified, captcha_passed, created_at — unique per voter+submission)`

### 4.6 financial

`point_prices (id, price_per_point, changed_by, changed_at)`
`currency_rates (id, from_currency(SAR/USD), to_currency(YER), rate, updated_at)`
`wallet_transactions (id, user_id, type(charge/withdrawal/commission/order_earning), amount_in_yer, points, balance_before, balance_after, status, reference_type, reference_id, description)`
`withdrawals (id, user_id, points, amount_in_yer, bank_name, bank_account, account_holder, status(pending/approved/rejected), transfer_receipt_path, reviewed_by, reviewed_at)`

### 4.7 notifications & activities

`notifications(id, type, notifiable_id, notifiable_type, data(JSON), read_at, created_at)`
`activities (id, user_id, type, description, link, created_at)`
`analytics_events (id, type(visit/view/like/order/booking/contest_submission), user_id(nullable), metadata(JSON), created_at)`
`audit_logs (id, user_id, action, description, metadata(JSON), ip_address, user_agent, created_at — read only, no update/delete)`
`admin_notifications (id, sender_id, title, body, audience_type, audience_filter(JSON), priority, type, sent_count, created_at)`

### 4.8 subscriptions & disputes

`subscription_plans (id, name, price, works_limit, features(JSON), is_active)`
`subscriptions (id, user_id, plan_id, starts_at, ends_at, status)`
`subscription_requests (id, user_id, plan_id, amount_yer, status(pending/approved/rejected), payment_reference, reviewed_by, reviewed_at)`
`disputes (id, order_id, raised_by, against_id, reason, status(open/resolved), resolution, resolved_by, resolved_at)`
`dispute_decisions (id, dispute_id, decision_label, description, decided_by, created_at)`

### 4.9 settings, categories & comments

`settings (id, key, value, type, group, is_sensitive, updated_by, updated_at)`
`categories (id, type(service/occasion/style), name_ar, name_en, slug, icon, color, is_active, sort_order)`
`role_display_settings (id, role_name, label_ar, label_en, icon, color)`
`permission_display_settings (id, permission_name, label_ar, label_en, group, icon)`
`ui_sections (id, key, label_ar, label_en, icon, color, is_visible, sort_order)`
`dashboard_sections (id, key, label_ar, label_en, icon, color, permission, is_active, sort_order)`
`comments (id, work_id, user_id, body, status(pending/visible/hidden/deleted), reviewed_by, reviewed_at, created_at, updated_at)`

---

## 5. LOGGING & ANALYTICS

### 5.1 Safe Logging

| المستوى | الاستخدام |
|---------|-----------|
| `emergency` | أعطال كارثية |
| `error` | أخطاء API, فشل مصادقة |
| `warning` | محاولات فاشلة, rate limit |
| `info` | حركة المستخدمين |
| `debug` | local فقط |

### 5.2 Analytics — قسمان

1. **Audit Logs:** تغيير سعر النقطة، تحرير دفعات، نزاعات، نتائج مسابقات، تعديلات إدارية
2. **Analytics Events:** زيارات، مشاهدات، إعجابات، مفضلة، طلبات، حجوزات

---

## 6. BUILD PLAN — خطة البناء التفصيلية الكاملة

> الترتيب النهائي حسب الأولوية:  
> المرحلة التمهيدية (إعداد البيئة) ← المرحلة 0 (App Shell) ← المرحلة 1 (Auth) ← ... ← المرحلة 15 (Audit Logs)

---

### المرحلة التمهيدية — تجهيز البيئة والتعديلات النهائية

> المدة التقديرية: **قبل البدء — تم إنجاز القرارات وجزء من التنفيذ**

#### القرارات النهائية المستقر عليها

| # | القرار | التفاصيل |
|---|--------|----------|
| 1 | هيكلة المشروع | Laravel في `backend/` — Nuxt 4 في `frontend/` — مشروعان منفصلان |
| 2 | PostgreSQL 18 | التبديل من SQLite — لا SQLite أبداً في أي مرحلة |
| 3 | Redis 8 | من البداية: Cache + Queue + Session + Rate Limiting + Notifications |
| 4 | `php8.4-redis` | غير متوفر حالياً — استخدام `predis/predis` عبر Composer كبديل |
| 5 | `.env` | `APP_LOCALE=ar`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis` |
| 6 | Spatie Permission | تثبيت + نشر الإعدادات + إنشاء 11 دوراً مع الصلاحيات |
| 7 | Catch-All Route | حذف `Route::get('/{any?}')` من `routes/web.php` — Laravel يصبح `/api/*` فقط |
| 8 | `concurrently` | `npm run dev` من الجذر يشغّل Nuxt + Laravel + Queue معاً |
| 9 | `resources/js/` | مرجع مؤقت فقط — 8 مكونات تُنقل، الباقي يُبنى من الصفر، ثم يُحذف الملف |
| 10 | اختبارات Laravel | 7 اختبارات فاشلة — تُصلَح بعد PostgreSQL وقبل بدء المرحلة 4 |

---

### المرحلة 0 — App Shell (إلزامية قبل أي صفحة)

> المدة التقديرية: **5 أيام**  
> الاعتماديات: لا شيء (هذه المرحلة هي الأساس)

---

#### نظرياً — تجربة المستخدم

عندما يدخل أي مستخدم إلى المنصة — زائراً كان أم عميلاً أم مصمماً أم موظفاً أم مديراً — يجب أن يشعر فوراً بأنه داخل **نظام موحد**. الشريط العلوي، القائمة الجانبية، الخلفية، الهوية البصرية — كلها ثابتة لا تتغير. المستخدم لا يعيد تعلّم التنقل في كل صفحة.

**ماذا يرى المستخدم:**

- **شريط علوي** (Top Bar) ثابت في كل الصفحات: شعار YM في أقصى اليسار يفتح/يغلق القائمة ← اسم الصفحة الحالية في الوسط → أيقونة الإشعارات ← مبدل اللغة AR/EN ← مبدل المظهر Dark/Light ← صورة المستخدم واسمه ← قائمة منسدلة: [لوحة التحكم / الإعدادات / تسجيل خروج]
- **قائمة جانبية** (Sidebar) حسب دوره: تعرض فقط الصفحات المسموح بها. عرضها 260px مفتوحة، 68px مطوية (أيقونات فقط). فتح/غلق بتأثير سلس GSAP.
- **بطاقة هوية** (Identity Card) أسفل القائمة: صورة مصغرة + اسم المستخدم + Badge ملون حسب الدور مع Glow خفيف + Animation عند الدخول + زر تسجيل الخروج.
- **خلفية موحدة** (Background Watermark): عبارة "YEMEN MOTION" أو "يمن موشن" بحجم ضخم، Opacity 3-5%، ثابتة (fixed)، لا تؤثر على القراءة.
- **نظام إشعارات**: أيقونة جرس في الشريط العلوي ← نقطة حمراء + عدد الإشعارات غير المقروءة ← Dropdown متقدم: [الكل] [غير المقروءة] [المقروءة] [الأحدث] [الأقدم] ← كل إشعار: أيقونة + عنوان + وصف + وقت + رابط ← [تحديد الكل كمقروء] [حذف الكل] [فتح مركز الإشعارات]
- **SearchToolbar موحد**: حقل بحث + فلترة + ترتيب + إعادة تعيين — يُستخدم في كل الصفحات التي تحتاج بحثاً.
- **نظام لغات**: العربية والإنجليزية من أول يوم. زر تبديل في الشريط. RTL/LTR فوري. يُحفظ التفضيل في Pinia + localStorage.
- **نظام مظهر**: Dark و Light من أول يوم. زر تبديل. متغيرات CSS عبر `data-theme`. يُحفظ في Pinia + localStorage. احترام `prefers-color-scheme` عند التحميل الأول.

---

#### عناصر الواجهة — تفصيلاً

##### 1. Top Bar — الشريط العلوي

```
┌─────────────────────────────────────────────────────────────┐
│ [≡ شعار YM]  [اسم الصفحة الحالية]    [🔔] [🌐] [☀/🌙] [👤 أحمد] │
└─────────────────────────────────────────────────────────────┘
```

| العنصر | الموقع | الوظيفة |
|--------|--------|---------|
| شعار YM + زر الهامبرغر | أقصى اليسار | فتح/غلق القائمة الجانبية بتأثير GSAP |
| اسم الصفحة | الوسط | يُحدّد ديناميكياً من `definePageMeta()` |
| NotificationBell | اليمين | Dropdown الإشعارات مع عداد |
| LangToggle | اليمين | AR ↔ EN |
| ThemeToggle | اليمين | Dark ↔ Light |
| UserAvatar + الاسم | أقصى اليمين | Dropdown: لوحة التحكم / الإعدادات / تسجيل خروج |

##### 2. Sidebar — القائمة الجانبية

عرضها: 260px (مفرد) / 68px (مطوي أيقونات فقط)

**Super Admin:**
```
📊 Dashboard
👥 Users
👔 Staff
🔐 Roles & Permissions
🎨 Works
📦 Orders
📅 Bookings
🏆 Contests
💰 Wallet
📈 Reports
📋 Analytics
⚙️ Settings
```

**Staff (Content Manager):**
```
📊 Dashboard
🎨 Content Review
🚩 Reports
```

**Designer:**
```
📊 Dashboard
👤 Profile
🎨 My Works
🏪 Store
📦 Orders
📅 Bookings
💰 Wallet
```

**Client:**
```
📊 Dashboard
📦 My Orders
📅 My Bookings
❤️ Favorites
💰 Wallet
⚙️ Settings
```

**المواصفات:**
- تأثير طي/فتح سلس (GSAP)
- العنصر النشط مظلّل بلون مختلف
- RBAC: العناصر غير المصرح بها مخفية كلياً (لا تظهر ولا يمكن الوصول إليها عبر الرابط المباشر)
- Sidebar ثابت لا يتغير عند التنقل بين الصفحات

##### 3. Identity Card — بطاقة الهوية

أسفل القائمة الجانبية:

```
┌─────────────────────┐
│  [🖼] أحمد محمد     │
│  👑 مدير النظام      │  ← Badge + Glow + Animation
│  [تسجيل الخروج]      │
└─────────────────────┘
```

| العنصر | الوصف |
|--------|-------|
| الصورة | Avatar مصغر للمستخدم |
| الاسم | اسم المستخدم الكامل |
| Badge | أيقونة + اسم الدور بلون مميز (👑 للمدير، 🛡️ للموظف، 🎨 للمصمم، 💼 للعميل) |
| Glow | توهج خفيف يظهر عند الدخول |
| Animation | دخول سلس عند تحميل الصفحة |
| زر الخروج | تسجيل خروج فوري |

##### 4. Background Watermark — الخلفية الموحدة

في كل الصفحات:
```
Y E M E N   M O T I O N
```
أو:
```
ي م ن   م و ش ن
```

| الخاصية | القيمة |
|---------|--------|
| الحجم | ضخم (font-size كبير) |
| Opacity | 3-5% |
| الموقع | fixed (لا تتحرك مع التمرير) |
| z-index | أقل من كل المحتوى |
| التأثير | لا تؤثر على القراءة ولا التفاعل |

##### 5. NotificationDropdown — قائمة الإشعارات المنسدلة

```
┌────────────────────────────────────┐
│  🔔 الإشعارات                       │
│  [الكل] [غير المقروءة] [المقروءة]   │
│  ──────────────────────────────── │
│  ✅ تم تأكيد طلبك رقم #١٥٣        │
│     منذ ٥ دقائق                   │
│  🆕 عمل جديد قيد المراجعة         │
│     منذ ساعة                      │
│  💰 تم شحن ١٠٠٠٠٠ ريال            │
│     منذ ٣ ساعات                   │
│  ──────────────────────────────── │
│  [تحديد الكل كمقروء] [حذف الكل]    │
│  [فتح مركز الإشعارات →]           │
└────────────────────────────────────┘
```

| المكوّن | الوصف |
|---------|-------|
| التبويبات | الكل / غير المقروءة / المقروءة |
| كل إشعار | أيقونة + عنوان + وصف + وقت نسبي (منذ...) |
| رابط | الضغط على الإشعار ينتقل إلى الصفحة المرتبطة |
| أزرار جماعية | تحديد الكل كمقروء، حذف الكل |
| رابط سفلي | فتح مركز الإشعارات الكامل |

##### 6. SearchToolbar — شريط البحث الموحد

```
┌──────────────────────────────────────────────┐
│  🔍 بحث...     [فلترة ▼]     [ترتيب ▼]     [↻] │
└──────────────────────────────────────────────┘
```

| المكوّن | الوظيفة |
|---------|---------|
| حقل بحث نصي | إدخال النص للبحث |
| فلترة | Dropdown حسب الفئة (حسب الصفحة) |
| ترتيب | تصاعدي/تنازلي أو حسب التاريخ/الاسم |
| إعادة تعيين | مسح كل الحقول |

**يُستخدم في:** Users, Works, Orders, Stores, Contests, Feed

---

#### Layouts — الهيكل المعماري للواجهة

```
layouts/
├── default.vue          # الصفحات العامة (SSR) — Top Bar فقط
├── admin.vue            # Super Admin (SPA) — Top Bar + Sidebar + Identity Card + Background
├── staff.vue            # باقي الموظفين (SPA) — Top Bar + Sidebar + Identity Card + Background
├── designer.vue         # المصممين (SPA) — Top Bar + Sidebar + Identity Card + Background
├── client.vue           # العملاء (SPA) — Top Bar + Sidebar + Identity Card + Background
└── auth.vue             # تسجيل الدخول (بدون Top Bar ولا Sidebar) — خلفية متحركة فقط
```

**مبدأ العمل:**
```
default.vue ← Top Bar فقط (للمستخدمين غير المسجلين)
auth.vue    ← بدون Top Bar ولا Sidebar (شاشة تسجيل دخول كاملة مع خلفية متحركة)
admin/staff/designer/client.vue ← Top Bar + Sidebar + Identity Card + Background Watermark
```

**كل Layout يرث من نفس App Shell — لا يعاد بناء أي عنصر داخل الصفحات.**

---

#### المعمارية — الجانب التقني

##### Backend (Laravel)

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/user` | المستخدم الحالي + أدواره + صلاحياته (Spatie) |
| `GET /api/notifications` | إشعارات المستخدم (مقسمة كل/مقروء/غير مقروء) |
| `PATCH /api/notifications/{id}/read` | تحديد إشعار كمقروء |
| `PATCH /api/notifications/read-all` | تحديد الكل كمقروء |
| `DELETE /api/notifications/{id}` | حذف إشعار |
| `DELETE /api/notifications` | حذف الكل |

**موديلز مطلوبة:**
- `User` — تحديث الحقول: `role`, `avatar`, `phone`, `settings` (JSON), `trusted`, `wallet_balance`
- `Notification` — جديد

##### Frontend (Nuxt)

**مكونات جديدة تُبنى:**

| المكون | الوظيفة |
|--------|---------|
| `AppTopBar.vue` | الشريط العلوي الموحد |
| `AppSidebar.vue` | القائمة الجانبية حسب الدور |
| `IdentityCard.vue` | بطاقة الهوية أسفل القائمة |
| `NotificationDropdown.vue` | قائمة الإشعارات المنسدلة |
| `NotificationBell.vue` | أيقونة الجرس مع العداد |
| `SearchToolbar.vue` | شريط البحث والفلترة الموحد |
| `BackgroundWatermark.vue` | الخلفية الموحدة |
| `LangToggle.vue` | مبدل اللغة |
| `ThemeToggle.vue` | مبدل المظهر |
| `UserDropdown.vue` | قائمة المستخدم المنسدلة |
| `Layouts/*.vue` | الـ 6 Layouts |

**مكونات منقولة من `resources/js/`:**
- `MotionLogo.vue` → `components/ui/MotionLogo.vue`
- `MotionName.vue` → `components/ui/MotionName.vue`
- `ThemeToggle.vue`, `LangToggle.vue` — تُدمج في المكونات الجديدة
- `NotificationBell.vue` — يُدمج في `NotificationBell.vue`

**Stores (Pinia):**

| الـ Store | الوظيفة |
|-----------|---------|
| `authStore` | جلب المستخدم + دوره عند تحميل الصفحة، تخزين الـ token |
| `themeStore` | Dark/Light + حفظ في localStorage + احترام `prefers-color-scheme` |
| `i18nStore` | AR/EN + حفظ في localStorage + تبديل RTL/LTR |
| `notificationStore` | جلب الإشعارات + إدارة الحالة (مقروء/غير مقروء) + عداد |

**Nuxt Middleware:**

| الـ Middleware | الوظيفة |
|---------------|---------|
| `auth.ts` | منع الوصول غير المصرح به — التحقق من وجود token سليم |
| `role.ts` | توجيه حسب الدور — مصمم يحاول فتح `/admin` ← يُعاد توجيهه إلى `/designer` |

---

#### معايير نجاح المرحلة 0

- [ ] الـ 6 Layouts تعمل مع Top Bar + Sidebar حسب الدور
- [ ] Sidebar تعرض فقط العناصر المصرح بها (RBAC)
- [ ] الضغط على الشعار يفتح/يغلق القائمة بتأثير سلس
- [ ] تغيير اللغة AR/EN يعكس كل النصوص فوراً
- [ ] تغيير المظهر Dark/Light يعكس كل الألوان فوراً
- [ ] NotificationDropdown يعرض الإشعارات مع الفلترة والعداد
- [ ] Identity Card تظهر اسم المستخدم + دوره + Badge + Glow
- [ ] Background Watermark يظهر في كل الصفحات
- [ ] SearchToolbar جاهز للاستخدام في أي صفحة
- [ ] `npm run dev` يشغّل كل شيء بأمر واحد

---

### المرحلة 1 — Authentication (تسجيل الدخول)

> المدة التقديرية: **3 أيام**  
> الاعتماديات: المرحلة 0 (App Shell)

---

#### نظرياً — تجربة المستخدم

**الزائر (Guest)** يصل إلى المنصة ← يرى الخلاصة ← يريد الإعجاب أو الطلب ← تُفتح نافذة "سجّل للاستفادة من المزايا" ← يضغط تسجيل دخول.

**صفحة تسجيل الدخول** — `/auth/login`:
- تصميم Glass Aesthetic (شفاف + زجاجي مع تأثير ضبابي)
- خلفية متحركة متكاملة (Particle Background + Animated Lights + MotionLogo)
- حقول: بريد إلكتروني + كلمة مرور
- زر: [تسجيل الدخول]
- روابط: [إنشاء حساب] / [نسيت كلمة المرور؟]

**صفحة إنشاء حساب** — `/auth/register`:
- الاسم الكامل + البريد الإلكتروني + رقم الهاتف (اختياري) + كلمة مرور + تأكيدها
- اختيار الدور: عميل / مصمم
- CAPTCHA (مكافحة البوتات)
- بعد الإرسال ← رسالة "تم إنشاء الحساب، رجاءً تحقق بريدك الإلكتروني"

**بعد تسجيل الدخول:** المستخدم يُوجَّه حسب دوره إلى لوحة التحكم الخاصة به:
- عميل ← `/client`
- مصمم ← `/designer`
- موظف ← `/staff`
- مدير ← `/admin`

**نسيت كلمة المرور** — `/auth/forgot-password`:
- إدخال البريد الإلكتروني
- إرسال رابط إعادة تعيين عبر البريد

**إعادة تعيين كلمة المرور** — `/auth/reset-password`:
- رمز التحقق + كلمة مرور جديدة + تأكيد

---

#### عناصر الواجهة

##### Login Page — `/auth/login`

```
┌─────────────────────────────────────────────┐
│                                             │
│              🎬  يمن موشن                    │
│           (شعار متحرك MotionLogo)            │
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │         تسجيل الدخول                  │   │
│  │                                     │   │
│  │  📧  البريد الإلكتروني               │   │
│  │  [__________________________]      │   │
│  │                                     │   │
│  │  🔒  كلمة المرور                    │   │
│  │  [__________________________]      │   │
│  │                                     │   │
│  │  [⚠️ تسجيل الدخول]                   │   │
│  │                                     │   │
│  │  نسيت كلمة المرور؟                  │   │
│  │  ليس لديك حساب؟  إنشاء حساب          │   │
│  └─────────────────────────────────────┘   │
│                                             │
│  ✨ (ParticleBackground + AnimatedLights)    │
│     خلفية متحركة                             │
└─────────────────────────────────────────────┘
```

| العنصر | الوصف |
|--------|-------|
| MotionLogo + MotionName | شعار متحرك (GSAP) أعلى الصفحة |
| GlassLoginCard | بطاقة زجاجية شفافة تحتوي الحقول |
| ParticleBackground | جسيمات متحركة في الخلفية |
| AnimatedLights | أضواء متحركة للخلفية |
| حقل البريد | مع أيقونة @ وتحقّق من صحة الإدخال |
| حقل كلمة المرور | مع أيقونة 🔒 وزر إظهار/إخفاء |
| زر تسجيل الدخول | مع حالة تحميل (Loading spinner) |
| رابط نسيت كلمة المرور | يفتح صفحة Forgot Password |
| رابط إنشاء حساب | ينتقل إلى Register |

##### Register Page — `/auth/register`

```
┌─────────────────────────────────────────────┐
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │         إنشاء حساب جديد               │   │
│  │                                     │   │
│  │  👤  الاسم الكامل                   │   │
│  │  [__________________________]      │   │
│  │                                     │   │
│  │  📧  البريد الإلكتروني               │   │
│  │  [__________________________]      │   │
│  │                                     │   │
│  │  📞  رقم الهاتف (اختياري)            │   │
│  │  [__________________________]      │   │
│  │                                     │   │
│  │  أنا:  ○ عميل  ○ مصمم              │   │
│  │                                     │   │
│  │  🔒  كلمة المرور                    │   │
│  │  [__________________________]      │   │
│  │                                     │   │
│  │  🔒  تأكيد كلمة المرور              │   │
│  │  [__________________________]      │   │
│  │                                     │   │
│  │  ☑ أنا لست برنامجاً (CAPTCHA)      │   │
│  │                                     │   │
│  │  [📝 إنشاء حساب]                    │   │
│  │                                     │   │
│  │  لديك حساب؟  تسجيل الدخول           │   │
│  │  بالتسجيل أنت توافق على الشروط      │   │
│  └─────────────────────────────────────┘   │
└─────────────────────────────────────────────┘
```

| العنصر | الوصف |
|--------|-------|
| اختيار الدور | Radio: عميل / مصمم — يحدد الـ Layout بعد التسجيل |
| CAPTCHA | ReCAPTCHA أو تحدي بسيط |
| التحقق | التحقق من تطابق كلمتي المرور + صحة البريد + قوة كلمة المرور |
| الموافقة على الشروط | رابط لصفحة Policies (SSG) |

##### Forgot Password — `/auth/forgot-password`

```
┌─────────────────────────────────────┐
│  🔑  نسيت كلمة المرور                │
│                                      │
│  أدخل بريدك الإلكتروني:              │
│  [__________________________]       │
│                                      │
│  [إرسال رابط إعادة التعيين]          │
│                                      │
│  تذكرت كلمة المرور؟  تسجيل الدخول    │
└─────────────────────────────────────┘
```

##### Reset Password — `/auth/reset-password?token=xxx`

```
┌─────────────────────────────────────┐
│  🔑  إعادة تعيين كلمة المرور         │
│                                      │
│  رمز التحقق: [________________]     │
│  كلمة المرور الجديدة: [________]    │
│  تأكيد كلمة المرور: [________]      │
│                                      │
│  [حفظ كلمة المرور الجديدة]           │
└─────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend (Laravel Sanctum)

| الـ Endpoint | الطريقة | الوظيفة |
|-------------|---------|---------|
| `POST /api/auth/register` | عام | إنشاء حساب ← إرجاع `{user, token}` |
| `POST /api/auth/login` | عام | تسجيل دخول ← إرجاع `{user, token, role}` |
| `POST /api/auth/logout` | مصادق | إبطال الـ token الحالي |
| `POST /api/auth/forgot-password` | عام | إرسال رابط إعادة تعيين للبريد |
| `POST /api/auth/reset-password` | عام | إعادة تعيين كلمة المرور بالرمز |
| `GET /api/auth/verify-email/{id}/{hash}` | عام | تحقق البريد الإلكتروني |
| `POST /api/auth/email/verification-notification` | مصادق | إعادة إرسال رابط التحقق |
| `GET /api/user` | مصادق | المستخدم الحالي + أدواره + صلاحياته (Spatie) |

**موديل User — الحقول المطلوبة:**
```
id, name, email, email_verified_at, password,
role (via Spatie), avatar, phone, status,
bio, interests (JSON), settings (JSON),
wallet_balance, trusted, created_by
```

##### Frontend (Nuxt)

**صفحات (SSR):**
- `pages/auth/login.vue`
- `pages/auth/register.vue`
- `pages/auth/forgot-password.vue`
- `pages/auth/reset-password.vue`

**توجيه (Route Rules):**
```ts
routeRules: {
  '/auth/**': { ssr: true }
}
```

**مكونات:**
- `GlassLoginCard.vue` — منقول من `resources/js/`
- `ParticleBackground.vue` — منقول
- `AnimatedLights.vue` — منقول
- `MotionLogo.vue` — منقول
- `MotionName.vue` — منقول

**Layout:**
- `layouts/auth.vue` — بدون Top Bar ولا Sidebar، فقط خلفية متحركة + Card

**Sanctum Config:**
```php
// config/sanctum.php
'stateful' => ['localhost:3000', 'localhost:8000'],
```

---

#### معايير النجاح

- [ ] تسجيل دخول يعمل ويعيد `{user, token, role}`
- [ ] إنشاء حساب مع تحقق بريد إلكتروني
- [ ] إعادة تعيين كلمة المرور عبر البريد
- [ ] التوجيه حسب الدور بعد تسجيل الدخول
- [ ] الـ Middleware يمنع الوصول غير المصرح به
- [ ] Glass aesthetic + خلفية متحركة (Particles + Lights)
- [ ] CAPTCHA في التسجيل
- [ ] `php artisan test` — جميع اختبارات Auth تمر ✅

---

### المرحلة 2 — Dashboard Core المتقدم (لوحة المعلومات والإحصاءات)

> المدة التقديرية: **5 أيام**  
> الاعتماديات: المرحلة 1 (Auth)

---

#### نظرياً — تجربة المستخدم

بعد تسجيل الدخول، أول صفحة يراها المستخدم هي **لوحة المعلومات** (Dashboard). كل دور يرى محتوى مختلفاً يناسب مهامه:

**Super Admin:** يرى نبضة سريعة عن أداء المنصة بأكملها. كروت تحتوي: عدد المستخدمين الجدد اليوم، الطلبات الجديدة بانتظار التأكيد، الأعمال قيد المراجعة، المسابقات النشطة، إجمالي الإيرادات الشهرية، عدد النقاط المشحونة. تحتها رسمين بيانيين (الطلبات خلال الشهر + الإيرادات — SVG خالص). وأسفلها Activity Feed حي يُحدّث فورياً — كل عملية مهمة في المنصة تظهر هنا. الضغط على أي نشاط يفتح صفحة التفاصيل الكاملة.

**Content Manager:** يرى عدد الأعمال بانتظار المراجعة، عدد البلاغات الجديدة، آخر الأعمال المرفوعة.

**Order Manager:** يرى الطلبات بانتظار التأكيد، آخر الطلبات الموكلة، إشعارات RFQ الجديدة.

**Finance:** يرى إجمالي الإيرادات، طلبات السحب بانتظار المراجعة، آخر الحركات المالية.

**Designer:** يرى عدد أعماله المنشورة، عدد الطلبات الجديدة الواردة، رصيد محفظته، آخر الإشعارات الخاصة به.

**Client:** يرى حالة آخر طلب لديه، عدد نقاطه في المحفظة، آخر أعماله المفضلة، آخر الإشعارات.

**Super Admin — النسخة المتقدمة:** لا تقتصر لوحة التحكم على بضعة KPIs، بل تعرض إحصاءات كل قسم في المنصة بلا استثناء: الطلبات، الحجوزات، العملاء، الزيارات، المستخدمين، المصممين، الأعمال، المسابقات، التصويت، المحفظة، السحوبات، البلاغات، النزاعات، الاشتراكات، الإشعارات، والتعليقات إذا كانت مفعّلة. يستطيع الأدمن التبديل بين عرض البطاقات وعرض الرسوم البيانية، وتحديد الفترة الزمنية، واختيار أقسام محددة أو عرض الكل. كل قسم له لون مخصص يظهر في البطاقة والرسم البياني. باقي الأدوار ترى فقط الإحصاءات المسموح بها حسب الصلاحيات.

---

#### عناصر الواجهة

##### Admin Dashboard — `/admin`

```

##### Admin Dashboard — Advanced Mode

```
┌────────────────────────────────────────────────────────────┐
│  📊 لوحة التحكم                                            │
│                                                            │
│  [بطاقات] [رسوم بيانية]                                   │
│  [اليوم] [الأسبوع] [الشهر] [السنة] [من] [إلى]             │
│  الأقسام: [الكل ▼]                                         │
│                                                            │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐                     │
│  │طلبات │ │حجوزات│ │عملاء │ │زيارات│                     │
│  │ 45   │ │ 12   │ │ 230  │ │ 5000 │                     │
│  │ أزرق │ │ أصفر │ │ أخضر │ │ بنفسجي│                    │
│  └──────┘ └──────┘ └──────┘ └──────┘                     │
│                                                            │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐                     │
│  │مصممين│ │أعمال │ │مسابقات│ │محفظة│                     │
│  │ 80   │ │ 900  │ │ 4     │ │ 1.2M │                    │
│  └──────┘ └──────┘ └──────┘ └──────┘                     │
│                                                            │
│  ⚡ التحديثات الفورية                                      │
│  تم إنشاء طلب جديد، تم نشر عمل، تم شحن محفظة...           │
└────────────────────────────────────────────────────────────┘
```

##### Chart Mode

```
┌────────────────────────────────────────────────────────────┐
│  📈 الرسوم البيانية                                        │
│                                                            │
│  [الطلبات] [الحجوزات] [الزيارات] [الأعمال]                │
│                                                            │
│  ┌────────────────────────────────────────────────────┐    │
│  │ طلبات     ━━━━━ أزرق                               │    │
│  │ حجوزات    ━━━━━ أصفر                               │    │
│  │ زيارات    ━━━━━ بنفسجي                             │    │
│  │ أعمال     ━━━━━ وردي                                │    │
│  └────────────────────────────────────────────────────┘    │
└────────────────────────────────────────────────────────────┘
```
┌──────────────────────────────────────────────────────────────┐
│  📊  لوحة التحكم                                              │
│  [اليوم] [الأسبوع] [الشهر] [السنة]  [من ▼] [إلى ▼]           │
│                                                              │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐               │
│  │ ١٢٤  │ │ ٣٤   │ │ ٨    │ │ ١٢   │ │ ٤٥٠  │               │
│  │مستخدم│ │طلبات │ │أعمال │ │مسابقات│ │نقاط  │               │
│  │جديد  │ │جديدة │ │جديدة │ │نشطة  │ │مشحونة│               │
│  │ +١٢% │ │ +٥%  │ │ -٢%  │ │ ٠%   │ │ +٢٠% │               │
│  └──────┘ └──────┘ └──────┘ └──────┘ └──────┘               │
│                                                              │
│  ┌────────────────────────┐  ┌────────────────────────┐     │
│  │  📈 الطلبات خلال الشهر  │  │  📈 الإيرادات           │     │
│  │  ▂▃▄▅▆▇██▇▆▅▄▃▂       │  │  ▁▂▃▄▅▆▇█▇▆▅▄▃▂       │     │
│  │  [SVG Bar Chart]       │  │  [SVG Line Chart]      │     │
│  └────────────────────────┘  └────────────────────────┘     │
│                                                              │
│  ⚡  آخر النشاطات                                             │
│  ┌────────────────────────────────────────────────────┐     │
│  │ 🟢 تم تأكيد طلب #١٥٣ من العميل X — منذ دقيقتين     │     │
│  │ 🔵 تم نشر عمل جديد للمصمم Y — منذ ٥ دقائق          │     │
│  │ 🟡 تم تقديم طلب سحب من المصمم Z — منذ ١٠ دقائق     │     │
│  │ 🔴 تم تقديم بلاغ على عمل — منذ ١٥ دقيقة            │     │
│  └────────────────────────────────────────────────────┘     │
└──────────────────────────────────────────────────────────────┘
```

| العنصر | الوصف |
|--------|-------|
| StatsCards | بطاقات KPI: رقم كبير + تسمية + نسبة تغير (+/-) عن الفترة السابقة |
| StatsChart | رسم بياني SVG (Bar/Line) مع فلترة الفترة — SVG خالص |
| ActivityFeed | قائمة النشاطات فورية — كل عملية تظهر فور حدوثها — قابلة للضغط |
| PeriodFilter | [اليوم] [الأسبوع] [الشهر] [السنة] [من] [إلى] |

##### Designer Dashboard — `/designer`

```
┌───────────────────────────────────────────────────────────┐
│  📊  لوحة التحكم                                           │
│                                                           │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐                     │
│  │ ١٥   │ │ ٣    │ │ ١٢٠٠ │ │ ٨٥٠٠ │                     │
│  │أعمال │ │طلبات │ │نقطة  │ │ريال  │                     │
│  │منشورة│ │واردة │ │رصيد  │ │أرباح │                     │
│  └──────┘ └──────┘ └──────┘ └──────┘                     │
│                                                           │
│  ⚡  آخر الطلبات الواردة                                    │
│  ┌──────────────────────────────────────────────────┐    │
│  │ 📦 طلب #٢٠١ — تصميم لوجو — ١٠٠٠٠٠ ريال — جديد    │    │
│  └──────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────┘
```

##### Client Dashboard — `/client`

```
┌───────────────────────────────────────────────────────────┐
│  📊  لوحة التحكم                                           │
│                                                           │
│  ┌──────┐ ┌──────┐ ┌──────┐                              │
│  │ طلب  │ │ ٢٥٠  │ │ ٤    │                              │
│  │قيد   │ │نقطة  │ │مفضلة│                              │
│  │تنفيذ │ │رصيد  │ │     │                              │
│  └──────┘ └──────┘ └──────┘                              │
│                                                           │
│  📦  آخر طلباتي                                            │
│  🔵 طلب #١٥٣ — تصميم هوية — قيد التنفيذ                   │
│                                                           │
│  ❤️  آخر المفضلات                                         │
│  [عمل ١]  [عمل ٢]  [عمل ٣]                               │
└──────────────────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend (Laravel)

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/dashboard/stats` | KPIs حسب صلاحية المستخدم + الفترة |
| `GET /api/dashboard/activities` | آخر 20 نشاط (Activity Feed) |
| `GET /api/dashboard/chart?type=orders&period=month` | بيانات الرسم البياني |
| `GET /api/dashboard/overview` | كل الإحصاءات حسب الدور |
| `GET /api/dashboard/cards` | بيانات البطاقات |
| `GET /api/dashboard/charts` | بيانات الرسوم البيانية |
| `GET /api/dashboard/sections` | الأقسام المتاحة حسب صلاحيات المستخدم |
| `GET /api/admin/dashboard/all-sections` | كل الأقسام — Super Admin فقط |

**موديل:**
```php
// Activity
id, user_id, type (enum), description, link, created_at

// DashboardSection
id, key, label_ar, label_en, icon, color, permission, is_active, sort_order
```

##### Frontend (Nuxt)

**صفحات جديدة (SPA):**
- `pages/admin/index.vue`
- `pages/staff/index.vue`
- `pages/designer/index.vue`
- `pages/client/index.vue`

**مكونات تُبنى:**

| المكون | الوظيفة |
|--------|---------|
| `StatsCard.vue` | بطاقة KPI مع أيقونة + رقم + نسبة تغير |
| `StatsChart.vue` | رسم بياني SVG (Bar/Line) مع فلترة — SVG خالص |
| `ActivityFeed.vue` | قائمة النشاطات الفورية — قابلة للضغط |
| `PeriodFilter.vue` | فلترة الفترات الزمنية |
| `DashboardKPIs.vue` | مجموعة StatsCards حسب الدور |
| `DashboardViewToggle.vue` | تبديل بين بطاقات ورسوم بيانية |
| `DashboardSectionFilter.vue` | اختيار الأقسام المعروضة |
| `DashboardMetricCard.vue` | بطاقة إحصائية ملونة حسب القسم |
| `DashboardMultiChart.vue` | رسم بياني متعدد الأقسام |
| `DashboardLegend.vue` | دليل ألوان الأقسام |
| `RoleBasedDashboard.vue` | عرض حسب الدور والصلاحيات |

**مبدأ SVG Charts (خالص — بدون مكتبات):**
```vue
<template>
  <svg :viewBox="`0 0 ${width} ${height}`">
    <line v-for="(point, i) in data" :key="i"
      :x1="getX(i)" :y1="height"
      :x2="getX(i+1)" :y2="height - scale(point.value)"
      stroke="currentColor" stroke-width="2" />
  </svg>
</template>
```

---

#### معايير النجاح

- [ ] كل دور يرى Dashboard خاصاً به مع KPIs المناسبة
- [ ] KPIs صحيحة ومأخوذة من الـ API مع فلترة الفترات
- [ ] SVG Charts تعمل مع Bar و Line
- [ ] Activity Feed فوري ومحدّث
- [ ] الضغط على أي عنصر في Activity Feed يفتح صفحة التفاصيل
- [ ] تصميم موحد مع App Shell (Top Bar + Sidebar + Background)
- [ ] Super Admin يرى إحصاءات كل الأقسام بلا استثناء
- [ ] كل قسم له لون مخصص في البطاقة والرسم البياني
- [ ] يمكن التبديل بين عرض البطاقات وعرض الرسوم البيانية
- [ ] يمكن اختيار أقسام محددة أو عرض الكل
- [ ] باقي الأدوار ترى الإحصاءات حسب الصلاحيات فقط

---

### المرحلة 3 — Users Management (إدارة المستخدمين)

> المدة التقديرية: **3 أيام**  
> الاعتماديات: المرحلة 2 (Dashboard)

---

#### نظرياً — تجربة المستخدم (Super Admin)

**Super Admin** يدخل `/admin/users` ← يرى جدولاً بكل المستخدمين المسجلين. يمكنه البحث بالاسم أو البريد أو الهاتف، وفلترة حسب الدور (عميل/مصمم/موظف) والحالة (نشط/موقوف/معلق). كل صف يظهر: رقم، اسم المستخدم، بريده الإلكتروني، رقم هاتفه، صورته، دوره مع Badge ملون، حالته مع دائرة ملونة، تاريخ التسجيل، وأزرار الإجراءات.

يمكنه الضغط على [تعديل] لأي مستخدم ← نافذة تعديل: تغيير الاسم، البريد، الهاتف، تغيير الدور (تحذير: قد يؤثر على صلاحياته)، تغيير الحالة. للمصممين: زر الثقة (Trust Toggle) — إذا كان مفعّلاً، تُنشر أعمال المصمم مباشرة بدون مراجعة. يمكنه حظر مستخدم (تعطيل الحساب فوراً) أو إضافة مستخدم جديد.

---

#### عناصر الواجهة

##### Users List — `/admin/users`

```
┌─────────────────────────────────────────────────────────────────┐
│  👥  المستخدمين                          [إضافة مستخدم ＋]       │
│                                                                 │
│  🔍 بحث...   [الدور: الكل ▼]   [الحالة: الكل ▼]   [↻]           │
│                                                                 │
│  ┌────┬──────┬──────────┬────────┬──────────┬────────┬────────┐ │
│  │ #  │ الصورة│ الاسم    │ البريد │ الهاتف   │ الدور  │ الحالة│ │
│  ├────┼──────┼──────────┼────────┼──────────┼────────┼────────┤ │
│  │ ١  │ [🖼] │ أحمد    │ a@..   │ ٧٧٧١٢٣  │ 👑 مدير│ 🟢 نشط │ │
│  │ ٢  │ [🖼] │ سارة    │ s@..   │ ٧٧٨٤٥٦  │ 🎨 مصمم│ 🟢 نشط │ │
│  │ ٣  │ [🖼] │ خالد    │ k@..   │ —       │ 💼 عميل│ 🔴 موقوف│ │
│  │ ٤  │ [🖼] │ نور     │ n@..   │ ٧٧٩٧٨٩  │ 🎨 مصمم│ 🟡 معلق│ │
│  └────┴──────┴──────────┴────────┴──────────┴────────┴────────┘ │
│                                                                 │
│  ◀ ١ ٢ ٣ ... ١٠ ▶                                              │
└─────────────────────────────────────────────────────────────────┘
```

##### User Edit Modal

```
┌─────────────────────────────────────────┐
│  ✏️  تعديل المستخدم: أحمد محمد           │
│                                          │
│  [🖼 تغيير الصورة]                        │
│                                          │
│  الاسم الكامل    [أحمد محمد________]    │
│  البريد          [ahmed@example.com_]   │
│  الهاتف          [777123456_________]   │
│  الدور           [مدير النظام ▼]        │
│  الحالة          [نشط ▼]               │
│                                          │
│  ─── إعدادات المصمم ───                  │
│  [⬜] نشر مباشر (زر الثقة)              │
│                                          │
│  [💾 حفظ التغييرات]  [✖ إلغاء]          │
└─────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend (Laravel)

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/admin/users` | قائمة المستخدمين (بحث + فلترة + ترتيب + Pagination) |
| `GET /api/admin/users/{id}` | تفاصيل مستخدم كاملة |
| `POST /api/admin/users` | إنشاء مستخدم جديد (مع كلمة مرور) |
| `PUT /api/admin/users/{id}` | تحديث بيانات مستخدم |
| `PATCH /api/admin/users/{id}/status` | تغيير الحالة (نشط/موقوف/معلق) |
| `PATCH /api/admin/users/{id}/trust` | تفعيل/تعطيل زر الثقة للمصمم |
| `DELETE /api/admin/users/{id}` | حذف المستخدم (أو تعطيل فقط) |

**موديل User — استخدام Spatie:**
```php
$user->assignRole('designer');
$user->hasRole('admin');
$user->can('edit-users');
```

##### Frontend (Nuxt)

**صفحات (SPA):**
- `pages/admin/users.vue`
- `pages/admin/users/[id].vue` (اختياري — أو Modal)

**مكونات:**
- `UsersTable.vue` — جدول المستخدمين مع الفرز والفلترة
- `UserFormModal.vue` — نافذة إضافة/تعديل مستخدم
- `StatusBadge.vue` — دائرة ملونة للحالة
- `RoleBadge.vue` — Badge للدور مع أيقونة ولون

---

#### معايير النجاح

- [ ] عرض جميع المستخدمين مع Pagination
- [ ] بحث يعمل بالاسم والبريد والهاتف
- [ ] فلترة بالدور والحالة
- [ ] تعديل مستخدم — تغيير الاسم، البريد، الهاتف، الدور، الحالة
- [ ] تفعيل/تعطيل زر الثقة للمصممين
- [ ] إضافة مستخدم جديد
- [ ] حظر/تفعيل مستخدم
- [ ] Spatie: صلاحية `edit-users` مطلوبة للوصول

---

### المرحلة 4 — Roles & Permissions (الأدوار والصلاحيات)

> المدة التقديرية: **3 أيام**  
> الاعتماديات: المرحلة 3 (Users)

---

#### نظرياً — تجربة المستخدم (Super Admin)

**Super Admin** يدخل `/admin/roles` ← يرى قائمة بكل الأدوار. كل دور يظهر مع: اسمه، أيقونته، عدد المستخدمين المنتمين إليه، عدد الصلاحيات المرتبطة به. يضغط على أي دور لفتح **محرر الصلاحيات**.

في محرر الصلاحيات، يرى كل الـ Permissions المتاحة مقسمة حسب الوحدة (المستخدمين، الطلبات، الأعمال، المالية، المسابقات، الإعدادات). كل صلاحية هي Checkbox — يحدد المسموح والممنوع. عند الحفظ، تنعكس التغييرات فوراً، وكل مستخدم يحمل هذا الدور تتغير صلاحياته دون الحاجة لتسجيل خروج.

يمكنه أيضاً إنشاء دور جديد (مثال: "مدير تسويق") واختيار صلاحياته من الصفر، أو حذف دور موجود (مع تحذير).

---

#### عناصر الواجهة

##### Roles List — `/admin/roles`

```
┌────────────────────────────────────────────────────────┐
│  🔐  الأدوار والصلاحيات              [إضافة دور ＋]      │
│                                                        │
│  ┌──────┬──────────┬──────────────┬────────┬─────────┐ │
│  │ #    │ أيقونة   │ الاسم        │ المستخدمون│ الصلاحيات│ │
│  ├──────┼──────────┼──────────────┼────────┼─────────┤ │
│  │ ١    │ 👑      │ مدير النظام  │ ٢      │ ٣٤      │ │
│  │ ٢    │ 💰      │ مالية        │ ٣      │ ١٢      │ │
│  │ ٣    │ 🎨      │ مراجعة محتوى │ ١      │ ٨       │ │
│  │ ٤    │ 📦      │ مدير طلبات   │ ٢      │ ١٥      │ │
│  │ ٥    │ 📅      │ حجوزات       │ ١      │ ٦       │ │
│  │ ٦    │ 🏆      │ مسابقات      │ ١      │ ١٠      │ │
│  │ ٧    │ 🔍      │ مراقبة جودة  │ ٠      │ ٤       │ │
│  │ ٨    │ ⚖️      │ نزاعات       │ ٠      │ ٥       │ │
│  └──────┴──────────┴──────────────┴────────┴─────────┘ │
└────────────────────────────────────────────────────────┘
```

##### Permission Editor — `/admin/roles/{id}/edit`

```
┌────────────────────────────────────────────────────────┐
│  ✏️  تعديل صلاحيات: 💰 مالية                            │
│                                                        │
│  🔴 المستخدمون: أحمد، سارة، خالد                         │
│                                                        │
│  ─── 📦 الطلبات ───                                     │
│  ☑ عرض كل الطلبات                                      │
│  ☐ تأكيد الطلبات                                       │
│  ☐ إلغاء الطلبات                                       │
│                                                        │
│  ─── 💰 المالية ───                                     │
│  ☑ عرض التقارير المالية                                 │
│  ☑ إدارة الشحن                                         │
│  ☑ إدارة السحب                                         │
│  ☐ إدارة سعر النقطة                                     │
│                                                        │
│  [💾 حفظ الصلاحيات]  [✖ إلغاء]                          │
└────────────────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend (Spatie Permission)

**11 Roles:** `super-admin`, `finance`, `order-manager`, `booking-specialist`, `quality-reviewer`, `content-manager`, `contest-moderator`, `dispute-manager`, `guest`, `client`, `designer`

**Permissions مقسمة حسب الوحدة:**
- **Users:** `create-users`, `edit-users`, `delete-users`, `view-users`
- **Orders:** `view-orders`, `create-orders`, `confirm-orders`, `assign-orders`, `cancel-orders`
- **Works:** `view-works`, `create-works`, `edit-works`, `delete-works`, `review-works`, `approve-works`
- **Finance:** `view-financial-reports`, `manage-charges`, `manage-withdrawals`, `manage-point-price`, `manage-commissions`
- **Contests:** `create-contests`, `edit-contests`, `delete-contests`, `evaluate-contests`, `finalize-contests`
- **Settings:** `edit-settings`
- **Roles:** `edit-roles`

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/admin/roles` | قائمة الأدوار مع عدد المستخدمين |
| `GET /api/admin/roles/{id}` | دور معين مع صلاحياته |
| `POST /api/admin/roles` | إنشاء دور جديد |
| `PUT /api/admin/roles/{id}/permissions` | تحديث صلاحيات الدور |
| `DELETE /api/admin/roles/{id}` | حذف دور (مع تحذير) |
| `GET /api/admin/permissions` | كل الصلاحيات المتاحة (مقسمة حسب الوحدة) |

##### Frontend (Nuxt)

**صفحات (SPA):**
- `pages/admin/roles.vue`
- `pages/admin/roles/[id].vue`

**مكونات:**
- `RolesTable.vue` — جدول الأدوار
- `PermissionEditor.vue` — قائمة الصلاحيات مع Checkboxes مقسمة حسب الوحدة
- `RoleFormModal.vue` — إنشاء/تعديل دور

---

#### معايير النجاح

- [ ] عرض الأدوار الـ 11 مع عدّاد المستخدمين والصلاحيات
- [ ] محرر صلاحيات مع Checkboxes مقسمة حسب الوحدة
- [ ] حفظ الصلاحيات ينعكس فوراً (لا يحتاج إعادة تسجيل دخول)
- [ ] إنشاء دور جديد
- [ ] حذف دور مع تحذير
- [ ] الـ API محمية بـ `permission:edit-roles`

---

### المرحلة 5 — Staff Management (إدارة الموظفين)

> المدة التقديرية: **2 يوم**  
> الاعتماديات: المرحلة 4 (Roles)

---

#### نظرياً — تجربة المستخدم (Super Admin)

يدخل `/admin/staff` ← يرى قائمة بكل الموظفين (المستخدمين من أدوار Staff الثمانية فقط — غير العملاء والمصممين). كل صف: اسم الموظف، صورته، دوره، آخر نشاط له، الحالة، والإجراءات.

يمكنه تعيين مستخدم كموظف: يختار مستخدم موجود (بريد أو اسم) ويختار له دوراً من أدوار Staff. إذا لم يكن المستخدم موجوداً، ينشئ حساباً جديداً بدور Staff. يمكنه أيضاً تعطيل موظف مؤقتاً (يبقى حسابه لكن لا يستطيع تسجيل الدخول) أو تغيير دوره.

---

#### عناصر الواجهة

##### Staff List — `/admin/staff`

```
┌───────────────────────────────────────────────────────┐
│  👔  الموظفين                       [تعيين موظف ＋]    │
│                                                       │
│  ┌────┬──────┬──────────┬──────────┬────────┬───────┐ │
│  │ #  │ الاسم │ البريد   │ الدور    │ آخر نشاط│ الحالة│ │
│  ├────┼──────┼──────────┼──────────┼────────┼───────┤ │
│  │ ١  │ أحمد │ a@..    │ 👑 مدير   │ منذ ٢ د│ 🟢    │ │
│  │ ٢  │ سارة │ s@..    │ 💰 مالية │ منذ ١ س│ 🟢    │ │
│  │ ٣  │ خالد │ k@..    │ 📦 طلبات │ أمس    │ 🔴    │ │
│  └────┴──────┴──────────┴──────────┴────────┴───────┘ │
└───────────────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/admin/staff` | قائمة الموظفين (Staff فقط) |
| `POST /api/admin/staff` | تعيين مستخدم موجود كموظف (أو إنشاء + تعيين) |
| `PATCH /api/admin/staff/{id}/deactivate` | تعطيل موظف |
| `PATCH /api/admin/staff/{id}/activate` | إعادة تفعيل موظف |
| `PATCH /api/admin/staff/{id}/role` | تغيير دور الموظف |

---

#### معايير النجاح

- [ ] عرض الموظفين فقط (غير العملاء والمصممين)
- [ ] تعيين موظف جديد (مستخدم موجود أو إنشاء جديد)
- [ ] تعطيل/تفعيل موظف
- [ ] تغيير دور الموظف
- [ ] بحث وفلترة

---

### المرحلة 6 — Designer Profiles (الملفات الشخصية للمصممين)

> المدة التقديرية: **3 أيام**  
> الاعتماديات: المرحلة 5 (Staff)

---

#### نظرياً — تجربة المستخدم

**المصمم:** يدخل `/designer/profile` ← يرى صفحة تعديل ملفه الشخصي. يعدل: الصورة الشخصية، الاسم، السيرة الذاتية، رقم الهاتف، روابط وسائل التواصل (Twitter, Instagram, Behance)، والتخصصات — ثلاث تصنيفات: نوع الخدمة (لوجو/موشن/هوية/بوسترات...)، المناسبة (عيد/زواج/مؤتمر/إطلاق...)، الستايل (مودرن/كلاسيك/مينيمال/جرافيتي...). يحفظ التغييرات ← تنعكس فوراً في متجره العام.

**الزائر:** يزور صفحة المصمم العامة عبر `/designer/{id}` (SSR) ← يرى: صورة المصمم، اسمه، سيرته، تخصصاته، تقييمه، عدد الطلبات المكتملة، شبكة بأعماله المنشورة. يضغط على أي عمل ← ينتقل إلى صفحة تفاصيل العمل. يضغط [اطلب تصميم] ← تفتح نافذة الطلب.

---

#### عناصر الواجهة

##### Designer Settings (SPA) — `/designer/profile`

```
┌────────────────────────────────────────────┐
│  👤  الملف الشخصي                           │
│                                            │
│  [🖼  صورة الملف]  [تغيير]                 │
│                                            │
│  الاسم الكامل    [أحمد المصمم_________]   │
│  السيرة الذاتية  [_____________________]  │
│  الهاتف          [777123456_________]     │
│                                            │
│  🔗  وسائل التواصل                         │
│  Twitter    [@ahmed_design________]       │
│  Instagram  [@ahmed_design________]       │
│  Behance    [ahmed_design_________]       │
│                                            │
│  🎨  التخصصات                               │
│  الخدمة: [لوجو ▼] [+ إضافة]               │
│  المناسبة: [عيد ▼] [+ إضافة]              │
│  الستايل: [مودرن ▼] [+ إضافة]             │
│                                            │
│  [💾 حفظ التغييرات]                        │
└────────────────────────────────────────────┘
```

##### Public Designer Page (SSR) — `/designer/{id}`

```
┌──────────────────────────────────────────────────┐
│  ┌──────────────────┐                            │
│  │   [🖼 صورة كبيرة] │                            │
│  │   أحمد المصمم     │                            │
│  │   🎨 مصمم جرافيك  │                            │
│  │   📍 اليمن        │                            │
│  │   ⭐ ٤.٨ (٣٠ تقييم)│                            │
│  │   📦 ١٥ طلب مكتمل │                            │
│  └──────────────────┘                            │
│                                                  │
│  [📝 ابدأ طلب تصميم]                              │
│                                                  │
│  📋  السيرة الذاتية  🏷️  التخصصات                 │
│  🎨  أعماله  [تحميل المزيد]                      │
└──────────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/designer/profile` | الملف الشخصي للمصمم الحالي |
| `PUT /api/designer/profile` | تحديث الملف الشخصي (مع رفع صورة) |
| `GET /api/designer/{id}` | بيانات المصمم العامة (SSR) |
| `GET /api/designer/{id}/works` | أعمال المصمم المنشورة (SSR) |

**موديل User — حقول إضافية:**
```
bio, interests (JSON: {services: [], occasions: [], styles: []}),
avatar, social_links (JSON: {twitter, instagram, behance})
```

##### Frontend

**صفحات:**
- `pages/designer/profile.vue` ← SPA
- `pages/designer/[id].vue` ← SSR

---

#### معايير النجاح

- [ ] المصمم يعدل ملفه الشخصي (صورة، اسم، سيرة، تخصصات، تواصل)
- [ ] حفظ التغييرات ينعكس فوراً في المتجر العام
- [ ] صفحة المصمم العامة (SSR) تظهر المعلومات كاملة + شبكة الأعمال
- [ ] الضغط على عمل ينتقل إلى Work Detail
- [ ] زر [اطلب تصميم] يفتح نافذة الطلب

---

### المرحلة 7 — Works Management + Public Homepage Feed (إدارة الأعمال والصفحة الرئيسية)

> المدة التقديرية: **8 أيام**  
> الاعتماديات: المرحلة 6 (Designer Profiles) + FFmpeg

---

#### نظرياً — تجربة المستخدم

**المصمم:** يدخل `/designer/works` ← يرى كل أعماله مع حالتها: منشورة (public)، قيد المراجعة (pending)، مرفوضة (rejected)، مؤرشفة (archived). يضغط [إضافة عمل جديد] ← يملأ النموذج: عنوان، وصف تفصيلي، سعر بالريال، مدة التسليم بالأيام، رفع ملفات (فيديو أو صور)، اختيار الغلاف (من الفيديو تلقائياً عبر FFmpeg أو رفع صورة منفصلة)، اختيار التصنيفات الثلاث (خدمة، مناسبة، ستايل) ← معاينة ← إرسال للمراجعة. بعد الإرسال: إذا كان المصمم موثوقاً (Trust Toggle مفعّل)، يُنشر العمل مباشرة. وإلا يدخل قائمة الانتظار للمراجعة.

**Content Manager:** يدخل `/admin/content` ← يرى قائمة الأعمال بانتظار المراجعة مرتبة من الأقدم للأولوية. يفتح عملاً ← يشاهد معاينة العمل والملفات المرفوعة ← يقرر: [قبول] (ينشر فوراً) / [إرجاع للتعديل مع ملاحظات] / [رفض] (مع سبب). مدة المراجعة: 48 ساعة كحد أقصى مع تذكيرات متكررة.

**الزائر — الصفحة الرئيسية (الـ Feed):** يدخل `/` (SSR) ← يرى في أعلى الصفحة **قسم المسابقة الحالية** بشكل بصري قوي مع غلاف المسابقة، عداد زمني ديناميكي، وزر [شارك الآن]. بجانبه **قسم الفائزين السابقين** يعرض أصحاب المراكز الثلاث 🥇🥈🥉 مع أسمائهم وصورهم. أسفل ذلك يظهر **زر [طلب خدمة]** ثابت. ثم يبدأ **الـ Feed** بتدفق مستمر من المحتوى مثل فيسبوك — بطاقات متعددة الأنواع (أعمال، مسابقات نشطة، نتائج سابقة) في تدفق واحد بمقاسات موحدة. كل بطاقة عمل تحتوي: غلاف + عنوان + اسم المصمم @mention + إعجابات ❤ + تعليقات 💬 + مفضلة 📌 + مشاركة 📤 + زر [اطلب مشابه]. التحميل عبر Infinite Scroll. الفلترة تشمل: الأكثر تفاعلاً، الأكثر مشاهدة، الأكثر إعجاباً، الأحدث، الأقدم، التصنيفات، أسماء المصممين، الكلمات المفتاحية.

**الزائر — تفاصيل العمل:** يدخل `/work/{id}` (SSR) ← يرى: مشغل وسائط (فيديو/صور)، العنوان، الوصف، السعر، مدة التسليم، المصمم (رابط للمتجر)، التصنيفات، الإعجابات، المفضلة، المشاهدات، أزرار: [اطلب مشابه] [إعجاب] [مفضلة] [مشاركة] [بلاغ].

**التعليقات:** يتم بناء نظام التعليقات كاملاً في هذه المرحلة. الأدمن يستطيع من لوحة التحكم (لاحقاً في Phase 16) تفعيل التعليقات أو تعطيلها. عند التعطيل تختفي أيقونة التعليقات وصندوق التعليق بالكامل. عند التفعيل: للمسجلين فقط، أو قراءة للزوار، أو تحتاج مراجعة، أو إظهار مباشر.

---

#### عناصر الواجهة

##### الصفحة الرئيسية (SSR) — `/` — Public Homepage المتقدمة

```
┌────────────────────────────────────────────────────────────┐
│  [شعار يمن موشن]              [طلب خدمة] [بحث...]          │
│                                                            │
│  ┌──────────────────────────────┐ ┌──────────────────────┐ │
│  │ 🏆 المسابقة الحالية           │ │ 🥇 الفائزون السابقون │ │
│  │ تصميم شعار السنة              │ │ 🥇 أحمد              │ │
│  │ متبقي: 5 أيام                 │ │ 🥈 سارة              │ │
│  │ المشاركات: 23                 │ │ 🥉 خالد              │ │
│  │ [عرض المسابقة] [شارك الآن]    │ │ [عرض النتائج]        │ │
│  └──────────────────────────────┘ └──────────────────────┘ │
│                                                            │
│  [الكل] [تصاميم] [إعلانات] [مونتاج] [موشن] [لوجو]         │
│  [الأكثر تفاعلاً ▼] [الأكثر مشاهدة ▼] [الأحدث ▼]          │
│                                                            │
│  ┌────────────┐ ┌────────────┐ ┌────────────┐             │
│  │ [عمل موحد] │ │ [عمل موحد] │ │ [عمل موحد] │             │
│  │ تصميم شعار │ │ إعلان فيديو│ │ مونتاج     │             │
│  │ @designer  │ │ @designer  │ │ @designer  │             │
│  │ 👁 1200 ❤ 42│ │ 👁 900 ❤ 31│ │ 👁 700 ❤ 20│             │
│  │ [طلب مشابه]│ │ [طلب مشابه]│ │ [طلب مشابه]│             │
│  └────────────┘ └────────────┘ └────────────┘             │
│                                                            │
│  [تحميل المزيد]                                           │
└────────────────────────────────────────────────────────────┘
```

| نوع البطاقة | المحتوى | مصدر البيانات |
|-------------|---------|---------------|
| **CurrentContestPanel** | غلاف + اسم مسابقة + عداد ديناميكي + عدد مشاركات + [عرض] [شارك] | `GET /api/home/current-contest` |
| **WinnersPanel** | 🥇🥈🥉 + أسماء المصممين + صور + [عرض النتائج] | `GET /api/home/latest-winners` |
| **WorkCard** | غلاف (600x400) + عنوان + @mention مصمم + مشاهدات 👁 + إعجابات ❤ + [طلب مشابه] | `GET /api/feed` |
| **ContestCard** | 🏆 + اسم مسابقة + وقت متبقي + مشاركات + [عرض] | `GET /api/contests/active` |
| **ContestResultsCard** | ✅ + اسم المسابقة المنتهية + 🥇🥈🥉 + [عرض النتائج] | `GET /api/contests/recent-results` |

**معايير الفلترة والترتيب:**
| المعيار | الوصف |
|---------|-------|
| الأكثر تفاعلاً | مشاهدات + إعجابات + طلبات مشابهة |
| الأكثر مشاهدة | ترتيب حسب views_count |
| الأكثر إعجاباً | ترتيب حسب likes_count |
| الأحدث | ترتيب حسب created_at تنازلي |
| الأقدم | ترتيب حسب created_at تصاعدي |
| التصنيفات | service / occasion / style |
| اسم المصمم | بحث في المصممين |
| اسم العمل | بحث في العناوين |
| كلمات مفتاحية | PostgreSQL Full Text Search |

##### مقاسات الوسائط الموحدة

| النوع | المقاس |
|-------|--------|
| Feed thumbnail | 600x400 |
| Work detail preview | 1280x720 |
| Avatar | 256x256 |
| Contest banner | 1400x500 |
| Winner card avatar | 128x128 |

**معالجة تلقائية عبر FFmpeg:**
```php
// استخراج Thumbnail للفيديو
$ffmpeg = FFMpeg::open($videoPath);
$thumbnail = $ffmpeg->getFrameFromSeconds(1);
$feedThumbnail = $ffmpeg->resize(600, 400);  // للـ Feed
$preview = $ffmpeg->resize(1280, 720);       // لصفحة التفاصيل
$compressed = $ffmpeg->compress(20000);      // ضغط للرفع
```

##### Comments — قسم التعليقات

###### Work Comments (عند التفعيل)

```
┌──────────────────────────────────────────────┐
│  💬 التعليقات (12)                            │
│                                              │
│  أحمد: عمل جميل جداً                         │
│  سارة: الألوان ممتازة                         │
│                                              │
│  اكتب تعليقك:                                │
│  [______________________________] [إرسال]    │
└──────────────────────────────────────────────┘
```

###### Admin Comments Settings

```
┌──────────────────────────────────────────────┐
│  💬 إعدادات التعليقات                         │
│                                              │
│  [●] تفعيل التعليقات                          │
│  [●] التعليق للمسجلين فقط                    │
│  [○] السماح للزوار بالتعليق                  │
│  [●] التعليقات تحتاج مراجعة                  │
│                                              │
│  [حفظ]                                       │
└──────────────────────────────────────────────┘
```

###### Content Manager — Comments Moderation

```
┌──────────────────────────────────────────────┐
│  مراجعة التعليقات                            │
│                                              │
│  ┌────┬────────┬────────┬────────┬────────┐ │
│  │ #  │ المستخدم│ العمل  │ الحالة │ إجراء  │ │
│  ├────┼────────┼────────┼────────┼────────┤ │
│  │ 1  │ أحمد   │ شعار   │ قيد    │ مراجعة │ │
│  │ 2  │ سارة   │ إعلان  │ ظاهر   │ إخفاء  │ │
│  └────┴────────┴────────┴────────┴────────┘ │
└──────────────────────────────────────────────┘
```

##### Designer — Works List — `/designer/works`

```
┌──────────────────────────────────────────────────────┐
│  🎨  أعمالي                          [إضافة عمل ＋]   │
│                                                      │
│  [الكل] [منشور] [قيد المراجعة] [مرفوض] [مؤرشف]      │
│                                                      │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐               │
│  │[غلاف]│ │[غلاف]│ │[غلاف]│ │[غلاف]│               │
│  │عنوان١│ │عنوان٢│ │عنوان٣│ │عنوان٤│               │
│  │🟢منشور│ │🟡قيد │ │🔴مرفوض│ │⚪مؤرشف│               │
│  └──────┘ └──────┘ └──────┘ └──────┘               │
└──────────────────────────────────────────────────────┘
```

##### Designer — Add Work Form — `/designer/works/new`

```
┌─────────────────────────────────────────────┐
│  ✨  إضافة عمل جديد                           │
│                                             │
│  العنوان        [________________________] │
│  الوصف          [________________________] │
│  السعر          [________]  ريال            │
│  مدة التسليم    [___]  يوم                  │
│                                             │
│  📁  رفع الملفات                             │
│  ┌─────────────────────────────────────┐  │
│  │  [اسحب وأفلت الملفات هنا]            │  │
│  │  يدعم: MP4, MOV, JPG, PNG, WEBP    │  │
│  │  الحد: 200MB, 5 دقائق للفيديو       │  │
│  └─────────────────────────────────────┘  │
│                                             │
│  🖼  الغلاف:                                 │
│  ○ استخراج من الفيديو (FFmpeg)              │
│  ○ رفع منفصل [اختيار صورة]                  │
│                                             │
│  🏷️  التصنيفات                               │
│  الخدمة [لوجو ▼]   المناسبة [عيد ▼]        │
│  الستايل [مودرن ▼]                          │
│                                             │
│  [👁 معاينة]  [📤 إرسال للمراجعة]           │
└─────────────────────────────────────────────┘
```

##### Content Review — Admin — `/admin/content`

```
┌──────────────────────────────────────────────────────┐
│  🎨  مراجعة المحتوى                                    │
│                                                      │
│  ┌────┬──────────┬──────────┬──────┬────────┬──────┐ │
│  │ #  │ العمل    │ المصمم   │ منذ  │ الحالة │ إجراء│ │
│  ├────┼──────────┼──────────┼──────┼────────┼──────┤ │
│  │ ١  │ تصميم شعار│ أحمد    │ ٢ سا │ 🟡 قيد │ مراجعة│ │
│  │ ٢  │ موشن جرافيك│ سارة   │ ١٠ س│ 🟡 قيد │ مراجعة│ │
│  └────┴──────────┴──────────┴──────┴────────┴──────┘ │
│                                                      │
│  ── عند الضغط على [مراجعة] ──                         │
│  ┌────────────────────────────────────────────────┐  │
│  │ [📹 معاينة العمل]  [📂 الملفات المرفوعة]        │  │
│  │ ملاحظات: [________________________]            │  │
│  │ [👍 قبول]  [✏️ إرجاع]  [👎 رفض]               │  │
│  └────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────┘
```

##### Work Detail (SSR) — `/work/{id}`

```
┌──────────────────────────────────────────────────┐
│  [📹 فيديو/صور العمل — مشغل وسائط]                │
│                                                    │
│  العنوان: تصميم هوية بصرية لشركة X                │
│                                                    │
│  المصمم: [🖼] أحمد المصمم  ← رابط المتجر          │
│  ⭐ ٤.٨  📦 ١٥ طلب مكتمل                          │
│                                                    │
│  السعر: ٥٠٠٠٠ ريال  |  مدة التسليم: ٧ أيام        │
│                                                    │
│  🏷️  #لوجو #مودرن #شركات                          │
│                                                    │
│  ────────────────────────────────────────────     │
│  ❤ ٤٢ إعجاب  📌 ١٥ مفضلة  👁 ١٢٠٣ مشاهدة          │
│                                                    │
│  [اطلب مشابه]  [❤ إعجاب]  [🔖 مفضلة]              │
│  [📤 مشاركة]   [🚩 بلاغ]                           │
│                                                    │
│  ┌───┐ ┌───┐ ┌───┐ ┌───┐                          │
│  │وات│ │فيس│ │توي│ │تلغ│ ← أزرار مشاركة           │
│  └───┘ └───┘ └───┘ └───┘                          │
└──────────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/home` | بيانات الصفحة الرئيسية كاملة (مسابقة حالية + فائزون + فيد) |
| `GET /api/home/current-contest` | المسابقة الحالية |
| `GET /api/home/latest-winners` | آخر الفائزين |
| `GET /api/feed` | الصفحة الرئيسية — دمج أعمال + مسابقات نشطة + نتائج (Pagination + Infinite Scroll) |
| `GET /api/feed/filters` | التصنيفات والفلاتر المتاحة |
| `GET /api/works` | الخلاصة (فلترة + بحث + ترتيب + PostgreSQL FTS) |
| `GET /api/works?sort=most_viewed` | الأكثر مشاهدة |
| `GET /api/works?sort=most_liked` | الأكثر إعجاباً |
| `GET /api/works?sort=most_engaged` | الأكثر تفاعلاً |
| `GET /api/works/{id}` | تفاصيل العمل (مع زيادة المشاهدات) |
| `POST /api/works` | إنشاء عمل جديد (Designer) |
| `POST /api/works/{id}/media` | رفع ملفات العمل (فيديو/صور) + FFmpeg |
| `PUT /api/works/{id}` | تعديل العمل |
| `DELETE /api/works/{id}` | حذف/أرشفة العمل |
| `GET /api/admin/content/pending` | أعمال بانتظار المراجعة (Admin) |
| `PATCH /api/admin/content/{id}/approve` | قبول العمل + نشر |
| `PATCH /api/admin/content/{id}/reject` | رفض العمل + سبب |
| `PATCH /api/admin/content/{id}/request-modification` | إرجاع للتعديل + ملاحظات |
| `POST /api/works/{id}/like` | إعجاب (toggle) |
| `POST /api/works/{id}/favorite` | مفضلة (toggle) |
| `POST /api/works/{id}/report` | بلاغ + سبب |
| `POST /api/service-request` | طلب خدمة عام |
| `POST /api/works/{id}/request-similar` | طلب خدمة مشابهة |
| `GET /api/designer/{id}/works` | أعمال مصمم معين (public) |
| `GET /api/works/{id}/comments` | عرض التعليقات (حسب الإعدادات) |
| `POST /api/works/{id}/comments` | إضافة تعليق |
| `PUT /api/comments/{id}` | تعديل تعليق المستخدم |
| `DELETE /api/comments/{id}` | حذف تعليق المستخدم |
| `GET /api/admin/comments` | إدارة التعليقات (Admin) |
| `PATCH /api/admin/comments/{id}/approve` | اعتماد تعليق |
| `PATCH /api/admin/comments/{id}/hide` | إخفاء تعليق |
| `DELETE /api/admin/comments/{id}` | حذف تعليق |
| `PUT /api/admin/settings/comments` | إعدادات التعليقات (تفعيل/تعطيل/شروط) |

**موديل Work:**
```php
// Work
id, designer_id, title, description, price, delivery_days,
service_type, occasion, style, thumbnail, media_type (video/image),
status (pending/approved/rejected/archived),
rejection_reason, modification_request,
reviewed_by, reviewed_at,
views_count, likes_count, favorites_count, soft_deletes
```

**موديل Comment:**
```php
comments:
id, work_id, user_id, body, status(pending/visible/hidden/deleted),
reviewed_by, reviewed_at, created_at, updated_at
```

**حد الـ 20 عمل مجاني:**
```php
$count = Work::where('designer_id', $user->id)
    ->whereIn('status', ['approved', 'archived'])
    ->count();

if ($count >= 20 && !$user->hasSubscription()) {
    throw new Exception('تجاوزت الحد المجاني');
}
```

**FFmpeg Integration — معالجة المقاسات:**
```php
$ffmpeg = FFMpeg::open($videoPath);
$thumbnail = $ffmpeg->getFrameFromSeconds(1);     // إطار من الثانية 1
$feedThumbnail = $ffmpeg->resize(600, 400);       // للـ Feed
$preview = $ffmpeg->resize(1280, 720);            // معاينة مصغرة
$compressed = $ffmpeg->compress(20000);            // ضغط حتى 20MB
```

##### Frontend

**صفحات SSR:**
- `pages/index.vue` — الـ Feed الرئيسي (هيرو + فائزون + أعمال)
- `pages/work/[id].vue` — Work Detail

**صفحات SPA:**
- `pages/designer/works.vue` — قائمة الأعمال
- `pages/designer/works/new.vue` — إضافة عمل
- `pages/designer/works/[id]/edit.vue` — تعديل عمل
- `pages/admin/content.vue` — مراجعة المحتوى

**مكونات جديدة للصفحة الرئيسية المتقدمة:**

| المكون | الوظيفة |
|--------|---------|
| `HomepageHero.vue` | قسم المسابقة الحالية + الفائزين |
| `CurrentContestPanel.vue` | بطاقة المسابقة الحالية مع عداد |
| `WinnersPanel.vue` | 🥇🥈🥉 الفائزون السابقون |
| `PublicServiceRequestButton.vue` | زر طلب خدمة عام ثابت |
| `UnifiedWorkCard.vue` | بطاقة عمل موحدة المقاسات (600x400) |
| `FeedGrid.vue` | شبكة الأعمال |
| `ServiceRequestModal.vue` | نموذج طلب عام |
| `SimilarRequestModal.vue` | طلب مشابه لعمل |
| `MediaPreview.vue` | عرض الفيديو/الصورة بمقاس موحد |

**مكونات الـ Feed (محدثة):**

| المكون | الوظيفة |
|--------|---------|
| `WorkCard.vue` | بطاقة عمل في الـ Feed (غلاف + عنوان + @mention + إعجابات + تعليقات + مفضلة + مشاركة + سعر + [اطلب مشابه]) |
| `ContestCard.vue` | بطاقة مسابقة نشطة (🏆 + اسم + وقت + مشاركات + [عرض] [مشاركة]) |
| `ContestResultsCard.vue` | بطاقة نتائج مسابقة (🥇🥈🥉 + اسم + [عرض]) |
| `FeedBanner.vue` | بانر مثبت في أعلى الـ Feed |
| `FeedFilterBar.vue` | شريط فلاتر (كل التصنيفات + ترتيب متعدد) |
| `InfiniteScroll.vue` | تحميل المزيد عند التمرير |
| `WorkDetail.vue` | تفاصيل العمل الكاملة مع مشغل وسائط |
| `WorkForm.vue` | نموذج إضافة/تعديل عمل |
| `MediaUploader.vue` | رفع ملفات مع سحب وإفلات + FFmpeg |
| `ContentReviewModal.vue` | نافذة المراجعة (معاينة + قبول/رفض/إرجاع + ملاحظات) |
| `ShareButtons.vue` | أزرار مشاركة (واتساب، فيسبوك، تويتر، تلغرام) |
| `ReportModal.vue` | نافذة بلاغ مع اختيار السبب |
| `CommentSection.vue` | قسم التعليقات (قابلة للتفعيل/التعطيل) |
| `CommentItem.vue` | عنصر تعليق واحد |
| `CommentForm.vue` | صندوق كتابة تعليق |
| `AdminCommentsModeration.vue` | مراجعة التعليقات (Admin) |
| `CommentSettingsPanel.vue` | إعدادات التعليقات للأدمن |

---

#### معايير النجاح

- [ ] **الصفحة الرئيسية (/) تعمل كفيسبوك**: تدفق مختلط (أعمال + مسابقات + نتائج) مع Infinite Scroll
- [ ] المسابقة الحالية تظهر في أعلى الصفحة مع عداد ديناميكي وزر مشاركة
- [ ] الفائزون السابقون 🥇🥈🥉 يظهرون بجانب المسابقة
- [ ] زر [طلب خدمة] عام ثابت يعمل بدون الاعتماد على عمل محدد
- [ ] مقاييس الـ Feed موحدة (600x400) مع معالجة تلقائية عبر FFmpeg
- [ ] الفلترة تشمل: الأكثر تفاعلاً، مشاهدة، إعجاباً، الأحدث، الأقدم، التصنيفات، الأسماء
- [ ] المصمم يضيف عملاً مع رفع فيديو/صور + FFmpeg (غلاف + معاينة + تصغير)
- [ ] التصنيفات الثلاث (خدمة، مناسبة، ستايل) تعمل
- [ ] Content Review: قبول/رفض/إرجاع + ملاحظات + تذكيرات 48 ساعة
- [ ] Trust Toggle: المصمم الموثوق يُنشر عمله مباشرة
- [ ] حد الـ 20 عمل مجاني يُحتسب بشكل صحيح
- [ ] تفاصيل العمل (SSR) مع إعجاب/مفضلة/بلاغ/مشاركة
- [ ] أزرار مشاركة تعمل (WhatsApp, Facebook, Twitter, Telegram)
- [ ] **نظام التعليقات**: مكتمل البناء — قابل للتفعيل/التعطيل من الأدمن
- [ ] التعليقات: للمسجلين فقط أو قراءة للزوار، تحتاج مراجعة أو مباشرة
- [ ] Content Manager يراجع ويخفي ويحذف التعليقات
- [ ] أيقونة التعليقات تختفي بالكامل عند التعطيل من الإعدادات

---

### المرحلة 8 — Stores (متاجر المصممين)

> المدة التقديرية: **3 أيام**  
> الاعتماديات: المرحلة 7 (Works)

---

#### نظرياً — تجربة المستخدم

**الزائر** يصل إلى صفحة المصمم العامة `/designer/{id}` ← يرى متجر المصمم الكامل: اسمه، صورته، سيرته، تقييمه، إحصائيات سريعة، شبكة أعماله المنشورة. يمكنه فلترة أعمال المصمم حسب التصنيف. يضغط على أي عمل ← ينتقل إلى صفحة التفاصيل. يضغط [اطلب تصميم] ← تفتح نافذة الطلب.

**المصمم** يدير متجره من `/designer/store` ← يرى معاينة لمتجره كما يراها الزوار. يمكنه: إعادة ترتيب الأعمال بسحب وإفلات (Drag & Drop)، تثبيت أعمال مميزة (Pinned) تظهر في الأعلى مع علامة "مميز"، إخفاء أعمال من المتجر دون حذفها، تعديل إعدادات المتجر.

---

#### عناصر الواجهة

##### Store Settings (SPA) — `/designer/store`

```
┌──────────────────────────────────────────────────┐
│  🏪  إعدادات المتجر                               │
│                                                  │
│  ┌── معاينة المتجر ──────────────────────────┐   │
│  │ أحمد المصمم • 🎨 مصمم جرافيك               │   │
│  │ ⭐ ٤.٨  📦 ١٥ طلب مكتمل                    │   │
│  │                                            │   │
│  │ ┌────┐ ┌────┐ ┌────┐ ┌────┐              │   │
│  │ │مميز│ │عمل٢│ │عمل٣│ │عمل٤│ ← Drag & Drop│   │
│  │ └────┘ └────┘ └────┘ └────┘              │   │
│  └────────────────────────────────────────────┘   │
│                                                  │
│  ⚙️  الإعدادات: وصف المتجر [________]            │
│  [⬜] إخفاء المتجر مؤقتاً                         │
│  [💾 حفظ الإعدادات]                               │
└──────────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/designer/{id}/store` | بيانات المتجر العامة مع الأعمال (SSR) |
| `GET /api/designer/store/settings` | إعدادات المتجر للمصمم الحالي |
| `PUT /api/designer/store/settings` | تحديث إعدادات المتجر |
| `POST /api/designer/store/reorder` | إعادة ترتيب الأعمال (إرسال مصفوفة IDs) |
| `POST /api/designer/store/pin/{workId}` | تثبيت عمل كمميز |
| `DELETE /api/designer/store/pin/{workId}` | إزالة تثبيت |
| `PATCH /api/designer/store/works/{workId}/visibility` | إظهار/إخفاء عمل من المتجر |

##### Frontend

**صفحات:**
- `pages/designer/store.vue` ← SPA
- `pages/designer/[id].vue` ← SSR (من المرحلة 6)

**مكونات:**
- `StorePreview.vue` — معاينة المتجر
- `DraggableWorkList.vue` — قائمة قابلة للسحب (Drag & Drop)
- `StoreSettings.vue` — إعدادات المتجر

---

#### معايير النجاح

- [ ] المصمم يعيد ترتيب أعماله بسحب وإفلات
- [ ] تثبيت/إزالة تثبيت أعمال مميزة
- [ ] إخفاء/إظهار أعمال من المتجر
- [ ] المتجر العام يظهر بالترتيب المحدد + المثبتة أولاً
- [ ] الفلترة حسب التصنيف في المتجر

---

### المرحلة 9 — Orders & Milestones + RFQ (الطلبات والدفعات وطلبات العروض)

> المدة التقديرية: **7 أيام**  
> الاعتماديات: المرحلة 5 (Staff) + المرحلة 8 (Stores)

---

#### نظرياً — تجربة المستخدم

**العميل أو الضيف:** يرى زر [اطلب مشابه] في الخلاصة أو تفاصيل العمل أو متجر المصمم ← يضغط ← تظهر نافذة الطلب. يملأ: وصف ما يريد، نوع الطلب (فوري/حجز مع تاريخ)، معلومات الاتصال (بريد أو هاتف — أحدهما على الأقل). إذا أدخل هاتفاً فقط، يظهر تنبيه "قد يتأخر الطلب للتأكيد اليدوي". بعد الإرسال: يظهر رقم تتبع ورابط لمتابعة الحالة.

**Order Manager:** يدخل `/admin/orders` ← يرى قائمة الطلبات مع فلترة حسب الحالة. للطلبات الجديدة: [تأكيد] (يتصل بالعميل للتحقق) / [تحويل إلى RFQ]. بعد التأكيد: [توجيه لمصمم] ← يختار مصمماً ← يُرسل الإشعار.

**المصمم:** يدخل `/designer/orders` ← يرى الطلبات الموجهة إليه. [قبول] ← يبدأ العمل. [رفض] ← يعود الطلب لمدير الطلبات. بعد الانتهاء: [تسليم] ← يرفع ملفات التسليم.

**Quality Reviewer:** يدخل `/admin/orders/review` ← يفحص التسليم ← [قبول] / [إرجاع للمصمم مع ملاحظات].

**العميل:** يدخل `/client/orders` ← يرى حالة طلبه. بعد التسليم: [مراجعة] ← [اعتماد] / [طلب تعديل] (تعديلان مجانيان). مهلة أسبوع — اعتماد تلقائي.

**الدفعات:** 30% عند بدء العمل، 70% عند اعتماد التسليم. العمولة 20% تُخصم.

---

#### عناصر الواجهة

##### Order Request Modal

```
┌─────────────────────────────────────────────┐
│  📝  طلب تصميم مشابه                         │
│                                             │
│  العمل المرجعي: [معاينة]  المصمم: أحمد      │
│                                             │
│  صف طلبك: [___________________________]    │
│                                             │
│  نوع الطلب:  ○ فوري  ○ حجز                 │
│  تاريخ التسليم: [____] (للحجز)             │
│                                             │
│  📧  البريد  [________]  📞  الهاتف  [____]│
│  (أحدهما على الأقل)                         │
│  ☑ أنا لست برنامجاً                         │
│  [📤 إرسال الطلب]                            │
└─────────────────────────────────────────────┘
```

##### Admin — Order List — `/admin/orders`

```
┌─────────────────────────────────────────────────────┐
│  📦  الطلبات                                         │
│  ┌────┬──────────┬────────┬────────┬────────┬─────┐ │
│  │ #  │ العميل   │ المصمم │ المبلغ │ الحالة │إجراء│ │
│  ├────┼──────────┼────────┼────────┼────────┼─────┤ │
│  │ ١  │ أحمد     │ —      │ ٥٠٠٠٠ │ 🟡 قيد │توجيه│ │
│  │ ٢  │ سارة     │ خالد   │ ٣٠٠٠٠ │ 🔵 جار │متابعة│ │
│  │ ٣  │ ضيف      │ —      │ ٢٠٠٠٠ │ 🟣 يحتاج│اتصال│ │
│  └────┴──────────┴────────┴────────┴────────┴─────┘ │
└─────────────────────────────────────────────────────┘
```

##### Order Timeline

```
┌───── تتبع الطلب ─────┐
│ ✅ تم الإرسال         │
│ ✅ تم التأكيد         │
│ ✅ تم التوجيه لمصمم   │
│ 🔵 جاري التنفيذ      │  ← الحالية
│ ⏳ تم التسليم        │
│ ⏳ الاعتماد          │
└──────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `POST /api/orders` | إنشاء طلب (Client/Guest مع tracking_token) |
| `GET /api/orders` | قائمة الطلبات (حسب الصلاحية) |
| `GET /api/orders/{id}` | تفاصيل الطلب مع Timeline + Milestones |
| `PATCH /api/orders/{id}/verify` | تأكيد الطلب يدوياً |
| `PATCH /api/orders/{id}/assign-designer` | توجيه لمصمم |
| `POST /api/orders/{id}/convert-rfq` | تحويل إلى RFQ |
| `PATCH /api/orders/{id}/milestones/{mid}/pay` | تأكيد صرف دفعة |
| `PATCH /api/orders/{id}/deliver` | تسليم المصمم |
| `PATCH /api/orders/{id}/review-delivery` | فحص التسليم |
| `PATCH /api/orders/{id}/approve-delivery` | قبول التسليم (عميل) |
| `PATCH /api/orders/{id}/request-revision` | طلب تعديل |
| `PATCH /api/orders/{id}/cancel` | إلغاء الطلب |

**موديل Order:**
```php
id, client_name, client_email, client_phone, client_type (guest/client),
tracking_token, type (instant/booking), status,
description, budget, commission_rate (20), commission_amount,
assigned_designer_id, currency, currency_rate,
amount_in_yer, points_equivalent,
service_type, occasion, style, verification_method, soft_deletes
```

**Auto-approve:**
```php
// Command يومي
$orders = Order::where('status', 'delivered')
    ->where('delivered_at', '<', now()->subDays(7))
    ->get();
foreach ($orders as $order) { $order->autoApprove(); }
```

##### Frontend

**صفحات SPA:**
- `pages/admin/orders.vue` + `[id].vue`
- `pages/designer/orders.vue` + `[id].vue`
- `pages/client/orders.vue` + `[id].vue`

**مكونات:**
- `OrderRequestModal.vue`
- `OrderTimeline.vue`
- `OrderAssignModal.vue`
- `MilestoneTracker.vue`
- `DeliveryUploader.vue`
- `DeliveryReviewer.vue`

---

#### معايير النجاح

- [ ] ضيف/عميل ينشئ طلباً مع tracking_token
- [ ] Order Manager يؤكد الطلب ويوجّه لمصمم
- [ ] مصمم يقبل/يرفض طلباً ويُسلم العمل
- [ ] Quality Reviewer يفحص التسليم
- [ ] عميل يعتمد/يطلب تعديلاً
- [ ] Auto-approve بعد أسبوع
- [ ] دفعات 30/70 مع حساب العمولة 20%
- [ ] Off-platform payment مع تأكيد يدوي

---

### المرحلة 10 — Bookings (الحجوزات)

> المدة التقديرية: **4 أيام**  
> الاعتماديات: المرحلة 9 (Orders)

---

#### نظرياً — تجربة المستخدم

**Booking Specialist** يدخل `/admin/bookings` ← يرى كل الحجوزات في المنصة بقائمة ملونة:
- 🟢 **أخضر**: أكثر من 30 يوماً على الموعد
- 🟡 **أصفر**: بين 6–14 يوماً
- 🔴 **أحمر**: 5 أيام أو أقل — إنذار عاجل

يظهر إنذار في الأعلى: "🔴 ٣ حجوزات تحتاج تأكيد عاجل". يستطيع [تأكيد] أو [إلغاء] أو [تعديل الموعد].

تلقائياً: قبل 5 أيام يُرسل تذكير. بعد تجاوز التاريخ دون تأكيد ← إلغاء تلقائي.

---

#### عناصر الواجهة

##### Bookings List — `/admin/bookings`

```
┌───────────────────────────────────────────────────────┐
│  📅  الحجوزات                    [قائمة] [تقويم]      │
│                                                       │
│  🔴 إنذار: ٣ حجوزات تحتاج تأكيد عاجل — أقل من ٥ أيام│
│                                                       │
│  ┌────┬────────┬────────┬──────────┬───────────┬────┐ │
│  │ #  │ العميل │ المصمم │ الموعد   │ الحالة    │لون │ │
│  ├────┼────────┼────────┼──────────┼───────────┼────┤ │
│  │ ١  │ أحمد   │ خالد   │ ١٠-٧     │ 🟡 Tentative│🟡 │ │
│  │ ٢  │ سارة   │ علي    │ ١٥-٦     │ 🔴 إنذار  │🔴 │ │
│  │ ٣  │ نور    │ أحمد   │ ٢٠-٨     │ ✅ مؤكد   │🟢 │ │
│  └────┴────────┴────────┴──────────┴───────────┴────┘ │
└───────────────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/bookings` | قائمة الحجوزات مع فلترة |
| `GET /api/bookings/calendar` | بيانات التقويم |
| `GET /api/bookings/{id}` | تفاصيل الحجز |
| `PATCH /api/bookings/{id}/confirm` | تأكيد الحجز |
| `PATCH /api/bookings/{id}/cancel` | إلغاء الحجز + سبب |
| `PATCH /api/bookings/{id}/reschedule` | تعديل الموعد |

**Scheduled Tasks (يومياً):**
1. حجوزات فيها 5 أيام أو أقل ← إرسال تذكير + تغيير اللون إلى 🔴
2. حجوزات تجاوزت التاريخ بدون تأكيد ← إلغاء تلقائي
3. تحديث الألوان: 30+ ← 🟢, 6-14 ← 🟡, 0-5 ← 🔴

##### Frontend

**صفحات SPA:** `pages/admin/bookings.vue`

**مكونات:** `BookingsTable.vue`, `BookingDetailModal.vue`, `BookingCalendar.vue`

---

#### معايير النجاح

- [ ] عرض الحجوزات مع التدرج اللوني (أخضر/أصفر/أحمر)
- [ ] إنذار للحجوزات الحمراء
- [ ] تأكيد/إلغاء/تعديل موعد الحجز
- [ ] إلغاء تلقائي بعد تجاوز التاريخ
- [ ] تحديث الألوان تلقائياً يومياً

---

### المرحلة 11 — Wallet & Ledger (المحفظة والسجل المالي)

> المدة التقديرية: **5 أيام**  
> الاعتماديات: المرحلة 9 (Orders)

---

#### نظرياً — تجربة المستخدم

**العميل:** يضغط [شراء نقاط] ← يحدد المبلغ بالريال اليمني (أو SAR/USD يُحول تلقائياً) ← يرسل طلب الشحن ← يدفع خارج المنصة ← Finance يؤكد ← تُضاف النقاط.

**المصمم:** يدخل `/designer/wallet` ← يرى الرصيد، آخر الحركات. [سحب] ← يحدد النقاط ← يدخل معلومات الحساب البنكي ← يُرسل. Finance يراجع ← يحوّل المبلغ يدوياً ← يرفع سند التحويل ← تُخصم النقاط.

**Super Admin:** يغيّر سعر النقطة بخطوتين (إدخال السعر الجديد ← تأكيد) ← يُسجّل في Audit Logs ← يُرسل إشعار للجميع.

---

#### عناصر الواجهة

##### Wallet — Designer — `/designer/wallet`

```
┌────────────────────────────────────────────────────┐
│  💰  المحفظة                                         │
│  الرصيد: ٢٥٠٠٠٠ ريال = ١٢٥٠ نقطة  |  قيمة النقطة: ٢٠٠│
│                                                    │
│  [💰 شحن نقاط]  [💸 سحب]                            │
│                                                    │
│  ┌──────┬──────────┬──────────┬────────┬────────┐  │
│  │التاريخ│ البيان   │ المبلغ   │ النقاط │ الحالة │  │
│  ├──────┼──────────┼──────────┼────────┼────────┤  │
│  │١-٦   │ شحن      │ +١٠٠٠٠٠ │ +٥٠٠  │ ✅    │  │
│  │٢٨-٥  │ أرباح طلب│ +٤٠٠٠٠  │ +٢٠٠  │ ✅    │  │
│  │٢٥-٥  │ سحب      │ -١٠٠٠٠٠│ -٥٠٠  │ 🔴 قيد│  │
│  └──────┴──────────┴──────────┴────────┴────────┘  │
└────────────────────────────────────────────────────┘
```

##### Point Price Editor (Super Admin) — خطوتان

```
الخطوة 1: السعر الجديد [________] ريال/نقطة ← [التالي]
الخطوة 2: ⚠️ تأكيد: من ٢٠٠ إلى ٢٥٠ ريال. هذا سيؤثر على الجميع.
          [✅ تأكيد التغيير]  [✖ إلغاء]
```

---

#### المعمارية — الجانب التقني

##### Backend

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/wallet` | الرصيد + آخر الحركات |
| `GET /api/wallet/transactions` | سجل الحركات كامل |
| `POST /api/wallet/charge` | طلب شحن نقاط |
| `GET /api/wallet/withdrawals` | طلبات السحب الخاصة بالمستخدم |
| `POST /api/wallet/withdrawals` | تقديم طلب سحب |
| `GET /api/admin/wallet/withdrawals` | كل طلبات السحب (Finance) |
| `PATCH /api/admin/wallet/withdrawals/{id}/approve` | قبول السحب + رفع سند |
| `PATCH /api/admin/wallet/withdrawals/{id}/reject` | رفض السحب |
| `POST /api/admin/wallet/manual-charge` | شحن يدوي |
| `GET /api/admin/wallet/point-price` | سعر النقطة الحالي |
| `POST /api/admin/wallet/point-price` | تغيير سعر النقطة (خطوة 1) |
| `POST /api/admin/wallet/point-price/confirm` | تأكيد تغيير السعر (خطوة 2) |

**صرف العملات:**
```php
switch ($currency) {
    case 'SAR': $amountInYer = $amount * CurrencyRate::where('from_currency', 'SAR')->first()->rate; break;
    case 'USD': $amountInYer = $amount * CurrencyRate::where('from_currency', 'USD')->first()->rate; break;
    default: $amountInYer = $amount; // YER
}
$points = $amountInYer / PointPrice::current()->price_per_point;
```

##### Frontend

**صفحات SPA:**
- `pages/designer/wallet.vue`
- `pages/client/wallet.vue`
- `pages/admin/finance/withdrawals.vue`
- `pages/admin/finance/point-price.vue`

---

#### معايير النجاح

- [ ] عرض الرصيد + سجل الحركات
- [ ] طلب شحن + تحويل العملات
- [ ] Finance يؤكد الشحن ← تضاف النقاط
- [ ] طلب سحب + مراجعة + تحويل + سند + خصم
- [ ] تغيير سعر النقطة بخطوتين
- [ ] تسجيل التغيير في Audit Logs + إشعار للجميع

---

### المرحلة 12 — Contests & Voting (المسابقات والتصويت)

> المدة التقديرية: **5 أيام**  
> الاعتماديات: المرحلة 7 (Works)

---

#### نظرياً — تجربة المستخدم

**Contest Moderator:** ينشئ مسابقة: اسم، وصف، شروط، جائزة، تاريخ، معايير تقييم (اسم + وزن — مجموعها 100%). يمكنه تعديلها قبل البدء أو تمديدها بعد البدء.

**المصمم:** يزور `/contests` أو يرى بطاقة المسابقة في الـ Feed ← يضغط [مشاركة] ← يرفع عمله.

**الجمهور:** يتصفح المشاركات ← يصوت (CAPTCHA + 3 أصوات فقط). عند الشبهة ← يُطلب إدخال البريد للتحقق. ممنوع التصويت المتكرر.

**بعد الانتهاء:** النظام يجمع تقييمات المقيمين + أصوات الجمهور ← يحسب النتيجة. تعادل ← Super Admin يحسم. تظهر النتائج مع شارات 🥇🥈🥉.

---

#### عناصر الواجهة

##### Contest Detail (SSR) — `/contests/{id}`

```
┌──────────────────────────────────────────────────┐
│  🏆  مسابقة تصميم شعار السنة                      │
│  الوصف | الجائزة: 🥇 ١٠٠٠٠٠٠ ﷼ | متبقي ٥ أيام   │
│  ⭐ معايير: الإبداع ٣٠٪، التنفيذ ٤٠٪، المطابقة ٣٠٪│
│                                                    │
│  ─── المشاركات (٢٣) ───                           │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐            │
│  │[غلاف]│ │[غلاف]│ │[غلاف]│ │[غلاف]│            │
│  │مصمم ١│ │مصمم ٢│ │مصمم ٣│ │مصمم ٤│            │
│  │ ⭐ ٤٥٪│ │ ⭐ ٣٢٪│ │ ⭐ ١٢٪│ │ ⭐ ١١٪│            │
│  └──────┘ └──────┘ └──────┘ └──────┘            │
│                                                    │
│  [📤 مشاركة (للمصممين)]                            │
└──────────────────────────────────────────────────┘
```

##### Admin — Contest Form — `/admin/contests/new`

```
الاسم | الوصف | الشروط | الجائزة | تاريخ البداية والنهاية
معايير التقييم: معيار ١ [الإبداع] الوزن [٣٠]٪ — [+ إضافة معيار]
(يجب مجموع الأوزان = ١٠٠٪)
[📢 نشر المسابقة]
```

---

#### المعمارية — الجانب التقني

##### Backend

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/contests` | قائمة المسابقات (SSR) |
| `GET /api/contests/{id}` | تفاصيل المسابقة + المشاركات + الأصوات |
| `POST /api/admin/contests` | إنشاء مسابقة |
| `PUT /api/admin/contests/{id}` | تعديل مسابقة |
| `PATCH /api/admin/contests/{id}/extend` | تمديد مدة المسابقة |
| `POST /api/contests/{id}/submit` | مشاركة (مصمم) |
| `POST /api/contests/{id}/vote/{submissionId}` | تصويت (CAPTCHA + تحقق) |
| `POST /api/admin/contests/{id}/evaluate` | تقييم مقيم |
| `GET /api/contests/{id}/results` | النتائج |
| `POST /api/admin/contests/{id}/finalize` | اعتماد النتائج |
| `POST /api/admin/contests/{id}/resolve-tie` | حسم تعادل (Super Admin) |

**نظام التصويت — Anti-Fraud:**
```php
// 3 votes per contest per IP
$voteCount = ContestVote::where('contest_id', $contestId)
    ->where('voter_ip', $request->ip())->count();
if ($voteCount >= 3) throw new Exception('استنفدت أصواتك');

// Suspicious activity
if ($voteCount > 1 && timeSinceLastVote < 5) {
    // طلب تحقق بريد
}
```

**حساب النتائج:**
```php
foreach ($submissions as $submission) {
    $evaluatorScore = $submission->scores->avg('total_score'); // 0-100
    $publicVoteScore = ($submission->votes->count() / $maxVotes) * 100;
    $finalScore = ($evaluatorScore * 0.7) + ($publicVoteScore * 0.3);
}
```

##### Frontend

**صفحات SSR:** `pages/contests/index.vue`, `pages/contests/[id].vue`
**صفحات SPA:** `pages/admin/contests.vue`, `pages/admin/contests/new.vue`, `pages/admin/contests/[id].vue`

**مكونات:**
- `ContestCard.vue` — بطاقة مسابقة في الـ Feed
- `ContestDetail.vue`
- `ContestForm.vue`
- `ContestSubmissionCard.vue`
- `VoteButton.vue` — مع CAPTCHA
- `EvaluationForm.vue`
- `ContestResults.vue` — مع شارات 🥇🥈🥉
- `TieBreakerModal.vue`

---

#### معايير النجاح

- [ ] Contest Moderator ينشئ مسابقة مع معايير تقييم
- [ ] مصمم يشارك في مسابقة (يرفع عملاً)
- [ ] تصويت الجمهور: 3 أصوات + CAPTCHA
- [ ] Anti-Fraud: منع تكرار + تحقق بريد عند الشبهة
- [ ] المقيمون يقيمون المشاركات
- [ ] حساب النتائج تلقائياً (70% مقيمين + 30% جمهور)
- [ ] حسم التعادل يدوياً
- [ ] إعلان الفائزين بالمراكز الثلاث
- [ ] بطاقات المسابقات تظهر في الـ Feed الرئيسي

---

### المرحلة 13 — Notifications Center + Manual Admin Notifications (مركز الإشعارات والإشعارات الإدارية)

> المدة التقديرية: **4 أيام**  
> الاعتماديات: المرحلة 0 (App Shell — NotificationDropdown) + Redis Queue

---

#### نظرياً — تجربة المستخدم

**مركز الإشعارات:** كل مستخدم لديه مركز إشعارات كامل في `/notifications`. يدخل فيرى كل إشعاراته مرتبة من الأحدث للأقدم. كل إشعار يحتوي: أيقونة (حسب النوع)، عنوان واضح، وصف مختصر، الوقت (نسبي + تاريخ كامل عند التمرير)، ورابط يأخذه إلى الصفحة المرتبطة.

يستطيع فلترة: [الكل] [غير المقروءة] [المقروءة] وترتيب: [الأحدث] [الأقدم]. أزرار: [تحديد الكل كمقروء] و[حذف الكل]. الإشعارات الجديدة تظهر فوراً في Top Bar مع أيقونة الجرس + نقطة حمراء + العداد. عند الضغط على الجرس ← Dropdown يظهر آخر 5 إشعارات غير مقروءة مع رابط [فتح مركز الإشعارات].

الإشعارات فورية — ترسل عبر Redis Queue.

**الإشعارات الإدارية اليدوية:** الأدمن أو أي موظف لديه صلاحية يستطيع إرسال إشعار أو رسالة إدارية إلى فئة محددة من المستخدمين. مثال: إرسال تحديث للمصممين، تنبيه لفريق الطلبات، رسالة للعملاء، تعليمات لموظفي المحتوى، إعلان عام لكل المستخدمين.

الأدمن يكتب العنوان، نص الرسالة، يحدد المستلمين، يحدد نوع الإشعار، ثم يرسل. تصل الرسالة كإشعار داخل المنصة.

---

#### عناصر الواجهة

##### Notifications Center (SPA) — `/notifications`

```
┌──────────────────────────────────────────────────┐
│  🔔  مركز الإشعارات                               │
│  [الكل] [غير المقروءة] [المقروءة]  [الأحدث ▼]    │
│  [✅ تحديد الكل كمقروء]  [🗑 حذف الكل]            │
│                                                    │
│  ┌────────────────────────────────────────────┐   │
│  │ ✅ تم تأكيد طلبك رقم #١٥٣                  │   │
│  │    تم تأكيد طلب التصميم. المصمم خالد       │   │
│  │    🕐 الخميس ١٠-٦ — ٢:٣٠ م  [تفاصيل →]   │   │
│  ├────────────────────────────────────────────┤   │
│  │ 🆕 عمل جديد قيد المراجعة                   │   │
│  │    منذ ٥ دقائق  [تفاصيل →]                │   │
│  ├────────────────────────────────────────────┤   │
│  │ 💰 تم شحن ١٠٠٠٠٠ ريال إلى محفظتك           │   │
│  │    منذ ساعة  [تفاصيل →]                    │   │
│  └────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────┘
```

##### Admin Send Notification — `/admin/notifications/send`

```
┌──────────────────────────────────────────────┐
│  📨 إرسال إشعار إداري                        │
│                                              │
│  العنوان: [________________________]         │
│                                              │
│  الرسالة:                                    │
│  [____________________________________]      │
│                                              │
│  المستلمون:                                  │
│  ○ الجميع                                    │
│  ○ المصممون                                  │
│  ○ العملاء                                   │
│  ○ فريق الطلبات                              │
│  ○ فريق المالية                              │
│  ○ أدوار محددة [اختر ▼]                      │
│  ○ مستخدمون محددون [اختر ▼]                  │
│                                              │
│  النوع: [تحديث ▼]   الأولوية: [عادية ▼]     │
│                                              │
│  المستلمون المتوقعون: ١٢٤ مستخدماً           │
│                                              │
│  [إرسال]                                     │
└──────────────────────────────────────────────┘
```

##### Sent Notifications Log — `/admin/notifications/sent`

```
┌──────────────────────────────────────────────┐
│  📋 سجل الإشعارات الإدارية                   │
│                                              │
│  ┌────┬────────┬──────────┬────────┬────────┐│
│  │ #  │ العنوان│ المستلم  │ المرسل │ الوقت  ││
│  ├────┼────────┼──────────┼────────┼────────┤│
│  │ 1  │ تحديث  │ مصممين  │ Admin  │ اليوم  ││
│  │ 2  │ تنبيه  │ مالية    │ Admin  │ أمس    ││
│  └────┴────────┴──────────┴────────┴────────┘│
└──────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/notifications` | الإشعارات (فلترة + Pagination) |
| `GET /api/notifications/unread-count` | عدد غير المقروءة |
| `PATCH /api/notifications/{id}/read` | تحديد مقروء |
| `PATCH /api/notifications/read-all` | كلها مقروءة |
| `DELETE /api/notifications/{id}` | حذف إشعار |
| `DELETE /api/notifications` | حذف الكل |
| `POST /api/admin/notifications/send` | إرسال إشعار إداري |
| `GET /api/admin/notifications/sent` | سجل الإشعارات المرسلة |
| `GET /api/admin/notifications/audience-options` | خيارات المستلمين المتاحة |
| `POST /api/admin/notifications/preview-count` | عدد المستلمين قبل الإرسال |

**إرسال عبر Queue:**
```php
Notification::send($user, new OrderConfirmedNotification($order))
    ->onQueue('notifications');  // → Redis Queue
```

**موديل AdminNotification:**
```php
admin_notifications:
id, sender_id, title, body, audience_type,
audience_filter(JSON), priority, type, sent_count, created_at
```

**أنواع الجمهور:**
| النوع | الوصف |
|-------|-------|
| `all` | كل المستخدمين |
| `role` | دور محدد (مثلاً designers, clients) |
| `permission` | صلاحية محددة |
| `specific_users` | مستخدمين محددين IDs |
| `designers` | كل المصممين |
| `clients` | كل العملاء |
| `staff` | كل الموظفين |
| `order_team` | فريق الطلبات |
| `finance_team` | فريق المالية |
| `content_team` | فريق المحتوى |
| `contest_team` | فريق المسابقات |

##### Frontend

**صفحات SPA:**
- `pages/notifications/index.vue` — مركز الإشعارات
- `pages/admin/notifications/send.vue` — إرسال إشعار إداري
- `pages/admin/notifications/sent.vue` — سجل الإشعارات المرسلة

**مكونات:**
- `NotificationCenter.vue` — صفحة كاملة
- `NotificationDropdown.vue` — من App Shell
- `NotificationBell.vue` — جرس + عداد
- `NotificationItem.vue` — عنصر إشعار واحد
- `AdminNotificationForm.vue` — نموذج إرسال إشعار إداري
- `AudienceSelector.vue` — اختيار المستلمين مع معاينة العدد
- `SentNotificationsTable.vue` — سجل الإشعارات المرسلة

---

#### معايير النجاح

- [ ] مركز إشعارات كامل مع فلترة وترتيب
- [ ] العداد في Top Bar يُحدّث فورياً
- [ ] Dropdown يظهر آخر الإشعارات غير المقروءة
- [ ] تحديد الكل كمقروء / حذف الكل
- [ ] الضغط على إشعار ينتقل إلى الصفحة المرتبطة
- [ ] إشعارات فورية عبر Redis Queue
- [ ] الأدمن يرسل إشعاراً للجميع أو لدور محدد أو لمستخدمين محددين
- [ ] معاينة عدد المستلمين قبل الإرسال
- [ ] الإرسال يسجل في Audit Logs
- [ ] سجل الإشعارات الإدارية مع إمكانية الرجوع إليه

---

### المرحلة 14 — Analytics & Reports (التحليلات والتقارير)

> المدة التقديرية: **4 أيام**  
> الاعتماديات: المرحلة 13 (Notifications)

---

#### نظرياً — تجربة المستخدم (Super Admin)

يدخل `/admin/reports` ← يختار نوع التقرير: [الطلبات] [المستخدمين] [الإيرادات] [المحتوى] [المسابقات]. يختار الفترة: [اليوم] [الأسبوع] [الشهر] [السنة] [من] [إلى]. يظهر له: بطاقات KPI، رسم بياني SVG، جدول تفصيلي.

**الفرق: Analytics Events = أحداث كمية للرسوم البيانية. Audit Logs = أحداث نوعية حساسة للتدقيق.**

---

#### عناصر الواجهة

##### Reports — `/admin/reports`

```
┌──────────────────────────────────────────────────┐
│  📊  التقارير                                     │
│  [الطلبات] [المستخدمين] [الإيرادات] [المحتوى]    │
│  [اليوم] [الأسبوع] [الشهر] [السنة] [من ▼] [إلى ▼]│
│                                                    │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐            │
│  │ ٤٥   │ │ ٢٣   │ │ ١٢   │ │ ١٠٠٠ │            │
│  │إجمالي│ │جديد  │ │مكتمل │ │إيراد │            │
│  └──────┘ └──────┘ └──────┘ └──────┘            │
│                                                    │
│  ┌────────────────────────────────────────────┐   │
│  │  📈 رسم بياني SVG + جدول تفصيلي             │   │
│  └────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/admin/reports/{type}?period=month&from=...&to=...` | بيانات تقرير معين |

**موديل AnalyticsEvent:**
```php
id, type (visit, view, like, order, booking, contest_submission, etc.),
user_id (nullable), metadata (JSON), created_at
```

**حساب التقارير — PostgreSQL Aggregation:**
```php
$report = Order::selectRaw("DATE(created_at) as date, COUNT(*) as total")
    ->whereBetween('created_at', [$from, $to])
    ->groupBy('date')->orderBy('date')->get();
```

##### Frontend

**صفحات SPA:** `pages/admin/reports.vue`

**مكونات:** `ReportTabs.vue`, `PeriodFilter.vue`, `StatsChart.vue` (من المرحلة 2), `ReportTable.vue`, `KpiCards.vue`

---

#### معايير النجاح

- [ ] اختيار نوع التقرير + فلترة الفترة
- [ ] KPIs + SVG Charts + جدول تفصيلي
- [ ] بيانات صحيحة من Analytics Events
- [ ] فصل تام عن Audit Logs

---

### المرحلة 15 — Audit Logs (سجل التدقيق)

> المدة التقديرية: **2 يوم**  
> الاعتماديات: المرحلة 5 (Staff)

---

#### نظرياً — تجربة المستخدم (Super Admin)

يدخل `/admin/audit-logs` ← يرى سجلاً زمنياً بكل العمليات الحساسة: من فعل ماذا ومتى ومن أي IP. للقراءة فقط — لا تعديل ولا حذف.

يفلتر: حسب المستخدم، الإجراء، التاريخ.

---

#### عناصر الواجهة

##### Audit Logs — `/admin/audit-logs`

```
┌──────────────────────────────────────────────────────────┐
│  📋  سجل التدقيق                                          │
│  [المستخدم: الكل ▼]  [الإجراء: الكل ▼]  [من ▼] [إلى ▼]  │
│                                                          │
│  ┌──────────┬──────────┬──────────┬────────────┬────────┐│
│  │ الوقت    │ المستخدم │ الإجراء  │ التفاصيل   │ IP     ││
│  ├──────────┼──────────┼──────────┼────────────┼────────┤│
│  │١٠:٣٠    │ أحمد M  │💰تغيير │ ٢٠٠ → ٢٥٠  │ x.x.x ││
│  │          │          │سعر النقطة│ ريال/نقطة  │        ││
│  │٠٩:١٥    │ سارة F  │💳اعتماد │ سحب #٥     │ x.x.x ││
│  │          │          │سحب      │            │        ││
│  └──────────┴──────────┴──────────┴────────────┴────────┘│
└──────────────────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/admin/audit-logs` | سجل التدقيق (بحث + فلترة + Pagination) |

**موديل AuditLog — للقراءة فقط:**
```php
id, user_id, action (enum), description, metadata (JSON), ip_address, user_agent, created_at
// No update, no delete, no soft delete
```

**العمليات التي تُسجّل:**
- تغيير سعر النقطة
- اعتماد/رفض طلب سحب
- تعديل صلاحيات الدور
- حسم تعادل في مسابقة
- اعتماد نتائج مسابقة
- تعديل إعدادات المنصة

##### Frontend

**صفحات SPA:** `pages/admin/audit-logs.vue`
**مكونات:** `AuditLogTable.vue`, `AuditLogFilter.vue`

---

#### معايير النجاح

- [ ] عرض جميع السجلات الزمنية
- [ ] فلترة حسب المستخدم والإجراء والتاريخ
- [ ] للقراءة فقط — لا تعديل ولا حذف
- [ ] تسجيل تلقائي لكل عملية حساسة
- [ ] SearchToolbar يعمل مع الفلترة

---

### المرحلة 16 — Admin Control Center & Settings (مركز التحكم والإعدادات)

> المدة التقديرية: **6 أيام**  
> الاعتماديات: المرحلة 4 (Roles) + المرحلة 7 (Works) + المرحلة 9 (Orders) + المرحلة 11 (Wallet) + المرحلة 12 (Contests)

---

#### نظرياً — تجربة المستخدم

الأدمن أو Super Admin يجب أن يتحكم في المنصة بالكامل من الواجهة، بدون الرجوع لتعديل الكود. الهدف أن تكون المنصة قابلة للتخصيص والتوسع مستقبلاً. أي مسمى، أي أيقونة، أي تصنيف، أي دور، أي صلاحية، أي لون، أي قيمة تشغيلية، يمكن تعديلها من لوحة التحكم.

الأدمن يدخل `/admin/settings` ويرى مركز تحكم مقسم إلى أقسام واضحة. يستطيع تعديل إعدادات المنصة العامة، إعدادات الصفحة الرئيسية، التصنيفات، الأيقونات، الأدوار، الصلاحيات، الحدود، الاشتراكات، الأعمال، الطلبات، المسابقات، الإشعارات، الألوان، النصوص، القيم المالية، وإعدادات الحماية.

---

#### عناصر الواجهة

##### Settings Home — `/admin/settings`

```
┌────────────────────────────────────────────────────┐
│  ⚙️ مركز التحكم                                    │
│                                                    │
│  [عام] [الواجهة] [الألوان] [الأيقونات]             │
│  [التصنيفات] [الأدوار] [الصلاحيات]                 │
│  [الأعمال] [الطلبات] [المسابقات]                  │
│  [المالية] [الإشعارات] [الحماية] [النسخ]           │
│                                                    │
│  اختر القسم الذي تريد إدارته.                       │
└────────────────────────────────────────────────────┘
```

##### Labels & Names

```
┌────────────────────────────────────────────────────┐
│  🏷️ المسميات                                      │
│                                                    │
│  اسم القسم: الطلبات                                │
│  الاسم العربي: [الطلبات_________]                  │
│  الاسم الإنجليزي: [Orders________]                 │
│  الأيقونة: [📦]                                    │
│  اللون: [#4F46E5]                                  │
│                                                    │
│  [حفظ]                                             │
└────────────────────────────────────────────────────┘
```

##### Icons Manager

```
┌────────────────────────────────────────────────────┐
│  🎭 إدارة الأيقونات                                │
│                                                    │
│  Dashboard      [📊]                                │
│  Users          [👥]                                │
│  Orders         [📦]                                │
│  Bookings       [📅]                                │
│  Wallet         [💰]                                │
│  Contests       [🏆]                                │
│                                                    │
│  [حفظ الأيقونات]                                   │
└────────────────────────────────────────────────────┘
```

##### Categories Manager

```
┌────────────────────────────────────────────────────┐
│  🏷️ التصنيفات                                     │
│                                                    │
│  أنواع الخدمة                                      │
│  [لوجو] [موشن] [هوية] [مونتاج] [+ إضافة]           │
│                                                    │
│  المناسبات                                         │
│  [عيد] [زواج] [مؤتمر] [إطلاق منتج] [+ إضافة]       │
│                                                    │
│  الستايلات                                         │
│  [مودرن] [كلاسيك] [مينيمال] [+ إضافة]              │
│                                                    │
│  [حفظ]                                             │
└────────────────────────────────────────────────────┘
```

##### Roles & Permissions Customization

```
┌────────────────────────────────────────────────────┐
│  🔐 تخصيص الأدوار والصلاحيات                       │
│                                                    │
│  الدور: Content Manager                            │
│  الاسم العربي: [مدير المحتوى________]              │
│  الأيقونة: [🎨]                                    │
│  اللون: [#EC4899]                                  │
│                                                    │
│  الصلاحيات                                         │
│  ☑ مراجعة الأعمال                                  │
│  ☑ إدارة البلاغات                                  │
│  ☐ إدارة الطلبات                                   │
│  ☐ إدارة المالية                                   │
│                                                    │
│  [حفظ]                                             │
└────────────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend APIs

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/admin/settings` | جلب كل إعدادات المنصة |
| `PUT /api/admin/settings/general` | تحديث الإعدادات العامة |
| `PUT /api/admin/settings/labels` | تحديث المسميات |
| `PUT /api/admin/settings/icons` | تحديث الأيقونات |
| `PUT /api/admin/settings/theme` | تحديث الألوان |
| `GET /api/admin/categories` | جلب التصنيفات |
| `POST /api/admin/categories` | إضافة تصنيف |
| `PUT /api/admin/categories/{id}` | تعديل تصنيف |
| `DELETE /api/admin/categories/{id}` | حذف أو تعطيل تصنيف |
| `PUT /api/admin/roles/{id}/display` | تعديل اسم/أيقونة/لون الدور |
| `PUT /api/admin/permissions/{id}/display` | تعديل مسمى الصلاحية |
| `PUT /api/admin/settings/homepage` | إعدادات الصفحة الرئيسية |
| `PUT /api/admin/settings/comments` | تفعيل/تعطيل التعليقات |
| `PUT /api/admin/settings/security` | إعدادات CAPTCHA و Rate Limits |

##### Models

```php
settings:
id, key, value, type, group, is_sensitive, updated_by, updated_at

categories:
id, type(service/occasion/style), name_ar, name_en, slug, icon, color, is_active, sort_order

role_display_settings:
id, role_name, label_ar, label_en, icon, color

permission_display_settings:
id, permission_name, label_ar, label_en, group, icon

ui_sections:
id, key, label_ar, label_en, icon, color, is_visible, sort_order
```

##### Frontend

**صفحات SPA:** `pages/admin/settings.vue`

**مكونات:**
- `SettingsHome.vue` — الصفحة الرئيسية لمركز التحكم
- `LabelsManager.vue` — إدارة المسميات
- `IconsManager.vue` — إدارة الأيقونات
- `CategoriesManager.vue` — إدارة التصنيفات
- `RolesDisplayEditor.vue` — تخصيص مظهر الأدوار
- `ThemeSettings.vue` — إعدادات الألوان
- `SecuritySettings.vue` — إعدادات الحماية

---

#### معايير النجاح

- [ ] الأدمن يعدل مسميات الأقسام من الواجهة
- [ ] الأدمن يعدل أيقونات الأقسام والأدوار
- [ ] الأدمن يضيف ويحذف ويعطل التصنيفات
- [ ] الأدمن يضيف أدوار وصلاحيات ويعدل مسمياتها
- [ ] لا توجد قيم تشغيلية مهمة hardcoded في الكود
- [ ] كل تعديل حساس يسجل في Audit Logs
- [ ] الواجهة تقرأ المسميات والأيقونات من الإعدادات

---

### المرحلة 17 — Subscriptions & Plans (الاشتراكات والباقات)

> المدة التقديرية: **5 أيام**  
> الاعتماديات: المرحلة 11 (Wallet)

---

#### نظرياً — تجربة المستخدم

**المصمم:** يدخل `/designer/subscriptions` ← يرى الباقات المتاحة (مجاني محدود بـ 20 عملاً، وباقات مدفوعة بعدد أعمال غير محدود ومزايا إضافية). يختار باقة ← يدفع عبر المحفظة أو تحويل خارجي ← Finance يؤكد ← يُفعّل الاشتراك.

**Super Admin:** يدخل `/admin/subscriptions/plans` ← ينشئ باقات جديدة، يعدل الأسعار، يحدد المزايا، يفعل/يعطل باقة. يدخل `/admin/subscriptions/requests` ← يرى طلبات الاشتراك المعلقة ويؤكدها أو يرفضها.

##### Plans List — `/admin/subscriptions/plans`

```
┌──────────────────────────────────────────────────────────┐
│  📋  باقات الاشتراك                      [إضافة باقة ＋]   │
│                                                          │
│  ┌────┬──────────┬────────┬──────────┬────────┬────────┐ │
│  │ #  │ الاسم    │ السعر  │ حد الأعمال│ المزايا │ الحالة│ │
│  ├────┼──────────┼────────┼──────────┼────────┼────────┤ │
│  │ ١  │ مجاني    │ ٠      │ ٢٠       │ أساسية │ 🟢    │ │
│  │ ٢  │ محترف    │ ٥٠٠٠٠  │ غير محدود│ ⭐مميزة│ 🟢    │ │
│  │ ٣  │ أقصى     │ ١٠٠٠٠٠ │ غير محدود│ 💎كل شيء│ 🟢    │ │
│  └────┴──────────┴────────┴──────────┴────────┴────────┘ │
└──────────────────────────────────────────────────────────┘
```

##### Subscription Requests — `/admin/subscriptions/requests`

```
┌──────────────────────────────────────────────────────────────┐
│  📋  طلبات الاشتراك                                          │
│  ┌────┬──────────┬──────────┬────────┬──────────┬──────────┐ │
│  │ #  │ المصمم   │ الباقة   │ المبلغ │ الحالة   │ إجراء   │ │
│  ├────┼──────────┼──────────┼────────┼──────────┼──────────┤ │
│  │ ١  │ أحمد     │ محترف   │ ٥٠٠٠٠  │ 🟡 قيد   │ تأكيد   │ │
│  │ ٢  │ سارة     │ أقصى     │ ١٠٠٠٠٠│ ✅ مقبول │ —       │ │
│  └────┴──────────┴──────────┴────────┴──────────┴──────────┘ │
└──────────────────────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend APIs

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `GET /api/subscriptions/plans` | الباقات المتاحة |
| `POST /api/subscriptions/subscribe` | طلب اشتراك |
| `GET /api/subscriptions/my` | اشتراكاتي |
| `GET /api/admin/subscriptions/plans` | إدارة الباقات |
| `POST /api/admin/subscriptions/plans` | إنشاء باقة |
| `PUT /api/admin/subscriptions/plans/{id}` | تعديل باقة |
| `PATCH /api/admin/subscriptions/plans/{id}/toggle` | تفعيل/تعطيل |
| `GET /api/admin/subscriptions/requests` | طلبات الاشتراك |
| `PATCH /api/admin/subscriptions/requests/{id}/approve` | قبول طلب |
| `PATCH /api/admin/subscriptions/requests/{id}/reject` | رفض طلب |

##### Models

```php
subscription_plans:
id, name, price, works_limit, features(JSON), is_active

subscriptions:
id, user_id, plan_id, starts_at, ends_at, status(pending/active/expired/cancelled)

subscription_requests:
id, user_id, plan_id, amount_yer, status(pending/approved/rejected), payment_reference, reviewed_by, reviewed_at
```

##### Frontend

**صفحات SPA:** `pages/designer/subscriptions.vue`, `pages/admin/subscriptions/plans.vue`, `pages/admin/subscriptions/requests.vue`

**مكونات:** `PlansGrid.vue`, `PlanCard.vue`, `SubscribeModal.vue`, `RequestsTable.vue`

---

#### معايير النجاح

- [ ] المصمم يرى الباقات والأسعار
- [ ] المصمم يطلب اشتراكاً عبر المحفظة
- [ ] Finance يؤكد الاشتراك ويُفعّل
- [ ] بعد التفعيل، حد الـ 20 عمل يُرفع
- [ ] Super Admin يدير الباقات (إضافة/تعديل/تعطيل)
- [ ] انتهاء الاشتراك يُرجِع الحد إلى 20

---

### المرحلة 18 — Disputes Management (إدارة النزاعات)

> المدة التقديرية: **4 أيام**  
> الاعتماديات: المرحلة 9 (Orders)

---

#### نظرياً — تجربة المستخدم

**العميل أو المصمم:** يرفع نزاعاً على طلب معين من صفحة تفاصيل الطلب. يختار سبب النزاع (تسليم متأخر، جودة غير مطابقة، عدم التزام بالوصف، إلخ)، ويكتب وصفاً تفصيلياً.

**Dispute Manager:** يدخل `/admin/disputes` ← يرى قائمة النزاعات المفتوحة. يفتح نزاعاً ← يرى تفاصيل الطلب، أدلة الطرفين، محادثة النزاع. يقرر: [رفض النزاع] / [قبول النزاع مع تعويض] / [حل وسط]. القرار يُسجّل ويُرسل إشعار للطرفين.

**Super Admin:** يمكنه حسم النزاعات المستعصية أو مراجعة قرارات Dispute Manager.

##### Disputes List — `/admin/disputes`

```
┌──────────────────────────────────────────────────────────┐
│  ⚖️ النزاعات                                            │
│  ┌────┬──────────┬──────────┬──────────┬────────┬──────┐ │
│  │ #  │ الطلب    │ مقدم    │ ضد       │ السبب  │الحالة│ │
│  ├────┼──────────┼──────────┼──────────┼────────┼──────┤ │
│  │ ١  │ #٢٠١    │ أحمد    │ خالد     │ تأخير  │ 🔴   │ │
│  │ ٢  │ #٢٠٥    │ سارة    │ علي      │ جودة   │ 🟡   │ │
│  └────┴──────────┴──────────┴──────────┴────────┴──────┘ │
└──────────────────────────────────────────────────────────┘
```

##### Dispute Resolution Modal

```
┌──────────────────────────────────────────────┐
│  ⚖️ حل النزاع #١                            │
│                                              │
│  تفاصيل الطلب: #٢٠١ — تصميم لوجو             │
│  مقدم: أحمد  |  ضد: خالد                     │
│  السبب: تسليم متأخر ٥ أيام                   │
│                                              │
│  قرارك:                                      │
│  ○ رفض النزاع (الطلب مكتمل حسب الاتفاق)      │
│  ○ قبول النزاع (تعويض ٢٠٪ للعميل)            │
│  ○ حل وسط (تعويض ١٠٪ + تمديد ٣ أيام)         │
│                                              │
│  ملاحظات: [__________________________]       │
│                                              │
│  [إصدار القرار]                              │
└──────────────────────────────────────────────┘
```

---

#### المعمارية — الجانب التقني

##### Backend APIs

| الـ Endpoint | الوظيفة |
|-------------|---------|
| `POST /api/disputes` | رفع نزاع (Client/Designer) |
| `GET /api/disputes` | نزاعاتي |
| `GET /api/admin/disputes` | كل النزاعات (Admin) |
| `GET /api/admin/disputes/{id}` | تفاصيل النزاع مع الطلب |
| `POST /api/admin/disputes/{id}/resolve` | إصدار قرار |

##### Models

```php
disputes:
id, order_id, raised_by, against_id, reason, description,
status(open/resolved), resolution, resolved_by, resolved_at

dispute_decisions:
id, dispute_id, decision_label, description, compensation_type,
compensation_amount, decided_by, created_at
```

##### Frontend

**صفحات SPA:** `pages/admin/disputes.vue`, `pages/admin/disputes/[id].vue`, (نموذج رفع نزاع داخل `pages/client/orders/[id].vue` و `pages/designer/orders/[id].vue`)

**مكونات:** `DisputesTable.vue`, `DisputeDetail.vue`, `DisputeResolutionModal.vue`, `RaiseDisputeModal.vue`

---

#### معايير النجاح

- [ ] عميل/مصمم يرفع نزاعاً على طلب
- [ ] Dispute Manager يرى كل النزاعات المفتوحة
- [ ] إصدار قرار (رفض/قبول/حل وسط) مع تعويض
- [ ] القرار يُسجّل في Audit Logs
- [ ] الطرفين يستلمان إشعاراً بالقرار
- [ ] النزاعات المغلقة تُؤرشَف للرجوع إليها

---

### Operations — Backup & Restore (النسخ الاحتياطي والاستعادة)

> المدة التقديرية: **2 يوم**  
> النوع: تشغيلي — ليس مرحلة بناء منتج  
> الاعتماديات: بعد اكتمال البنية التحتية (PostgreSQL + Redis + Storage)

---

#### السياسة

| النوع | التكرار | التخزين |
|-------|---------|---------|
| **Daily Backup** | يومياً | Off-server |
| **Weekly Backup** | أسبوعياً | Off-server |
| **Monthly Backup** | شهرياً | تخزين طويل الأمد |

#### ما يتم نسخه

- PostgreSQL database (Full dump)
- ملفات التخزين (Stored media, uploads, transfer receipts)
- بيانات الوسائط metadata
- إعدادات المنصة (Settings model)
- **لا** يتم حفظ `.env` الخام في النسخ العامة

#### واجهة الأدمن

```
┌──────────────────────────────────────────────┐
│  💾 النسخ الاحتياطي                           │
│                                              │
│  آخر نسخة يومية: ✅ اليوم 03:00               │
│  آخر نسخة أسبوعية: ✅ الأحد                  │
│  آخر نسخة شهرية: ✅ 1 يونيو                  │
│                                              │
│  [تشغيل نسخة الآن] [اختبار استعادة]          │
└──────────────────────────────────────────────┘
```

#### Scheduled Commands

```bash
php artisan backup:run          # تشغيل نسخة فورية
php artisan backup:daily        # النسخة اليومية المجدولة
php artisan backup:status       # حالة آخر النسخ
php artisan backup:test-restore # اختبار استعادة (بيئة اختبار)
php artisan backup:clean        # تنظيف النسخ القديمة
```

---

#### معايير النجاح

- [ ] نسخ يومي/أسبوعي/شهري تلقائي
- [ ] التخزين خارج السيرفر (Off-server)
- [ ] إشعار عند فشل النسخ (إشعار داخلي + بريد)
- [ ] اختبار استعادة شهري (بيئة اختبار)
- [ ] Super Admin يرى حالة النسخ من الواجهة
- [ ] لا يتم تخزين أسرار خام بشكل غير آمن

---

## 7. ملخص — الجدول الزمني التقديري النهائي

| المرحلة | الاسم | الأيام | الاعتماديات |
|---------|-------|--------|-------------|
| تمهيدية | تجهيز البيئة | قبل البناء | لا شيء |
| 0 | App Shell | 5 | لا شيء (إلزامية قبل أي صفحة) |
| 1 | Authentication | 3 | المرحلة 0 |
| 2 | Dashboard Core المتقدم | 5 | المرحلة 1 |
| 3 | Users Management | 3 | المرحلة 2 |
| 4 | Roles & Permissions | 3 | المرحلة 3 |
| 5 | Staff Management | 2 | المرحلة 4 |
| 6 | Designer Profiles | 3 | المرحلة 5 |
| 7 | Works + Public Homepage Feed + Comments Toggle | 8 | المرحلة 6 + FFmpeg |
| 8 | Stores | 3 | المرحلة 7 |
| 9 | Orders & Milestones + RFQ | 7 | المرحلة 5 + 8 |
| 10 | Bookings | 4 | المرحلة 9 |
| 11 | Wallet & Ledger | 5 | المرحلة 9 |
| 12 | Contests & Voting | 5 | المرحلة 7 |
| 13 | Notifications Center + Manual Admin Notifications | 4 | المرحلة 0 + Redis Queue |
| 14 | Analytics & Reports | 4 | المرحلة 13 |
| 15 | Audit Logs | 2 | المرحلة 5 |
| 16 | Admin Control Center & Settings | 6 | المرحلة 4 + 7 + 9 + 11 + 12 |
| 17 | Subscriptions & Plans | 5 | المرحلة 11 |
| 18 | Disputes Management | 4 | المرحلة 9 |
| تشغيلي | Backup & Restore | 2 | بعد اكتمال البنية التحتية |
| | **المجموع** | **~78 يوم** | |

---

## 8. ملاحظات ختامية

- هذا الملف هو **المرجع الوحيد المعتمد** لبناء منصة Yemen Motion (Web + Mobile API)
- جميع القرارات المعمارية والتقنية نهائية ولا تُغيّر إلا بمراجعة كاملة
- الـ API Architecture متوافقة مع تطبيقات الهاتف الذكي — كل الـ endpoints جاهزة للاستخدام من أي Frontend
- الصفحة الرئيسية `/` هي الـ Feed الشبيه بفيسبوك — تجمع أعمال + مسابقات + نتائج + أبطال سابقون في تدفق واحد مع قسم ثابت للمسابقة الحالية والفائزين بالأعلى
- App Shell (المرحلة 0) إلزامية قبل أي صفحة أخرى — لأنها تحتوي Top Bar, Sidebar, Identity Card, Background, Notifications, Language, Theme
- معايير الأمان موحّدة في **Section 2.15 (Security & API Standards)** — راجعها قبل البدء في أي API
- ترتيب البناء: App Shell ← Auth ← Dashboard ← Users ← Roles ← Staff ← Profiles ← Works+Feed+Comments ← Stores ← Orders+RFQ ← Bookings ← Wallet ← Contests ← Notifications+Admin Notif. ← Analytics ← Audit Logs ← Admin Control Center ← Subscriptions ← Disputes ← Backup
- **لا عوائق تقنية — البيئة جاهزة للبدء فوراً**

# تعليمات التنفيذ النهائية – صفحة تسجيل الدخول (Yemen Motion)
باضافة هذه التعليمات في اخر الملف المرجعي للمشروع بدون تعديل او تحديث اي شيء في هذه التعليمات او ملف الذاكرة المرجعي 
اعتبر هذه الوثيقة مرجع التنفيذ النهائي الملزم.
يمكنك  تقسيم التنفيذ الى خطوات وتنفيذها على خطوة خطوة 
أي قرار هندسي يخالف ما ورد هنا يجب توضيحه قبل تنفيذه.

---

# الهدف

بناء صفحة تسجيل دخول تعتبر واجهة عرض احترافية لمنصة متخصصة في:

* التصميم الجرافيكي
* الموشن جرافيك
* المونتاج
* الإنتاج الإبداعي

المطلوب ليس نموذج تسجيل دخول تقليدي.

المطلوب تجربة بصرية مميزة تعطي انطباعاً احترافياً منذ أول ثانية.

---

# ممنوعات

ممنوع:

* استخدام أي كود من resources/js
* نسخ أي Component قديم
* استخدام PNG كمصدر رئيسي للشعار أو الاسم
* وضع منطق Three.js داخل login.vue
* وضع منطق GSAP داخل login.vue
* وضع Shader Code داخل الصفحة
* وضع CSS ضخم داخل الصفحة

---

# Architecture

يجب إنشاء الملفات التالية بالكامل:

frontend/components/

* MotionLogo.vue
* MotionName.vue
* GlassLoginCard.vue
* ParticleBackground.vue
* AnimatedLights.vue

frontend/composables/

* useThreeScene.ts
* useParticles.ts
* useLoginAnimations.ts
* useSvgMorph.ts

frontend/pages/auth/

* login.vue

frontend/assets/

* auth.scss

---

# مسؤولية login.vue

يعمل كـ Orchestrator فقط.

يسمح فقط بـ:

* إدارة الحالة
* استدعاء المكونات
* استدعاء المتاجر
* استدعاء composables

ممنوع وجود:

* كود Three.js
* كود Shader
* كود Particle Logic
* كود Morph Logic

داخله.

---

# MotionLogo.vue

يجب أن يعتمد على:

frontend/public/logo.svg

وليس logo.png

المطلوب:

الحالات التالية:

idle
hover
success
error
loading

في حالة hover:

* دوران خفيف
* تكبير بسيط
* Glow

في حالة success:

* توهج أخضر
* Morph SVG
* Pulse

في حالة error:

* وميض أحمر
* Shake
* تغيير لون accent layer

في حالة loading:

* دوران سلس
* نبض خفيف

يجب استخدام:

GSAP
+
Flubber

وليس MorphSVGPlugin.

---

# MotionName.vue

يجب أن يعتمد على:

frontend/public/name.svg

وليس name.png

المطلوب:

* تحريك الحروف بشكل منفصل
* Parallax
* Depth Effect
* 3D Rotation

عند Hover:

* تحرك الحروف بدرجات مختلفة

عند Success:

* Glow أخضر
* Wave Animation

عند Error:

* اهتزاز خفيف

---

# GlassLoginCard.vue

المطلوب:

Glassmorphism حقيقي.

المواصفات:

backdrop-filter: blur(24px)

border:
rgba(255,255,255,.15)

background:
rgba(255,255,255,.06)

يجب إضافة:

* Tilt Effect
* Soft Reflection
* Animated Border Glow

عند Error:

* card shake

عند Success:

* success glow

---

# ParticleBackground.vue

يجب أن يستخدم:

useThreeScene.ts
+
useParticles.ts

ولا يحتوي منطق داخلي.

---

# AnimatedLights.vue

المطلوب:

طبقات إضاءة ديناميكية.

تشبه:

Aurora
Gradient Rays
Floating Light Beams

تتحرك ببطء.

ترتبط بالماوس.

لا تؤثر على الأداء.

---

# useThreeScene.ts

المطلوب:

* Scene
* Camera
* Renderer
* Resize Handler
* Visibility Handler
* Cleanup Handler

إلزامي:

requestIdleCallback

قبل إنشاء المشهد.

وفي حال عدم الدعم:

setTimeout

كبديل.

---

# OffscreenCanvas

إذا كان مدعوماً:

استخدمه.

إذا لم يكن:

Fallback إلى Canvas عادي.

---

# useParticles.ts

يجب استخدام:

THREE.InstancedMesh
+
ShaderMaterial

وليس:

THREE.Points

المواصفات:

Desktop:
1200 particles

Tablet:
800 particles

Mobile:
500 particles

---

# Adaptive Quality

إذا انخفض الأداء:

FPS > 55
1200

FPS 45-55
800

FPS 30-45
500

FPS < 30
250

يتم التغيير تلقائياً.

---

# Mouse Interaction

المطلوب:

Attraction
Repulsion
Wave Distortion

تنفذ على GPU داخل Shader.

ممنوع تنفيذها على CPU.

---

# useSvgMorph.ts

المطلوب:

استخدام flubber.

يجب توفير API بسيط:

morphToSuccess()
morphToError()
morphToIdle()

ليستخدمه MotionLogo.

---

# useLoginAnimations.ts

يجب أن يحتوي:

cardEntrance()

successAnimation()

errorShake()

buttonRipple()

logoHover()

logoLeave()

pageTransition()

---

# Accessibility

إلزامي:

ARIA labels

Keyboard Navigation

Focus States

Role Alerts

Screen Reader Support

---

# Reduced Motion

يجب دعم:

prefers-reduced-motion

عند تفعيله:

* إيقاف Morph
* إيقاف Three.js Animation الثقيلة
* تقليل الحركة بنسبة كبيرة

---

# Dark Mode

يجب أن يعمل مع Theme Store الحالي.

لا يتم إنشاء نظام Theme جديد.

---

# Responsive

يجب اختبار:

Mobile 375px

Tablet 768px

Desktop 1440px

---

# Performance Targets

Lighthouse

Performance:

> = 90

Accessibility:

> = 95

Best Practices:

> = 95

SEO:

> = 90

---

# التسليم المطلوب

بعد انتهاء التنفيذ أريد:

1. tree -L 3 frontend

2. قائمة الملفات الجديدة

3. قائمة الملفات المعدلة

4. الكود الكامل لملف login.vue

5. شرح تقني لكل Component و Composable



7. تقرير Lighthouse

8. نتائج:

npm run dev

9. إثبات عدم وجود Errors أو Warnings داخل Console

لن تعتبر المهمة منجزة إلا بعد تسليم جميع ما سبق.


IMPLEMENTATION_LOG — سجل الإنجاز والاعتماد

هذا القسم مخصص لتثبيت ما تم إنجازه واعتماده بعد كل مرحلة.
لا يتم تعديل الأقسام المرجعية السابقة عند اعتماد أي جزء.
أي إنجاز جديد يُضاف هنا في نهاية الملف فقط بنظام Append Only.
الهدف: منع الخلط، منع التكرار، وتوثيق الحالة الفعلية للمشروع بعد كل اعتماد.

2026-06-19 — اعتماد مبدئي نهائي لواجهة صفحة تسجيل الدخول
القرار

تم اعتماد واجهة صفحة تسجيل الدخول كواجهة UI فقط.

Status:

Login Page UI: APPROVED

نوع الاعتماد:

UI Layer Approval فقط.

هذا الاعتماد لا يعني اعتماد مرحلة Authentication بالكامل.

نطاق الاعتماد

يشمل هذا الاعتماد صفحة:

/auth/login

داخل مشروع Nuxt 4 في:

frontend/

ويشمل طبقة الواجهة البصرية والتفاعلية فقط، وليس المصادقة الحقيقية.

ما تم اعتماده

تم اعتماد العناصر التالية:

صفحة تسجيل الدخول تعمل كواجهة بصرية احترافية لمنصة Yemen Motion.
استخدام شعار SVG عبر MotionLogo.
استخدام اسم المنصة SVG عبر MotionName.
استخدام بطاقة تسجيل دخول زجاجية GlassLoginCard.
وجود خلفية جسيمات ParticleBackground محمّلة كسولاً.
وجود إضاءات متحركة AnimatedLights محمّلة كسولاً.
وجود عناصر إبداعية عائمة FloatingCreativeElements محمّلة كسولاً.
الحفاظ على login.vue كـ Orchestrator فقط.
عدم وضع Three.js داخل login.vue.
عدم وضع Shader logic داخل login.vue.
عدم وضع Particle logic داخل login.vue.
عدم وضع Morph logic داخل login.vue.
الحفاظ على Lazy Loading للمؤثرات الثقيلة.
إصلاح overflow على شاشة الهاتف.
إضافة زر إظهار/إخفاء كلمة المرور.
إضافة رابط نسيت كلمة المرور.
اعتماد مسار نسيت كلمة المرور الرسمي:

/auth/forgot-password

إضافة رابط إنشاء حساب:

/auth/register

دعم التجاوب مع الهاتف والتابلت وسطح المكتب.
الحفاظ على عدم وجود Console Errors حسب تحقق المطور.
الحفاظ على الأداء المطلوب بعد التعديلات.
نتائج Lighthouse المعتمدة

تم اعتماد النتائج التالية حسب التحقق النهائي المرسل من المطور:

Performance: 100
Accessibility: 96
Best Practices: 96
SEO: 100

هذه النتائج تحقق متطلبات PROJECT_MAP الحالية:

Performance >= 90
Accessibility >= 95
Best Practices >= 95
SEO >= 90

الملفات والمناطق المرتبطة بهذا الاعتماد

الملفات والمكونات المرتبطة بواجهة تسجيل الدخول تشمل:

frontend/pages/auth/login.vue
frontend/assets/auth.scss
frontend/components/MotionLogo.vue
frontend/components/MotionName.vue
frontend/components/GlassLoginCard.vue
frontend/components/ParticleBackground.vue
frontend/components/AnimatedLights.vue
frontend/components/FloatingCreativeElements.vue
frontend/composables/useThreeScene.ts
frontend/composables/useParticles.ts
frontend/composables/useLoginAnimations.ts
frontend/composables/useSvgMorph.ts
frontend/composables/useInlineSvg.ts
frontend/public/logo.svg
frontend/public/name.svg

ضوابط يجب الحفاظ عليها مستقبلاً

أي تعديل لاحق على صفحة /auth/login يجب أن يلتزم بالضوابط التالية:

ممنوع تحويل login.vue إلى ملف ضخم.
ممنوع إضافة Three.js أو GSAP أو Flubber باستيراد مباشر داخل المسار الحرج.
ممنوع تحميل المؤثرات الثقيلة قبل First Paint.
ممنوع كسر Lazy Loading للمكونات الثقيلة.
ممنوع استخدام PNG كمصدر رئيسي للشعار أو اسم المنصة.
ممنوع استخدام resources/js في تطوير صفحة الدخول.
ممنوع خفض Lighthouse Performance عن 90.
ممنوع خفض Accessibility عن 95.
يجب الحفاظ على مسار نسيت كلمة المرور:

/auth/forgot-password

يجب الحفاظ على مسار إنشاء الحساب:

/auth/register

ما لم يتم اعتماده بعد

هذا الاعتماد لا يشمل العناصر التالية:

Auth API الحقيقي.
Register Page الفعلية.
Forgot Password Page الفعلية.
Reset Password Page الفعلية.
ربط login.vue بـ authStore الحقيقي.
ربط Nuxt مع Laravel Sanctum API.
حفظ user/token/role بعد تسجيل الدخول.
Role-based redirect.
Auth middleware لحماية المسارات.
اعتماد مرحلة Authentication بالكامل.
الحالة التالية للمشروع

بعد هذا الاعتماد، لا يتم الرجوع إلى تحسينات صفحة تسجيل الدخول إلا إذا ظهر خلل واضح أو كسر أداء.

المرحلة التالية التي ستُناقش لاحقاً هي:

Authentication Flow Completion

وتشمل لاحقاً:

/auth/register
/auth/forgot-password
/auth/reset-password
authStore الحقيقي
Laravel Sanctum API
Role-based redirect
Auth middleware

لكن لا تبدأ أي مرحلة جديدة قبل مناقشتها واعتماد تعليماتها بشكل مستقل.



2026-06-19 — اعتماد المرحلة 1 — Backend Auth API Foundation

القرار

تم اعتماد البنية الخلفية للمصادقة (Backend Auth API Foundation) كاملة.
هذا الاعتماد يشمل Step 1A (البنية التحتية) + Step 1B (النقاط النهائية) + Step 1C (الاختبارات والتحقق).

Status:

Step 1A — Backend Auth Infrastructure: APPROVED
Step 1B — Backend Auth Endpoints: APPROVED
Step 1C — Backend Auth Tests & Verification: APPROVED
Step 1 — Backend Auth API Foundation: APPROVED

نوع الاعتماد

Laravel Backend Auth API Foundation — Sanctum + Spatie Permission.

نطاق الاعتماد

يشمل هذا الاعتماد البنية الخلفية للمصادقة داخل Laravel فقط:

- تثبيت Sanctum v4 و Spatie Permission v8
- إعداد User model بـ HasApiTokens و HasRoles traits
- نشر ونقل جداول Spatie (create_permission_tables)
- إنشاء AuthRolesSeeder للأدوار (client, designer)
- إعداد config/permission.php
- إنشاء AuthApiController مع دوال register, login, logout, user
- إنشاء RegisterRequest و LoginRequest و UserResource
- إعداد routes/api.php مع مسارات /api/auth/register, /api/auth/login, /api/auth/logout, /api/user
- إنشاء ملف الاختبارات tests/Feature/Auth/AuthApiTest.php
- نجاح 8/8 اختبارات
- توحيد تنسيق JSON: {success, data, message, errors}
- معالجة AuthenticationException لتنسيق JSON الموحد لمسارات API

ما تم اعتماده

تم اعتماد العناصر التالية في Laravel:

- Sanctum v4 لتوليد Bearer tokens وإنشاؤها وحذفها
- Spatie Permission v8 لربط الأدوار وتخصيصها
- User model جاهز مع traits المصادقة والأدوار
- AuthApiController يعمل لتسجيل الدخول والتسجيل والخروج وجلب المستخدم
- RegisterRequest يحقق من: name, email (unique), password (confirmed, min:8), role (in:client,designer)
- LoginRequest يحقق من: email, password
- UserResource يحدد حقول المستخدم المرتجعة (id, name, email, created_at)
- register يعيد: user, token, role (Spatie), permissions
- login يعيد: user, token, role (Spatie), permissions
- logout يحذف currentAccessToken فقط
- /api/user يعيد: user, role, permissions — بدون token
- خطأ المصادقة (401) يعيد تنسيق JSON موحد
- خطأ بيانات الدخول الخاطئة (401) يعيد تنسيق JSON موحد
- الاختبارات تغطي: register (client+designer), login, wrong login, /api/user بدون token, /api/user مع token, logout, نفس token بعد logout
- 8/8 اختبارات تمر بنجاح (35 assertions)

الملفات والمناطق المرتبطة بهذا الاعتماد

الملفات المنشأة أو المعدلة في Steps 1A–1C:

الملفات الجديدة:
- tests/Feature/Auth/AuthApiTest.php
- app/Http/Controllers/Api/AuthApiController.php
- app/Http/Requests/Auth/RegisterRequest.php
- app/Http/Resources/UserResource.php
- config/permission.php
- database/migrations/2026_06_19_224625_create_permission_tables.php
- database/seeders/AuthRolesSeeder.php

الملفات المعدلة:
- app/Models/User.php (إضافة HasRoles trait)
- bootstrap/app.php (معالجة AuthenticationException)
- routes/api.php (إضافة مسارات Auth مع auth:sanctum على logout و /api/user)
- app/Http/Requests/Auth/LoginRequest.php (تبسيط إلى rules فقط)
- composer.json / composer.lock (إضافة spatie/laravel-permission)

المسارات المعتمدة:

POST /api/auth/register — عام — إنشاء حساب
POST /api/auth/login — عام — تسجيل دخول
POST /api/auth/logout — مصادق (auth:sanctum) — إبطال التوكن
GET /api/user — مصادق (auth:sanctum) — المستخدم الحالي

ما لم يتم اعتماده بعد

هذا الاعتماد لا يشمل العناصر التالية:

- Forgot Password API
- Reset Password API
- Verify Email API
- Register Page الفعلية في frontend/
- Forgot Password Page الفعلية
- Reset Password Page الفعلية
- ربط login.vue بـ authStore الحقيقي
- ربط Nuxt مع Laravel Sanctum API
- حفظ user/token/role بعد تسجيل الدخول
- Role-based redirect
- Auth middleware لحماية المسارات
- اعتماد مرحلة Authentication بالكامل (تتطلب اكتمال الطرفين)

الحالة التالية للمشروع

بعد هذا الاعتماد، البنية الخلفية للمصادقة (Sanctum + Spatie + API endpoints + tests) جاهزة للربط مع الواجهة الأمامية.

المرحلة التالية التي ستُناقش لاحقاً هي:

Frontend Auth Integration

وتشمل:

- ربط Nuxt مع Laravel Sanctum API
- authStore الحقيقي
- حفظ user/token/role بعد تسجيل الدخول
- Role-based redirect
- Auth middleware

لكن لا تبدأ أي مرحلة جديدة قبل مناقشتها واعتماد تعليماتها بشكل مستقل.



---

## IMPLEMENTATION_LOG — اعتماد Step 2 — Backend Password Reset API

التاريخ: 2026-06-20

الحالة العامة:

Step 2 — Backend Password Reset API: APPROVED

تم اعتماد تنفيذ API استعادة كلمة المرور في Laravel Backend فقط، دون أي تعديل على واجهة المستخدم أو صفحات Nuxt أو PROJECT_MAP.md قبل هذه الإضافة.

### الخطوات الفرعية المعتمدة

- Step 2A — Backend Password Reset API: APPROVED
- Step 2B — Password Reset Tests & Verification: APPROVED

### نطاق الاعتماد

تم اعتماد المسارات التالية:

- POST /api/auth/forgot-password
- POST /api/auth/reset-password

### تفاصيل Step 2A

تم تنفيذ مسارات استعادة كلمة المرور باستخدام Laravel Password Broker الرسمي:

- Password::sendResetLink()
- Password::reset()

تم إنشاء أو تعديل الملفات التالية ضمن النطاق الخلفي المسموح:

- app/Http/Requests/Auth/ForgotPasswordRequest.php
- app/Http/Requests/Auth/ResetPasswordRequest.php
- routes/api.php
- app/Http/Controllers/Api/AuthApiController.php

لم يتم إنشاء نظام token يدوي.

لم يتم إرجاع reset token في response.

لم يتم تعديل واجهة المستخدم.

لم يتم تعديل routes/web.php.

لم يتم تعديل frontend أو resources أو public.

لم يتم تشغيل migrations مدمرة.

### تفاصيل Step 2B

تم إنشاء اختبار:

- tests/Feature/Auth/PasswordResetApiTest.php

يغطي الاختبار الحالات التالية:

- forgot-password مع بريد موجود.
- forgot-password مع بريد غير موجود.
- reset-password مع token صحيح.
- reset-password مع token خاطئ.
- validation ناقص لطلب reset-password.
- فشل تسجيل الدخول بكلمة المرور القديمة بعد نجاح reset-password.
- نجاح تسجيل الدخول بكلمة المرور الجديدة بعد reset-password.

### نتائج الاختبارات

PasswordResetApiTest:

- 6/6 passed
- 17 assertions

AuthApiTest:

- 8/8 passed
- 35 assertions

### ملاحظات فنية

إعداد البريد الحالي في بيئة التطوير يستخدم:

MAIL_MAILER=log

لذلك روابط استعادة كلمة المرور تُسجل في:

storage/logs/laravel.log

ولا تُرسل كبريد حقيقي في بيئة التطوير.

استخدام RefreshDatabase في اختبار PasswordResetApiTest مقبول لأنه يعمل على SQLite in-memory حسب إعداد phpunit.xml، وليس على قاعدة PostgreSQL المحلية.

### حدود الاعتماد

هذا الاعتماد خاص بـ Backend Password Reset API فقط.

لا يشمل هذا الاعتماد:

- بناء صفحة forgot-password في Nuxt.
- بناء صفحة reset-password في Nuxt.
- ربط الواجهة بهذه المسارات.
- إعداد SMTP للإنتاج.
- تعديل Login UI.
- تنفيذ middleware frontend.

### القرار

Step 2 — Backend Password Reset API: APPROVED

يمكن بعد هذا الاعتماد الانتقال إلى المرحلة التالية فقط بعد إثبات أن هذا التحديث أضيف إلى PROJECT_MAP.md بنظام Append Only.

---

## IMPLEMENTATION_LOG — Step 3 Frontend Auth Integration Approval

### Step
Step 3 — Frontend Auth Integration

### Approval Status
APPROVED

### Approval Date
2026-06-20

### Scope
Frontend Auth integration for the Nuxt application under `frontend/`.

This approval covers the completed frontend authentication flow integration after the backend Auth API and password reset API were approved.

### Approved Sub-Steps

#### Step 3A — Frontend Auth API Client + authStore Integration
Status: APPROVED

Implemented and approved:
- `frontend/composables/useApiClient.ts`
- `frontend/stores/authStore.ts`
- `frontend/types/auth.ts`
- `frontend/nuxt.config.ts` runtime API configuration

Approved behavior:
- Uses `runtimeConfig.public.apiBaseUrl`.
- Uses `ym_auth_token` cookie for token persistence.
- Sends Bearer token through the API client.
- Clears auth state on API 401.
- Provides frontend auth actions:
  - `register`
  - `login`
  - `logout`
  - `fetchUser`
  - `hydrateAuth`
  - `clearAuth`
  - `forgotPassword`
  - `resetPassword`

#### Step 3B — Link Approved Login UI to Real Auth API
Status: APPROVED

Implemented and approved:
- Existing approved Login UI was connected to the real Auth API through `authStore.login`.
- Mock login behavior was removed.
- Fixed redirect behavior after successful login based on user role:
  - `admin` → `/admin`
  - `designer` → `/designer`
  - `client` → `/`
  - fallback → `/`
- Backend validation and authentication errors are displayed to the user.
- Approved Login visual design, animation structure, and components were preserved.

#### Step 3C — Frontend Auth Route Middleware
Status: APPROVED

Implemented and approved:
- `frontend/middleware/auth.global.ts`

Approved behavior:
- Public Auth routes:
  - `/auth/login`
  - `/auth/register`
  - `/auth/forgot-password`
  - `/auth/reset-password`
- Protected routes:
  - `/admin`
  - `/designer`
  - `/client`
- Role rules:
  - `/admin` → `admin`
  - `/designer` → `designer`, `admin`
  - `/client` → `client`, `admin`
- Unauthenticated users are redirected to `/auth/login?redirect=<path>`.
- Authenticated users visiting `/auth/login` are redirected based on role.
- Redirect loops are guarded with path checks.
- Middleware does not use `window` or `localStorage`.
- `hydrateAuth()` is async and fetches the current user when a token exists.

#### Step 3D — Register Page Integration
Status: APPROVED

Implemented and approved:
- `frontend/pages/auth/register.vue`

Approved behavior:
- Uses `authStore.register`.
- Registration fields:
  - `name`
  - `email`
  - `password`
  - `password_confirmation`
  - `role`
- Public registration roles are limited to:
  - `client`
  - `designer`
- Public registration does not expose or allow `admin`.
- Validation errors are shown per field.
- Loading state is handled.
- Successful registration redirects based on role.

#### Step 3E — Forgot Password Page
Status: APPROVED

Implemented and approved:
- `frontend/pages/auth/forgot-password.vue`

Approved behavior:
- Uses `authStore.forgotPassword`.
- Contains only the email field.
- Shows a generic success message.
- Does not reveal whether an email exists.
- Does not expose reset tokens.
- Does not read Laravel logs.
- Shows backend/validation errors.
- Loading state is handled.
- Includes navigation links to login/register.

#### Step 3F — Reset Password Page
Status: APPROVED

Implemented and approved:
- `frontend/pages/auth/reset-password.vue`

Approved behavior:
- Uses `authStore.resetPassword`.
- Reads `token` from query params.
- Reads `email` from query params when present, while allowing manual email entry.
- Does not display token to the user.
- Password reset fields:
  - `email`
  - `password`
  - `password_confirmation`
- Shows success and validation/backend errors.
- Loading state is handled.
- Redirects to `/auth/login` after successful reset.

#### Step 3X — Git Hygiene
Status: APPROVED

Implemented and approved:
- Frontend source files became visible in Git review via intent-to-add.
- `.gitignore` excludes generated/local frontend files, including:
  - `frontend/node_modules/`
  - `frontend/.nuxt/`
  - `frontend/.output/`
  - `frontend/dist/`
  - `frontend/.cache/`
  - `frontend/.vite/`
  - frontend `.env` files
  - Lighthouse generated reports
- `frontend/package-lock.json` remains visible for tracking.
- No generated build output is tracked.
- No files were deleted.
- No functional code was changed in this hygiene step.

#### Step 3Z — Frontend Auth Flow Final Verification
Status: APPROVED

Final verification confirmed:
- All Auth pages exist:
  - `frontend/pages/auth/login.vue`
  - `frontend/pages/auth/register.vue`
  - `frontend/pages/auth/forgot-password.vue`
  - `frontend/pages/auth/reset-password.vue`
- Login uses `authStore.login`.
- Register uses `authStore.register`.
- Forgot password uses `authStore.forgotPassword`.
- Reset password uses `authStore.resetPassword`.
- `authStore` includes all required Auth actions.
- `useApiClient` uses:
  - `apiBaseUrl`
  - `ym_auth_token`
  - Bearer token authorization
  - 401 auth cleanup
- Middleware protects role-based routes and leaves Auth public routes accessible.
- Role redirects are correct.
- `npm run build` completed successfully.
- `npm install` was not run.
- Backend files were not modified.
- `PROJECT_MAP.md` was not modified during implementation or verification before this append-only documentation step.

### Build Verification
Frontend build completed successfully using:

```bash
npm run build
```

Build result:

```text
✨ Build complete!
```

### Non-Blocking Notes

The final frontend build reported non-blocking warnings:

* A large JavaScript chunk warning related to animation/3D dependencies.
* A chunk-splitting warning related to `authStore.ts` being imported dynamically and statically.

These are not functional Auth failures and do not block Step 3 approval. They may be addressed later as frontend performance optimization work.

### Final Decision

Step 3 — Frontend Auth Integration is APPROVED.

The approved Auth flow now includes:

* Backend login/register/logout/current-user integration on the frontend.
* Token persistence through `ym_auth_token`.
* API client Bearer authentication.
* Login UI connected to real backend Auth.
* Register page.
* Forgot password page.
* Reset password page.
* Global auth middleware.
* Role-based route protection.
* Build verification.

### Explicit Non-Scope

This approval does not include:

* Email verification UI.
* CAPTCHA UI.
* Admin dashboard implementation.
* Designer dashboard implementation.
* Client dashboard implementation.
* Frontend performance optimization for large chunks.
* Mobile app work.
* Any new backend changes beyond previously approved Auth API and Password Reset API steps.

---

## IMPLEMENTATION_LOG — Step 4A Auth Flow Manual Runtime QA Approval

### Step
Step 4A — Auth Flow Manual Runtime QA

### Approval Status
APPROVED

### Approval Date
2026-06-21

### Scope
This section documents the final approval of the runtime authentication quality assurance step for Yemen Motion.

The approved scope includes:
- Backend Auth API runtime behavior.
- Frontend Auth page integration.
- Register and login behavior from browser-origin requests.
- Bearer token authentication flow.
- `/api/user` session hydration.
- Role restoration after refresh.
- Frontend route middleware behavior.
- Password reset runtime behavior.
- Reset password link generation for the Nuxt frontend route.
- SSR safety for authenticated user dropdown rendering.
- Build stability after all Step 4A fixes.

This step does not build dashboard pages. It verifies that authentication and role routing are functioning correctly before dashboard implementation begins.

---

### Step 4A-Fix1 — Roles and Auth Hydration Fix

Status: APPROVED

Problem discovered during runtime QA:
- Browser/API registration initially failed because Spatie roles were not available with the expected guard.
- Runtime error:
  - `There is no role named 'client' for guard 'web'.`
- Frontend session hydration also needed to restore `role` and `permissions` from the `/api/user` response.

Approved backend fix:
- `database/seeders/AuthRolesSeeder.php` was corrected.
- Seeder namespace was corrected to:
  - `Database\Seeders`
- Roles are created explicitly with `guard_name => web`:
  - `client web`
  - `designer web`

Approved frontend fix:
- `frontend/stores/authStore.ts` was updated so `fetchUser()` restores:
  - `user`
  - `role`
  - `permissions`
  - `isAuthenticated`

Runtime verification confirmed:
- `client web` exists.
- `designer web` exists.
- Register API succeeds for `client`.
- Register API succeeds for `designer`.
- `/api/user` returns `role` and `permissions`.
- `RoleDoesNotExist` no longer appears.
- Session hydration remains stable after refresh.

---

### Step 4A-Fix2 — Browser CSRF 419 Fix

Status: APPROVED

Problem discovered during manual browser testing:
- Browser requests to the correct API URL still returned:
  - HTTP 419
  - `CSRF token mismatch`

Confirmed failing request:
- `POST http://127.0.0.1:8000/api/auth/register`
- Origin:
  - `http://localhost:3000`

Root cause:
- `bootstrap/app.php` prepended Sanctum stateful middleware to the API middleware stack:
  - `Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful`
- This caused browser-origin API requests to be treated as stateful SPA session requests.
- Laravel therefore applied session and CSRF behavior to API routes such as:
  - `/api/auth/register`
  - `/api/auth/login`

Approved fix:
- Removed `EnsureFrontendRequestsAreStateful` from the API middleware stack in:
  - `bootstrap/app.php`

Preserved authentication architecture:
- Yemen Motion Auth remains Bearer-token based.
- Frontend stores token in:
  - `ym_auth_token`
- API requests use:
  - `Authorization: Bearer <token>`
- No `/sanctum/csrf-cookie` flow was added.
- No session-cookie auth conversion was performed.

Runtime verification confirmed:
- Browser-origin register request succeeds.
- Browser-origin login request succeeds.
- `/api/user` works with Bearer token.
- CSRF 419 no longer appears.
- `RoleDoesNotExist` does not appear.
- Build succeeds.

---

### UserDropdown SSR Safety Fix

Status: APPROVED

File:
- `frontend/components/UserDropdown.vue`

Problem:
- The component attempted to read user fields while `auth.user` could be `null` during SSR or unauthenticated rendering.
- Runtime SSR error:
  - `Cannot read properties of null (reading 'avatar')`

Approved fix:
- Use a reactive/computed user reference.
- Guard rendering when user is missing.
- Use null-safe field access:
  - `user?.avatar`
  - `user?.name`
- Prevent rendering the dropdown when `auth.user = null`.

Verification:
- Home page no longer crashes during SSR.
- `UserDropdown.vue` is safe when unauthenticated.
- No unsafe direct reads of `user.avatar` or `user.name`.
- No `window` or `localStorage` usage was introduced.
- Frontend build succeeds.

---

### Step 4A-R — Frontend Route Runtime QA

Status: APPROVED

Runtime environment:
- Laravel server:
  - `http://127.0.0.1:8000`
  - `/up` returned HTTP 200
- Nuxt dev server:
  - `http://localhost:3000`
  - returned HTTP 200

Protected routes without cookie:
- `/admin`
  - redirects to `/auth/login?redirect=/admin`
- `/designer`
  - redirects to `/auth/login?redirect=/designer`
- `/client`
  - redirects to `/auth/login?redirect=/client`

Client role behavior:
- Client is blocked from `/admin`.
- Client is blocked from `/designer`.
- Client is allowed by middleware to reach `/client`.

Designer role behavior:
- Designer is blocked from `/admin`.
- Designer is allowed by middleware to reach `/designer`.
- Designer is blocked from `/client`.

Repeated route checks:
- Role/session hydration remained stable across repeated requests.
- No redirect loops appeared.

Non-blocking route note:
- `/client` and `/designer` may return 404 after middleware allows access because those dashboard pages are not built yet.
- This is acceptable because dashboard implementation is outside Step 4A.

---

### Reset Password URL Fix

Status: APPROVED

Problem discovered during manual browser testing:
- Reset password links initially opened a non-existent frontend route.
- Incorrect route formats observed:
  - `/password-reset/<token>?email=...`
  - `/auth/password-reset/<token>?email=...`

Correct Nuxt route:
- `frontend/pages/auth/reset-password.vue`

Approved route format:
- `/auth/reset-password?token=<TOKEN>&email=<EMAIL>`

Approved backend fix:
- `app/Providers/AppServiceProvider.php` customizes Laravel reset URL generation using:
  - `ResetPassword::createUrlUsing`
- Reset URL generation now targets:
  - `/auth/reset-password`
- Token is passed as query parameter:
  - `token`
- Email is passed as query parameter:
  - `email`

---

### frontend_url Configuration Fix

Status: APPROVED

Problem found during static review:
- `AppServiceProvider.php` used:
  - `config('app.frontend_url')`
- But `config/app.php` did not statically define `frontend_url`.

Approved fix:
- `config/app.php` now defines:
  - `frontend_url`
- Fallback value:
  - `http://localhost:3000`

Approved `.env.example` update:
- `.env.example` includes:
  - `FRONTEND_URL=http://localhost:3000`

Static review confirmed:
- `config/app.php` contains `frontend_url`.
- `AppServiceProvider.php` uses `config('app.frontend_url')`.
- Reset URL generation uses:
  - `/auth/reset-password`
- Old reset URL formats are no longer generated:
  - `/password-reset/<token>`
  - `/auth/password-reset/<token>`

---

### Password Reset Runtime QA

Status: APPROVED

Runtime behavior verified:
- Forgot password endpoint returns a generic message.
- Forgot password does not reveal whether an email exists.
- Reset link uses the correct Nuxt route:
  - `/auth/reset-password?token=...&email=...`
- Reset password page opens without 404.
- Token is not displayed as a visible form field.
- Valid reset token changes the password.
- Login succeeds with the new password.
- Login fails with the old password.
- Invalid reset token returns a clear failure message.

Final manual browser confirmation:
- Forgot password: PASS
- Reset link shape `/auth/reset-password?token=...`: PASS
- Reset page opens without 404: PASS
- Reset password with valid token: PASS
- Login with new password: PASS
- Login with old password: PASS

---

### API Runtime QA Results

Status: APPROVED

Verified API behavior:
- Register client: PASS
- Register designer: PASS
- Login with wrong password: PASS
- Login with correct password: PASS
- `/api/user` with valid Bearer token: PASS
- `/api/user` without token: PASS
- `/api/auth/logout` without token returns unauthorized: PASS
- Forgot password with existing email: PASS
- Forgot password with unknown email: PASS
- Reset password with valid token: PASS
- Login with new password: PASS
- Login with old password after reset fails: PASS
- Reset password with invalid token: PASS

---

### Static Review Results

Status: APPROVED

Codex static review confirmed:
- `bootstrap/app.php` no longer applies `EnsureFrontendRequestsAreStateful` to API routes.
- Auth remains Bearer-token based.
- Frontend does not add `/sanctum/csrf-cookie`.
- `AppServiceProvider.php` generates the correct reset password URL.
- `config/app.php` defines `frontend_url` with fallback.
- `.env.example` documents `FRONTEND_URL`.
- `UserDropdown.vue` is SSR-safe when `auth.user = null`.
- `authStore.fetchUser()` restores `user`, `role`, and `permissions`.
- `hydrateAuth()` calls `fetchUser()` when a token exists.
- `AuthRolesSeeder.php` defines `client web` and `designer web`.
- Frontend middleware role rules are correct.
- Build succeeds.

---

### Build Verification

Status: APPROVED

Frontend build was executed after the Step 4A fixes.

Result:
- Client build: PASS
- Server build: PASS
- No blocking build errors.

Non-blocking warnings:
- Large chunk size warning.
- Dynamic/static import warning around `authStore`.

These are not authentication failures and may be handled later as frontend performance optimization.

---

### Runtime Review Boundary

Codex could not access the same runtime environment used by OpenCode.

Confirmed Codex limitation:
- Codex can read files.
- Codex can run static checks.
- Codex can inspect diffs.
- Codex can run build when available.
- Codex cannot reliably access:
  - Laravel server at `127.0.0.1:8000`
  - PostgreSQL at `127.0.0.1:5433`
  - runtime processes started by OpenCode

Workflow decision:
- Runtime/browser/API/DB verification is handled by OpenCode.
- Static review and diff inspection are handled by Codex.
- Final approval is made only after comparing both reports.

---

### Files Changed During Step 4A

The Step 4A approval includes changes to the following files:

- `database/seeders/AuthRolesSeeder.php`
- `frontend/stores/authStore.ts`
- `frontend/components/UserDropdown.vue`
- `bootstrap/app.php`
- `app/Providers/AppServiceProvider.php`
- `config/app.php`
- `.env.example`

Notes:
- `PROJECT_MAP.md` was intentionally not modified during runtime fixes.
- `PROJECT_MAP.md` is modified only in this documentation step.
- `npm install` was not run.
- No commit was created.

---

### Explicit Non-Scope

This approval does not include:
- Building `/admin` dashboard.
- Building `/designer` dashboard.
- Building `/client` dashboard.
- Full Playwright/Puppeteer browser E2E automation.
- Email verification UI.
- CAPTCHA UI.
- Frontend chunk optimization.
- Legacy `resources/js` cleanup.
- Admin role seeding beyond the current tested scope.
- Dashboard page implementation.

---

### Final Decision

Step 4A — Auth Flow Manual Runtime QA is APPROVED.

The Auth system is now verified at:
- Backend API runtime level.
- Browser-origin API request level.
- Bearer token authentication level.
- Frontend Auth page level.
- Frontend middleware route level.
- Password reset runtime level.
- Reset link generation level.
- SSR safety level.
- Build level.

This approval completes the Auth runtime validation required before starting dashboard page implementation.

---

## IMPLEMENTATION_LOG — Step 5A/5B Admin & Staff Dashboard App Shell Foundation

### Step Group
Step 5 — Admin & Staff Dashboard Foundation

### Included Steps
- Step 5A — Admin/Staff Dashboard Current State Audit
- Step 5B — Admin/Staff App Shell Foundation
- Step 5B-Fix1 — SSR and Shell Interaction Fix
- Step 5B-R — Authenticated Runtime QA

### Approval Status
APPROVED

### Approval Date
2026-06-21

---

### Purpose

This section documents the approved foundation for the Yemen Motion internal dashboard experience for:

- Administration dashboard route:
  - `/admin`
- Staff dashboard route:
  - `/staff`

The approved goal of Step 5B was not to build the complete production dashboard system. The approved goal was to establish the first working Admin/Staff App Shell, dashboard home placeholders, role-aware sidebar behavior, SSR-safe rendering, SVG-only charts, and authenticated runtime verification.

This step builds on the already approved authentication work and uses the existing Bearer-token authentication model.

---

### Step 5A — Current State Audit

Status: APPROVED

The audit confirmed the current dashboard foundation before implementation.

Confirmed existing items:
- `frontend/layouts/admin.vue` exists.
- `frontend/layouts/staff.vue` exists.
- Core App Shell components exist:
  - `AppTopBar.vue`
  - `AppSidebar.vue`
  - `IdentityCard.vue`
  - `BackgroundWatermark.vue`
  - `NotificationBell.vue`
  - `NotificationDropdown.vue`
  - `SearchToolbar.vue`
  - `ThemeToggle.vue`
  - `LangToggle.vue`
  - `UserDropdown.vue`
- `/admin` page existed but was only a minimal placeholder.
- `/staff` page did not exist before Step 5B.
- Dashboard API endpoints already existed in a preliminary form:
  - `GET /api/dashboard/stats`
  - `GET /api/dashboard/activity`
  - `GET /api/dashboard/chart`
- `DashboardController.php` already existed with preliminary methods.
- `config/permission.php` exists for Spatie Permission.
- Full permissions for dashboard operations were not yet seeded.
- Some dashboard-related frontend files existed as untracked/local files from an earlier session.

Important audit findings:
- `IdentityCard.vue` had a user-role bug because it referenced role fields that were not present directly on the `User` type.
- `AppSidebar.vue` was too limited and only displayed a minimal dashboard menu.
- `frontend/pages/staff/index.vue` was missing.
- Dashboard visual components such as KPI cards, period filters, SVG chart panels, and activity feed components were missing or incomplete.
- Existing dashboard data was not yet wired to production API responses.
- This phase should remain frontend shell foundation only and must not alter backend architecture.

---

### Step 5B — Admin/Staff App Shell Foundation

Status: APPROVED

Files modified or created during Step 5B:
- `frontend/components/IdentityCard.vue`
- `frontend/components/AppSidebar.vue`
- `frontend/pages/admin/index.vue`
- `frontend/pages/staff/index.vue`
- `frontend/components/dashboard/DashboardMetricCard.vue`
- `frontend/components/dashboard/DashboardActivityFeed.vue`
- `frontend/components/dashboard/DashboardPeriodFilter.vue`
- `frontend/components/dashboard/DashboardViewToggle.vue`
- `frontend/components/dashboard/DashboardSectionFilter.vue`
- `frontend/components/dashboard/DashboardSvgChart.vue`

Existing untracked or earlier-session files still present:
- `frontend/components/dashboard/StatsCard.vue`
- `frontend/components/dashboard/StatsChart.vue`
- `frontend/stores/dashboardStore.ts`

These existing files were not removed during Step 5B.

---

### IdentityCard Approval

Status: APPROVED

Problem:
- `IdentityCard.vue` previously referenced fields such as:
  - `user.role`
  - `user.roleLabel`
- These fields were not guaranteed on the `User` type.
- The component also needed stronger null safety when `auth.user = null`.

Approved behavior:
- The role is read from `auth.role`, not from `user.role`.
- The component is guarded when no authenticated user exists.
- User display uses safe fallbacks.
- Role labels are rendered in Arabic:
  - `admin` → `مدير النظام`
  - `staff` → `موظف`
  - `designer` → `مصمم`
  - `client` → `عميل`
- The component remains SSR-safe.
- The component does not use `window` or `localStorage`.

Final static review confirmed:
- `IdentityCard.vue` is null-safe.
- It depends on `auth.role`.
- It does not access unsafe user role fields.
- It does not introduce SSR hazards.

---

### AppSidebar Approval

Status: APPROVED

`AppSidebar.vue` was upgraded from a minimal menu into a role-aware dashboard sidebar.

Approved Admin menu:
- Dashboard
- Users
- Staff
- Roles & Permissions
- Works
- Orders
- Bookings
- Contests
- Wallet
- Reports
- Analytics
- Settings

Approved Staff menu:
- Dashboard
- Content Review
- Reports

Approved behavior:
- Sidebar reads the current role from `auth.role`.
- Admin receives the admin navigation set.
- Staff receives the staff navigation set.
- Client and designer do not receive admin/staff links by mistake.
- Sidebar uses `NuxtLink`.
- Sidebar includes active route state.
- Sidebar is RTL-friendly.
- Sidebar supports collapsed mode.
- Sidebar is SSR-safe.
- Sidebar does not use `window` or `localStorage`.

---

### Admin Dashboard Home

Status: APPROVED

Route:
- `/admin`

File:
- `frontend/pages/admin/index.vue`

Approved behavior:
- The page now uses the admin layout.
- The page is no longer a plain placeholder.
- The page displays a dashboard shell with:
  - Admin dashboard heading.
  - Operational description.
  - Period filter.
  - View toggle.
  - Section filter.
  - KPI cards.
  - SVG chart area.
  - Activity feed.
  - Critical/demo notice.
- The data displayed in this phase is static placeholder data.
- The static placeholder status is explicit and not represented as production data.

Approved visible examples:
- الطلبات
- الحجوزات
- المستخدمون
- البلاغات
- النشاط اليومي
- بيانات تجريبية

This page is approved as a foundation shell, not as the final data-driven admin dashboard.

---

### Staff Dashboard Home

Status: APPROVED

Route:
- `/staff`

File:
- `frontend/pages/staff/index.vue`

Approved behavior:
- The page was created.
- The page uses the staff layout.
- The page displays a staff dashboard shell with:
  - Staff dashboard heading.
  - Daily tasks description.
  - KPI cards.
  - Activity feed.
  - Notes/demo notice.

Approved visible examples:
- مهام قيد المراجعة
- بلاغات مفتوحة
- تقارير اليوم
- إشعارات غير مقروءة
- مهامك اليومية حسب الصلاحيات

This page is approved as a foundation shell, not as the final role-permission data-driven staff dashboard.

---

### Dashboard Components Approval

Status: APPROVED

Approved new dashboard components:
- `DashboardMetricCard.vue`
- `DashboardActivityFeed.vue`
- `DashboardPeriodFilter.vue`
- `DashboardViewToggle.vue`
- `DashboardSectionFilter.vue`
- `DashboardSvgChart.vue`

Approved constraints:
- Components are frontend-only at this phase.
- Components are reusable.
- Components use static/placeholder data in this phase.
- No external chart dependency was introduced.
- No Chart.js was used.
- No ApexCharts was used.
- No ECharts or Highcharts were used.
- No `resources/js` implementation was introduced.
- Components are intended for Nuxt frontend under `frontend/`.

---

### DashboardSvgChart SSR Fix

Status: APPROVED

Problem found by Codex:
- `DashboardSvgChart.vue` originally used:
  - `Math.random()`
- This was unsafe in a Nuxt SSR context because it could produce different values between server render and client hydration.

Approved fix:
- `Math.random()` was removed.
- SVG gradient IDs are now deterministic.
- The ID is derived from stable component input such as `props.title`.
- `linearGradient` and `url(#...)` use the same deterministic identifier.
- The chart remains SVG-only.
- No `window` or `localStorage` usage was introduced.

Static review confirmed:
- No `Math.random()` remains in `DashboardSvgChart.vue`.
- No other random render-path value exists in the component.
- The SVG chart is SSR-safe.
- The frontend build passes.

---

### Sidebar Toggle Fix

Status: APPROVED

Problem found by Codex:
- `AppTopBar` emitted `toggle-sidebar`.
- The layout-level `toggleSidebar()` handler was empty or ineffective.
- `AppSidebar` managed collapsed state separately.

Approved fix:
- `layouts/admin.vue` now manages:
  - `isSidebarCollapsed`
- `layouts/staff.vue` now manages:
  - `isSidebarCollapsed`
- `AppTopBar` emits:
  - `toggle-sidebar`
- The layout receives this event and toggles the collapsed state.
- `AppSidebar` receives the collapsed state via prop.

Approved behavior:
- The sidebar toggle is no longer a dead UI action.
- Admin and staff layouts both wire the toggle correctly.
- No browser-only API is used.
- No SSR issue was introduced.

---

### User Avatar Type Fix

Status: APPROVED

Problem found by Codex:
- `IdentityCard.vue` and `UserDropdown.vue` use `user.avatar`.
- `frontend/types/auth.ts` did not define `avatar` on the `User` type.

Approved fix:
- `frontend/types/auth.ts` now includes:
  - `avatar?: string | null`

Approved result:
- `IdentityCard.vue` remains compatible with the user type.
- `UserDropdown.vue` remains compatible with the user type.
- No unsafe direct user field access was introduced.

---

### Authenticated Runtime QA

Status: APPROVED

Runtime QA scope:
- `/admin` without cookie.
- `/staff` without cookie.
- `/admin` with admin cookie.
- `/staff` with admin cookie.
- `/staff` with staff cookie.
- `/admin` with staff cookie.

Runtime server status:
- Laravel `/up`: HTTP 200
- Nuxt home: HTTP 200

QA users used locally:
- `qa-admin@example.com`
- `qa-staff@example.com`

Important note:
- These QA users were created or updated in the local database only for runtime testing.
- Local Spatie roles `admin` and `staff` were created through `tinker` if missing in the local DB.
- No migration, seeder, or code change was performed for this runtime QA step.

API login verification:
- Admin login API: PASS
- Staff login API: PASS
- Tokens were not printed in full.

Route behavior without cookie:
- `/admin` redirects to `/auth/login?redirect=/admin`
- `/staff` redirects to `/auth/login?redirect=/staff`

Route behavior with admin cookie:
- `/admin` returns HTTP 200.
- Admin dashboard content appears.
- Admin sidebar shows the approved admin navigation items.
- Admin top bar appears.
- Dashboard KPIs appear.
- SVG chart appears.
- Activity feed appears.
- Demo data notice appears.
- `/staff` also returns HTTP 200 for admin.

Route behavior with staff cookie:
- `/staff` returns HTTP 200.
- Staff dashboard content appears.
- Staff KPIs appear.
- Staff activity feed appears.
- Staff demo/notes content appears.
- `/admin` redirects to `/staff`.
- Staff does not see the admin dashboard.

SSR/runtime verification:
- No SSR crash appeared.
- No hydration error appeared in the tested output.
- No `Cannot read properties` error appeared.
- No `Math.random` output appeared.
- SVG chart ID was deterministic.

Build verification:
- Frontend build passed.
- No blocking build errors occurred.

---

### Known Non-Blocking Notes

The following notes are not blockers for Step 5B approval:

1. `StatsCard.vue` and `StatsChart.vue` exist and overlap conceptually with:
   - `DashboardMetricCard.vue`
   - `DashboardSvgChart.vue`

   These should be cleaned or consolidated in a later frontend cleanup step.

2. `frontend/stores/dashboardStore.ts` exists but is not yet used by the new dashboard pages.

   This is acceptable because Step 5B intentionally uses static placeholder data. API-driven dashboard integration is deferred.

3. Vue Router warnings may appear for admin subroutes such as:
   - `/admin/users`
   - `/admin/staff`
   - `/admin/roles`
   - `/admin/works`
   - `/admin/orders`
   - `/admin/bookings`
   - `/admin/contests`
   - `/admin/wallet`
   - `/admin/reports`
   - `/admin/analytics`
   - `/admin/settings`

   These routes are referenced in sidebar navigation but their pages are not built yet. This is expected and non-blocking for Step 5B.

4. Dashboard values are demo/static placeholders.

   This is intentional for App Shell foundation and will be replaced by API-driven data in a later step.

---

### Explicit Non-Scope

Step 5B does not include:
- Building admin subpages.
- Building staff subpages.
- Building roles and permissions management UI.
- Building dashboard production analytics.
- Wiring dashboard pages to final production API data.
- Seeding production dashboard permissions.
- Cleaning duplicate old dashboard components.
- Implementing `/admin/settings`.
- Implementing `/admin/users`.
- Implementing `/admin/orders`.
- Implementing `/admin/bookings`.
- Implementing `/admin/reports`.
- Implementing `/admin/analytics`.

---

### Final Decision

Step 5A — Admin/Staff Dashboard Current State Audit is APPROVED.

Step 5B — Admin/Staff App Shell Foundation is APPROVED.

Step 5B-Fix1 — SSR and Shell Interaction Fix is APPROVED.

Step 5B-R — Authenticated Runtime QA is APPROVED.

The Admin and Staff dashboard foundation is now verified at:
- Static review level.
- SSR-safety level.
- Build level.
- Authenticated runtime level.
- Role-based route behavior level.

The project is ready to proceed to the next dashboard phase after this documentation step.

## اعتماد قرارات مرحلة Dashboard Visual Foundation — 2026-06-23

تم اعتماد القرارات التالية من مالك المشروع بخصوص المرحلة الحالية من بناء لوحة التحكم:

1. لوحة Admin/Staff الحالية تُعتبر Visual Prototype / App Shell Foundation، وليست Dashboard Production نهائية.
2. الغرض الحالي من لوحة التحكم هو تثبيت الهوية البصرية، التخطيط العام، العناصر الثابتة، App Shell، الشريط العلوي، القائمة الجانبية، الخلفية، نظام اللغة، نظام المظهر، وأسلوب عرض البطاقات والرسوم.
3. البيانات الحالية المعروضة في Dashboard هي بيانات تجريبية مؤقتة للمعاينة البصرية فقط.
4. البيانات التجريبية لا تُستخدم لاتخاذ أي قرارات تشغيلية أو إدارية حقيقية.
5. يجب إضافة تلميحات واضحة داخل واجهة Dashboard توضّح أن البيانات الحالية تجريبية، مثل:
   - بيانات تجريبية
   - Prototype
   - سيتم استبدالها لاحقًا بإحصاءات حقيقية من API
6. سيتم حذف أو استبدال جميع البيانات التجريبية لاحقًا عند تنفيذ Dashboard Production وربطها بإحصاءات حقيقية من Backend API.
7. وجود روابط في Sidebar تؤدي مؤقتًا إلى صفحات غير مبنية بعد مقبول خلال مرحلة تثبيت اللوحة الأم البصرية، وسيتم بناء الصفحات حسب ترتيب PROJECT_MAP.
8. صفحات /client و /designer لم تُبنَ بعد ضمن هذه المرحلة، وسيتم إنشاء Placeholder بسيط لكل منهما مؤقتًا لمنع 404 أثناء المعاينة.
9. نظام الأدوار والصلاحيات الكامل لم يبدأ بعد، وسيتم بناؤه بعد الانتهاء من تثبيت Dashboard Visual Foundation.
10. Dashboard API لا تعتبر خطرًا مباشرًا خلال مرحلة البناء المحلية لأن الوصول إلى السيرفر محلي ومحصور بالمالك، لكنها يجب أن تُحمى قبل أي نشر خارجي أو استخدام غير محلي.
11. مسارات Debug الموجودة في routes/web.php لا تخدم المعاينة البصرية ولا Auth، وسيتم إزالتها في خطوة إصلاح مستقلة.
12. الاختبارات القديمة الفاشلة سيتم تنظيفها بما يتوافق مع قرار Laravel API Only، ولن يتم إرجاع Web/Auth routes قديمة فقط لإرضاء اختبارات غير متوافقة مع المعمارية الجديدة.
13. نقل Laravel إلى backend/ معتمد مبدئيًا، لكنه لن يُنفذ الآن، بل سيتم لاحقًا في مرحلة معمارية مستقلة بعد استقرار الاختبارات والتشغيل.
14. إصلاح root npm run dev معتمد بشرط عدم كسر الواجهة أو Auth أو الصفحات التي تم تجهيزها.
15. تحويل الاختبارات من SQLite إلى PostgreSQL معتمد التزامًا بالمرجع، لكنه سيتم بعد تنظيف الاختبارات القديمة أولًا حتى لا تختلط أسباب الفشل.
16. لا يتم الانتقال إلى بناء Dashboard Production أو Users أو Roles أو Works قبل اعتماد اكتمال مرحلة Dashboard Visual Foundation واختبارها وتوثيقها.

## سجل تنفيذ تثبيت الأساس — Step 1/2/3 — 2026-06-23

تم تنفيذ واعتماد الخطوات التالية ضمن مرحلة تثبيت أساس Dashboard Visual Foundation، مع الحفاظ على أن لوحة Admin/Staff الحالية ما زالت Visual Prototype / App Shell Foundation وليست Dashboard Production.

### Step 1 — إصلاحات آمنة لمرحلة Dashboard Visual Foundation

تم تنفيذ Step 1 بنجاح ضمن النطاق المحدد.

المنجزات:

1. إزالة مسارات Debug من `routes/web.php`:
   - `/session-test/set`
   - `/session-test/get`
   - `/dispatch-test-job`

2. أصبح `routes/web.php` شبه فارغ ومناسبًا لوضع Laravel API Only.

3. لم تعد `php artisan route:list` تعرض مسارات Debug السابقة.

4. تمت إضافة تلميح بصري واضح داخل:
   - `frontend/pages/admin/index.vue`
   - `frontend/pages/staff/index.vue`

   يوضح أن بيانات Dashboard الحالية تجريبية للمعاينة البصرية فقط، وسيتم استبدالها لاحقًا بإحصاءات حقيقية من Backend API.

5. تم إنشاء Placeholder مؤقت للصفحات:
   - `/client` عبر `frontend/pages/client/index.vue`
   - `/designer` عبر `frontend/pages/designer/index.vue`

6. صفحات Placeholder لا تحتوي على API calls ولا بيانات تشغيلية، والغرض منها منع 404 أثناء مرحلة بناء App Shell.

نتائج التحقق:

- `AuthApiTest` ناجح: 8/8.
- `PasswordResetApiTest` ناجح: 6/6.
- `cd frontend && npm run build` ناجح.
- Dashboard ما زالت Demo/Prototype ولم يتم ربطها ببيانات API.
- لم يتم تعديل `PROJECT_MAP.md` أثناء Step 1.
- لم يتم تعديل `package.json`.
- لم يتم نقل Laravel إلى `backend/`.
- لم يتم حذف `resources/js`.

### Step 2 — تنظيف الاختبارات القديمة لتطابق Laravel API Only

تم تنفيذ Step 2 بنجاح.

الهدف من الخطوة كان إزالة أو تعديل اختبارات Laravel Web/Breeze القديمة التي لم تعد تطابق قرار Laravel API Only، بدون إعادة إنشاء Web Auth Routes قديمة.

الملفات التي تم حذفها لأنها لم تعد تمثل المعمارية الحالية:

1. `tests/Feature/Auth/AuthenticationTest.php`
   - كان يعتمد على `/login` و`/logout` عبر Web Session.
   - التغطية الحالية موجودة عبر `AuthApiTest`.

2. `tests/Feature/Auth/RegistrationTest.php`
   - كان يعتمد على `/register` كمسار Web.
   - التسجيل الحالي مغطى عبر Auth API.

3. `tests/Feature/Auth/PasswordResetTest.php`
   - كان يعتمد على Web Password Reset flow.
   - التدفق الحالي مغطى عبر `PasswordResetApiTest`.

4. `tests/Feature/Auth/EmailVerificationTest.php`
   - كان يفترض وجود route باسم `verification.verify`.
   - Email Verification API لم يُبنَ بعد ضمن هذه المرحلة.

الملف الذي تم تعديله:

- `tests/Feature/ExampleTest.php`

أصبح يختبر Health Endpoint `/up` بدل اختبار Laravel `/`، لأن Nuxt هو المسؤول عن الصفحة الرئيسية العامة وليس Laravel.

نتائج التحقق:

- `php artisan test` ناجح بالكامل:
  - الاختبارات: 16
  - الناجحة: 16
  - Assertions: 54
  - Failures: لا يوجد
  - Errors: لا يوجد

- `AuthApiTest` ناجح: 8/8.
- `PasswordResetApiTest` ناجح: 6/6.

قيود مؤكدة:

- لم يتم إنشاء Web Auth Routes قديمة.
- لم يتم إضافة route باسم `verification.verify`.
- لم يتم تعديل `routes/web.php`.
- لم يتم تعديل `routes/api.php`.
- لم يتم تعديل `AuthApiController`.
- لم يتم تعديل Password Reset API.
- لم يتم تعديل Frontend.
- لم يتم تعديل `PROJECT_MAP.md`.
- لم يتم تعديل package أو composer files.
- لم يتم نقل Laravel إلى `backend/`.

### Step 3 — إصلاح أوامر التشغيل من جذر المشروع

تم تنفيذ Step 3 بنجاح.

الهدف من الخطوة كان جعل `npm run dev` من جذر المشروع يشغّل بيئة التطوير الحالية كاملة، مع مراعاة أن Laravel ما زال حاليًا في جذر المشروع ولم يتم نقله بعد إلى `backend/`.

تم تثبيت:

- `concurrently@10.0.3` كاعتمادية تطوير فقط.

الملفات المعدلة:

- `package.json`
- `package-lock.json`

أصبحت سكربتات التشغيل الجذرية كالتالي:

```json
{
  "scripts": {
    "dev": "concurrently \"php artisan serve\" \"cd frontend && npm run dev\" \"php artisan queue:work\"",
    "dev:backend": "php artisan serve",
    "dev:frontend": "cd frontend && npm run dev",
    "dev:queue": "php artisan queue:work",
    "build": "cd frontend && npm run build"
  }
}
```

أصبح `npm run dev` يشغّل:

1. Laravel API Server من جذر المشروع.
2. Nuxt Dev Server من `frontend/`.
3. Laravel Queue Worker من جذر المشروع.

قيود مؤكدة:

* لم يعد `npm run dev` يشغّل Vite القديم مباشرة.
* لم يتم حذف Vite القديم أو `vite.config.js`.
* لم يتم حذف `resources/js`.
* لم يتم نقل Laravel إلى `backend/`.
* لم يتم تعديل Frontend files.
* لم يتم تعديل Backend production code.
* لم يتم تعديل routes.
* لم يتم تشغيل migrations.
* لم يتم تشغيل composer install/update.
* لم يتم تشغيل npm update.

نتائج التحقق:

* `php artisan test` ناجح بالكامل:

  * الاختبارات: 16
  * الناجحة: 16
  * Assertions: 54
  * Failures: لا يوجد
  * Errors: لا يوجد

* `cd frontend && npm run build` ناجح.

تحذيرات غير مانعة ظهرت أثناء البناء:

1. تحذير Sourcemap من `nuxt:module-preload-polyfill`.
2. `authStore.ts` مستورد ديناميكيًا وثابتًا، لذلك لن ينتقل إلى chunk مستقل.
3. بعض chunks أكبر من 500 kB بعد التصغير.

هذه التحذيرات لا تمنع اعتماد Step 3، وسيتم التعامل معها لاحقًا ضمن مرحلة تحسين الأداء وتنظيف الحزم.

### حالة الاعتماد بعد Step 1/2/3

تم اعتماد Step 1 وStep 2 وStep 3 كخطوات تثبيت أساس ناجحة.

الحالة الحالية بعد هذه الخطوات:

1. Laravel Web Debug Routes أُزيلت.
2. Dashboard Visual Foundation توضح الآن أن البيانات تجريبية.
3. صفحات `/client` و`/designer` لم تعد 404، وتعرض Placeholder مؤقت.
4. اختبارات Laravel الكاملة تمر بنجاح.
5. `npm run dev` من جذر المشروع أصبح يشغّل Laravel + Nuxt + Queue.
6. Laravel ما زال حاليًا في جذر المشروع.
7. `backend/` ما زال فارغًا.
8. نقل Laravel إلى `backend/` مؤجل إلى مرحلة معمارية مستقلة لاحقة.
9. Dashboard ما زالت Prototype ولم يتم ربطها بإحصاءات API حقيقية.
10. نظام Roles & Permissions الكامل لم يبدأ بعد، وسيُنفّذ بعد اعتماد اكتمال Dashboard Visual Foundation.

## سجل تنفيذ تثبيت الأساس — Step 5 — PostgreSQL Test Database — 2026-06-23

تم تنفيذ واعتماد Step 5 بنجاح لتحويل بيئة اختبارات Laravel من SQLite in-memory إلى PostgreSQL test database، التزامًا بقاعدة المشروع التي تمنع استخدام SQLite.

### الهدف من الخطوة

كان الهدف من هذه الخطوة هو:

1. إزالة اعتماد اختبارات Laravel على SQLite in-memory.
2. تشغيل الاختبارات على PostgreSQL مثل بيئة المشروع الحقيقية.
3. استخدام قاعدة اختبار منفصلة حتى لا تتأثر قاعدة التطوير.
4. الحفاظ على استقرار اختبارات Auth وPassword Reset بعد التحويل.
5. عدم تعديل أي كود إنتاجي أو Frontend أو Routes أثناء هذه الخطوة.

### قاعدة الاختبار المعتمدة

تم اعتماد قاعدة اختبار منفصلة:

- اسم قاعدة الاختبار: `yemen_motion_test`
- قاعدة التطوير: `yemen_motion`
- المستخدم: `kali`
- المضيف: `127.0.0.1`
- المنفذ: `5433`

تم التأكد من أن قاعدة الاختبار منفصلة عن قاعدة التطوير، وأن الاختبارات لا تستخدم قاعدة التطوير `yemen_motion`.

### إعداد المصادقة المحلي

تم تجهيز مصادقة PostgreSQL غير تفاعلية محليًا عبر ملف المستخدم:

- `~/.pgpass`

مع ضبط الصلاحيات:

- `chmod 600 ~/.pgpass`

ملاحظات أمنية:

1. لم يتم عرض محتوى `~/.pgpass`.
2. لم يتم تخزين كلمة مرور PostgreSQL داخل المشروع.
3. لم يتم وضع كلمة مرور داخل `phpunit.xml`.
4. لم يتم وضع كلمة مرور داخل `.env.example`.
5. لا يجب مشاركة محتوى `~/.pgpass` أو إدخاله في Git أو في ملف الذاكرة.

### الملفات المعدلة في Step 5

تم تعديل ملفين فقط:

1. `phpunit.xml`
2. `.env.example`

### إعدادات phpunit.xml بعد Step 5

أصبح `phpunit.xml` يستخدم PostgreSQL لبيئة الاختبار:

```xml
<env name="CACHE_STORE" value="array"/>
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_HOST" value="127.0.0.1"/>
<env name="DB_PORT" value="5433"/>
<env name="DB_DATABASE" value="yemen_motion_test"/>
<env name="DB_USERNAME" value="kali"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
```

قيود مؤكدة:

* لم يعد `phpunit.xml` يستخدم `DB_CONNECTION=sqlite`.
* لم يعد `phpunit.xml` يستخدم `DB_DATABASE=:memory:`.
* لا يوجد `DB_PASSWORD` داخل `phpunit.xml`.
* لا يوجد `sqlite` أو `:memory:` داخل `phpunit.xml`.

### إعدادات .env.example بعد Step 5

تم تحديث `.env.example` ليعكس PostgreSQL وRedis كإعدادات افتراضية للمشروع:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=yemen_motion
DB_USERNAME=kali
DB_PASSWORD=

SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=phpredis
```

قيود مؤكدة:

* `DB_PASSWORD` بقي فارغًا داخل `.env.example`.
* لم يتم إدخال أي كلمة مرور أو سر داخل `.env.example`.
* لم يتم تعديل `.env`.

### نتائج الاختبارات بعد Step 5

تم تشغيل الاختبارات الكاملة على PostgreSQL test database:

* الأمر: `php artisan test`
* النتيجة: ناجح
* عدد الاختبارات: 16
* الاختبارات الناجحة: 16
* Assertions: 54
* Failures: لا يوجد
* Errors: لا يوجد

### قيود مؤكدة أثناء Step 5

تم الالتزام بالقيود التالية:

1. لم يتم تعديل `.env`.
2. لم يتم حذف `database/database.sqlite`.
3. لم يتم تعديل production code.
4. لم يتم تعديل routes.
5. لم يتم تعديل Frontend.
6. لم يتم تعديل `PROJECT_MAP.md` أثناء Step 5.
7. لم يتم تشغيل migrations يدويًا على قاعدة التطوير.
8. لم يتم نقل Laravel إلى `backend/`.
9. لم يتم استخدام قاعدة التطوير `yemen_motion` للاختبارات.

### حالة الاعتماد بعد Step 5

تم اعتماد Step 5 كخطوة ناجحة ضمن تثبيت الأساس.

الحالة الحالية بعد Step 5:

1. اختبارات Laravel الكاملة تمر بنجاح.
2. اختبارات Laravel تعمل الآن على PostgreSQL test database بدل SQLite.
3. قاعدة التطوير منفصلة عن قاعدة الاختبار.
4. إعدادات `.env.example` أصبحت أقرب للمرجع المعماري.
5. لا يزال `database/database.sqlite` موجودًا، لكنه لم يعد مستخدمًا في `phpunit.xml`.
6. حذف `database/database.sqlite` أو تنظيفه مؤجل إلى خطوة تنظيف مستقلة لاحقة.

## سجل تنفيذ تثبيت الأساس — Step 12 — Backend Auth Hardening — 2026-06-24

### 1. هدف Step 12

تقوية Backend Auth الحالي فقط، بدون لمس Dashboard أو Frontend أو routes أو PROJECT_MAP أثناء التنفيذ البرمجي.

### 2. الملفات التي تم تعديلها في Step 12

- `app/Http/Controllers/Api/AuthApiController.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Requests/Auth/ForgotPasswordRequest.php`
- `app/Http/Requests/Auth/ResetPasswordRequest.php`
- `database/seeders/DatabaseSeeder.php`
- `tests/Feature/Auth/PasswordResetApiTest.php`

`RegisterRequest.php` تمت مراجعته ولم يحتج تعديلًا لأن قواعده كانت مطابقة للمطلوب.

### 3. Register Transaction

عملية إنشاء المستخدم وتعيين الدور أصبحت داخل `DB::transaction()`، مما يمنع بقاء user جزئي إذا فشل `assignRole`.

### 4. Login Rate Limiting

- تم استخدام `RateLimiter` من Laravel.
- المفتاح مبني على:
  - البريد الإلكتروني (lowercase)
  - IP address
- الحد: 5 محاولات.
- الفترة: 60 ثانية.
- عند تجاوز الحد يرجع JSON 429.
- عند فشل login يتم تسجيل محاولة عبر `RateLimiter::hit($key)`.
- عند نجاح login يتم مسح المحاولات عبر `RateLimiter::clear($key)`.

### 5. Login Validation

تم إزالة التحقق المكرر داخل `login` وأصبح يعتمد على `$request->validated()` من `LoginRequest`.

### 6. Auth Request Validation

- **LoginRequest**: `email` (required, string, email, max:255)، `password` (required, string).
- **ForgotPasswordRequest**: `email` (required, string, email, max:255).
- **ResetPasswordRequest**: `email` (required, string, email, max:255)، `token` (required, string)، `password` (required, string, min:8, confirmed).
- **RegisterRequest**: بقي مطابقًا للمطلوب: name (required/string/max:255)، email (required/string/email/max:255/unique:users,email)، password (required/string/min:8/confirmed)، role (required/string/in:client,designer).

### 7. Password Reset Token Revocation

إعادة تعيين كلمة المرور أصبحت تحذف كل Sanctum tokens القديمة للمستخدم عبر `$user->tokens()->delete();`.

### 8. اختبار إبطال Sanctum Tokens

تمت إضافة اختبار صريح `test_password_reset_revokes_existing_sanctum_tokens` يتحقق من:
- إنشاء مستخدم.
- إنشاء Sanctum token قديم.
- التأكد من وجود token في `personal_access_tokens`.
- تنفيذ reset password.
- التأكد من حذف توكنات المستخدم القديمة.

### 9. الحفاظ على الاختبار القديم

بقي الاختبار القديم `test_old_password_no_longer_works_after_reset` موجودًا، ومراجعات Step 12-F أصلحت الخطأ الذي استبدل الاختبار القديم بدل إضافته، وأصبح كلا الاختبارين موجودين معًا.

### 10. DatabaseSeeder

تمت إضافة `$this->call(AuthRolesSeeder::class);` في `run()` قبل إنشاء المستخدمين التجريبيين. كلمة المرور الثابتة `password123` بقيت كدين تقني مؤجل ولم تُصلح ضمن هذه الخطوة.

### 11. نتائج الاختبارات النهائية

- `php artisan test --filter=PasswordResetApiTest`: 7 اختبارات ناجحة.
- `php artisan test --filter=AuthApiTest`: 8 اختبارات ناجحة.
- `php artisan test`: 17 اختبارًا ناجحًا.

### 12. فحوصات Step 12-B/C/D/E/F

مراجعات القراءة فقط كشفت أن:
- Git index بقي فارغًا طوال الخطوات.
- عدة ملفات Auth ما زالت غير متتبعة (`??`) ويجب أخذ ذلك في الاعتبار عند خطة staging.
- تم اكتشاف أن الاختبار الجديد استبدل اختبارًا قديمًا بالخطأ (Step 12-D).
- تم إصلاح ذلك في Step 12-F بإبقاء الاختبار القديم والجديد معًا.
- لا توجد مشكلة تمنع اعتماد Step 12 نهائيًا.

### 13. ما لم يتم عمله

- لم يتم تعديل Dashboard.
- لم يتم تعديل Frontend.
- لم يتم تعديل routes.
- لم يتم تعديل migrations.
- لم يتم تشغيل migrations.
- لم يتم تعديل composer أو package.
- لم يتم عمل git add أو commit.

### 14. الديون المؤجلة

- Dashboard RBAC.
- Bearer token vs SPA cookie.
- seed password ثابتة `password123`.
- Email verification.
- الملفات غير المتتبعة يجب إدخالها ضمن خطة staging لاحقًا.
- Dashboard API ما زالت تحتاج RBAC لاحقًا قبل الإنتاج.

## سجل تنفيذ تثبيت الأساس — Git Baseline + Legacy Removal + Validation — 2026-06-24

### 1. ملخص المرحلة

هذه المرحلة رتبت Git baseline بعد فصل المشروع إلى:

- Backend Laravel API-only
- Frontend Nuxt داخل `frontend/`
- PostgreSQL test database عبر `phpunit.xml`
- إزالة واجهة Laravel/Vite/Breeze القديمة من الجذر

أصبح المشروع الآن قائمًا على بنية نظيفة: Laravel API-only + Nuxt frontend منفصل.

### 2. commits المعتمدة

```text
21fd437 chore: protect local artifacts with gitignore
fe00765 chore: add root dev scripts
4ec1b2d chore: configure postgres test environment
f578d25 feat: add backend api foundation
a428ad8 test: add api auth coverage
d5b45c6 chore: make laravel api-only
9b38423 feat: add frontend app shell
5391b12 fix: add missing frontend composables
8eae7f7 chore: remove legacy laravel vite frontend
```

### 3. نتيجة حذف Legacy

commit `8eae7f7 chore: remove legacy laravel vite frontend` حذف ملفات Legacy Laravel/Vite/Breeze القديمة فقط، ومنها:

- `vite.config.js`
- `resources/js/*`
- `resources/css/app.css`
- `resources/views/welcome.blade.php`
- root public PNG logos القديمة (`public/logo.png`, `public/logo2.png`, `public/logo3.png`, `public/name.png`)

ملاحظات مهمة:

- `frontend/` لم يتأثر نهائيًا.
- `package.json` و `package-lock.json` (على مستوى الجذر) لم يتم تنظيف dependencies فيهما بعد من بقايا Breeze/Vite.
- `public/build/` بقي ignored artifact ولم يدخل Git.
- تنظيف root dependencies سيكون لاحقًا كخطوة مستقلة إن لزم.

### 4. نتائج validation

```text
php artisan route:list = نجح، 13 route
php artisan route:list --path=api = نجح، 9 API routes
php artisan test = نجح، 17 tests passed، 57 assertions
cd frontend && npm run build = نجح
```

ملاحظة مهمة حول الاختبارات:

- اختبار Laravel احتاج `DB_PASSWORD` كمتغير بيئة مؤقت في الطرفية المحلية فقط (`export DB_PASSWORD=...`).
- لم يتم حفظ كلمة المرور في `phpunit.xml`.
- لم يتم حفظ كلمة المرور في `.env`.
- لم تتم طباعة كلمة المرور في أي مخرج.
- تم تنفيذ `unset DB_PASSWORD` بعد الاختبار مباشرة.

### 5. تحذيرات Frontend غير مانعة

Build نجح مع warnings غير مانعة:

- sourcemap warning من Nuxt/Vite: `[plugin nuxt:module-preload-polyfill] Sourcemap is likely to be incorrect`
- dynamic/static import warning حول `authStore.ts`: مستورد ديناميكيًا من `useApiClient.ts` وثابتًا من 10 ملفات أخرى
- chunk size warning: بعض الـ chunks أكبر من 500 kB (أكبرها 732 kB)

هذه ديون تحسين لاحقة وليست مانعًا للـ baseline.

### 6. الملفات التي بقيت Hold

الملفات/المجموعات التالية بقيت خارج baseline وتحتاج قرارات مستقلة:

```text
app/Providers/AppServiceProvider.php
config/app.php
app/Jobs/
AGENTS.md
BUILD_PLAN.md
SPEC_ADMIN_PACK.txt
YEMEN_MOTION_COMPREHENSIVE_PROJECT_REPORT_2026-06-22.md
frontend/components/dashboard/StatsCard.vue
frontend/components/dashboard/StatsChart.vue
frontend/stores/dashboardStore.ts
audit_login_page.sh
audit_login_page_fast.sh
```

### 7. الحالة الرسمية بعد هذه المرحلة

Baseline قابل للدفع جزئيًا بعد توثيق PROJECT_MAP، لكن working tree لا يزال يحتوي ملفات Hold غير محسومة.

لا يتم push النهائي قبل حسم أو تجاهل أو توثيق ملفات Hold المتبقية.

---

## Memory Update — 2026-07-02 — Admin Management Tables Refinement

- **Status:** APPROVED — مقبول مبدئيًا.
- **Branch:** `main`
- **Commit:** `775afde`
- **Commit Message:** `feat: refine admin management tables`

### Scope

تم تثبيت وتحسين صفحات إدارة الأدمن التالية:

- `/admin/users`
- `/admin/staff`
- `/admin/roles`

### Completed

- إعادة بناء الهوية البصرية لصفحات الإدارة الفرعية بما ينسجم مع لوحة التحكم الرئيسية.
- تثبيت summary cards ملوّنة وواضحة.
- تثبيت read-only notice في الصفحات.
- إزالة أدوات البحث/الفلترة الداخلية غير المطلوبة من الجداول.
- تحويل الجداول إلى semantic HTML tables مستقرة.
- حذف `colgroup` من جداول users/staff/roles.
- حذف مقابض تمديد الأعمدة `ym-column-resize-handle`.
- حذف كود resize الميت:
  - `tableColumns`
  - `columnWidths`
  - `minimumColumnWidths`
  - `resizingColumn`
  - `tableWidth`
  - `startColumnResize`
  - `handleColumnResize`
  - `resizeDelta`
  - `stopColumnResize`
- تثبيت ترتيب `th/td` في الجداول الثلاثة.
- ضبط قص الأسماء والبريد بطول 15 حرفًا.
- ضبط اتجاه النص المختلط RTL/LTR للأسماء والبريد.
- تثبيت التاريخ بصيغة:
  - `YYYY-MM-DD HH:mm`
- إضافة دعم الفرز في backend لصفحة المستخدمين عبر:
  - `sort_by`
  - `sort_direction`
- اعتماد الفرز في users/staff عبر backend.
- اعتماد الفرز في roles محليًا داخل الصفحة لأن endpoint الأدوار يعيد القائمة كاملة دون pagination.

### Validation

- `git diff --check` نظيف.
- dead resize check نظيف للصفحات الثلاث.
- forbidden internal controls check نظيف:
  - لا `type="search"`
  - لا `searchPlaceholder`
  - لا `<select>`
- semantic table check أكد وجود:
  - `ym-users-table`
  - `ym-staff-table`
  - `ym-roles-table`
- `npm run build` نجح وانتهى بـ:
  - `✨ Build complete!`
- `php artisan test` نجح:
  - `23 passed`
  - `129 assertions`

### Deferred

إمكانية تمديد الأعمدة مؤجلة عمدًا إلى مهمة مستقلة لاحقًا.  
لا يتم إرجاع `resize/colgroup/columnWidths` إلى صفحات users/staff/roles في المرحلة الحالية.

### Final Decision

صفحات الإدارة التالية مثبتة ومقبولة مبدئيًا:

- `/admin/users`
- `/admin/staff`
- `/admin/roles`

لا يتم فتح تعديلات بصرية جديدة عليها إلا من خلال مهمة scoped جديدة وواضحة.

