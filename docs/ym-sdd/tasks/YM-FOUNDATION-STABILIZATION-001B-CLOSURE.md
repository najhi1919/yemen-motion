# YM-FOUNDATION-STABILIZATION-001B-CLOSURE

## الحالة

مكتملة ومغلقة بتاريخ `2026-07-28`.

## المهمة

إضافة خط أساس مستقر لـGitHub Actions يفحص Backend وFrontend آليًا عند تغييرات المشروع.

## ملف Workflow

```text
.github/workflows/ci.yml
```

## أحداث التشغيل

يعمل Workflow عند:

- `push` إلى `main`.
- `pull_request` يستهدف `main`.
- التشغيل اليدوي عبر `workflow_dispatch`.

## الأمان

صلاحيات Workflow محدودة إلى:

```yaml
permissions:
  contents: read
```

ويستخدم Checkout مع:

```yaml
persist-credentials: false
```

لا يملك Workflow صلاحية تعديل المستودع أوتنفيذ Push أوDeployment.

## التحكم في التشغيل المتكرر

```yaml
concurrency:
  group: ci-${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true
```

يلغي هذا العقد التشغيل الأقدم عند وصول تشغيل أحدث للمرجع نفسه.

## Job الواجهة

```text
Frontend Build (Node 24 / Nuxt)
```

العقد:

- Runner: `ubuntu-24.04`.
- Node.js: `24`.
- تثبيت حزم الجذر عبر `npm ci`.
- تثبيت حزم الواجهة عبر `npm ci --prefix frontend`.
- فحص شجرتي الحزم.
- تشغيل بناء Nuxt الإنتاجي عبر `npm run build`.
- استخدام Cache اعتمادًا على ملفي Lockfile.

## Job الخادم

```text
Backend Tests (PHP 8.4 / PostgreSQL 18)
```

العقد:

- Runner: `ubuntu-24.04`.
- PHP: `8.4`.
- Composer: `v2`.
- PostgreSQL service بالصورة `postgres:18-alpine`.
- قاعدة البيانات: `yemen_motion_test`.
- المستخدم: `postgres`.
- المنفذ: `5432`.
- Health check عبر `pg_isready`.
- تثبيت PHP extensions المطلوبة.
- التحقق من Composer manifest وPlatform requirements.
- تثبيت Composer dependencies.
- إنشاء مفتاح تطبيق مؤقت.
- تنفيذ migrations.
- تشغيل مجموعة اختبارات Laravel الكاملة.

## FFmpeg

يتحقق Workflow من توفر:

```text
ffmpeg
ffprobe
```

ويثبت FFmpeg عند غيابه.

هذا مطلوب لأن اختبارات وسائط الأعمال تنشئ فيديو Fixture حقيقيًا وتختبر استخراج البيانات وإنشاء Poster وحالة المعالجة والفشل الآمن.

## الخدمات غير المطلوبة

لا يشغل CI الحالي:

- Redis service.
- Queue worker مستقل.
- Laravel development server.
- Nuxt development server.
- Pail.
- SMTP server.

تستخدم الاختبارات:

```text
QUEUE_CONNECTION=sync
CACHE_STORE=array
SESSION_DRIVER=array
MAIL_MAILER=array
```

## التحقق المحلي

- تحليل YAML: ناجح.
- Trigger contract: ناجح.
- Permission contract: ناجح.
- Backend job contract: ناجح.
- Frontend job contract: ناجح.
- عدد PostgreSQL services: خدمة واحدة.
- Composer validation: ناجح.
- PHP platform requirements: ناجح.
- توفر FFmpeg وFFprobe: ناجح.
- اختبارات `WorksAdminMediaApiTest`: `64` اختبارًا و`638` تأكيدًا، جميعها ناجحة.
- Root `npm ci --dry-run`: ناجح.
- Frontend `npm ci --dry-run`: ناجح.
- بناء Nuxt الإنتاجي: ناجح.
- `git diff --check`: ناجح.
- نطاق الملفات: مطابق.
- Overall local verification: ناجح.

لم يكن `actionlint` مثبتًا محليًا، ولذلك كان الفحص اختياريًا ومتجاوزًا، وليس فحصًا منفذًا فعليًا.

## أول تشغيل فعلي على GitHub

```text
Workflow: CI
Run ID: 30241861433
Trigger: push
Branch: main
Commit: 59ae1b88effebc71fe47dd775203936dbbd09d1e
Conclusion: success
```

### نتائج Jobs

```text
Frontend Build (Node 24 / Nuxt)
Conclusion: success
Duration: 1m 02s
```

```text
Backend Tests (PHP 8.4 / PostgreSQL 18)
Conclusion: success
Duration: 5m 33s
```

نجحت جميع خطوات Checkout والتثبيت والبناء وPostgreSQL وFFmpeg وComposer وmigrations واختبارات Laravel، ولم توجد سجلات فاشلة.

## Commit التنفيذ

```text
59ae1b88effebc71fe47dd775203936dbbd09d1e
ci: add backend tests and frontend build workflow
```

تم دفع Commit إلى `origin/main` والتحقق من تطابق الفرع المحلي والبعيد.

## التحذيرات غير المانعة

- تحذير Sourcemap.
- الاستيراد الثابت والديناميكي لـ`authStore`.
- Chunk إنتاجي بحجم يقارب `732.65 kB`.

هذه التحذيرات لا تفشل Job الواجهة.

## حدود الإغلاق

- لم تُفعّل Branch Protection أوRepository Rulesets.
- لم تصبح Checks إلزامية قبل الدمج.
- لم يُضف Deployment أوArtifacts أوCoverage reports.
- لم تُضف Redis إلى CI.
- لم تُستخدم Secrets.
- لم تتغير ملفات التطبيق أوالاختبارات أوإعدادات PHP/NPM.
- لا يشمل الإغلاق تحديث README أو`PROJECT_MAP.md`.

## تجربة الاستخدام الناتجة

عند رفع Commit أوفتح Pull Request:

```text
GitHub Actions
└── CI
    ├── Frontend Build
    └── Backend Tests
```

يعرض GitHub علامة خضراء عند نجاح Jobين، أوعلامة حمراء مع اسم Job والخطوة والسجل عند الفشل.

## القرار

أُغلق GitHub Actions CI Baseline بنجاح بعد اجتياز التحقق المحلي وأول تشغيل فعلي على GitHub. أصبح المشروع جاهزًا للانتقال إلى `YM-FOUNDATION-STABILIZATION-001C`.
