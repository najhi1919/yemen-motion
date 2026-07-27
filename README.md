# يمن موشن — Yemen Motion

[![CI](https://github.com/najhi1919/yemen-motion/actions/workflows/ci.yml/badge.svg)](https://github.com/najhi1919/yemen-motion/actions/workflows/ci.yml)

منصة إبداعية قيد التطوير لإدارة الأعمال والتصاميم والمونتاج والخدمات المرتبطة بها. يجمع المستودع بين Backend مبني على Laravel وواجهة مستقلة مبنية على Nuxt.

## الحالة الحالية

المشروع تحت التطوير النشط. المرجع المعماري والبنائي الأعلى هو [`PROJECT_MAP.md`](PROJECT_MAP.md)، بينما تحفظ ملفات [`docs/ym-sdd`](docs/ym-sdd) مواصفات التنفيذ والمهام ووثائق الإغلاق.

لا يعني نجاح البناء أوالاختبارات أن جميع وحدات المنتج العامة مكتملة.

## المكدس التقني

- PHP `^8.3`، مع اعتماد PHP `8.4` في CI.
- Laravel `^13.8`.
- PostgreSQL، مع PostgreSQL `18` في CI.
- Redis للـQueue وCache في بيئة التطوير.
- Node.js `24`.
- Nuxt `4.4.8`.
- Tailwind CSS `3.4.1`.
- Pinia `3.0.4`.
- FFmpeg وFFprobe لمعالجة وسائط الأعمال.
- GitHub Actions لفحص Backend وFrontend آليًا.

## بنية المستودع

```text
.
├── app/                      Laravel application code
├── database/                 migrations, factories, seeders
├── frontend/                 Nuxt application and UI dependencies
├── routes/                   Laravel routes
├── tests/                    Backend test suite
├── docs/ym-sdd/              implementation specifications and handoff memory
├── .github/workflows/ci.yml  GitHub Actions CI
├── composer.json             Backend dependencies and project orchestration
├── package.json              root development orchestration only
└── PROJECT_MAP.md            authoritative architecture and build roadmap
```

### ملكية الحزم

- جذر المستودع مسؤول عن تنسيق التشغيل، ويملك `concurrently` فقط.
- `frontend/` هو المالك الحصري لحزم Nuxt وVue وTailwind وبقية حزم الواجهة.
- لا تُنقل حزم الواجهة إلى الجذر دون مهمة مستقلة ومراجعة واضحة.

## المتطلبات

ثبّت الأدوات التالية قبل إعداد المشروع:

- PHP `8.4` مع Extensions المطلوبة في `composer.json`.
- Composer `2`.
- Node.js `24` وnpm.
- PostgreSQL.
- Redis.
- FFmpeg وFFprobe.

تحقق سريع:

```bash
php -v
composer --version
node --version
npm --version
psql --version
redis-cli --version
ffmpeg -version
ffprobe -version
```

## الإعداد لأول مرة

استنسخ المستودع، ثم جهّز ملف البيئة قبل تشغيل الإعداد:

```bash
git clone https://github.com/najhi1919/yemen-motion.git
cd yemen-motion

cp .env.example .env
```

عدّل القيم المحلية المطلوبة داخل `.env`، خصوصًا:

```text
APP_URL
FRONTEND_URL
DB_CONNECTION
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
REDIS_HOST
REDIS_PORT
```

لا ترفع ملف `.env` أوأي بيانات سرية إلى Git.

بعد إعداد PostgreSQL وRedis شغّل:

```bash
composer setup
```

ينفذ هذا الأمر:

1. تثبيت Composer dependencies.
2. إنشاء مفتاح التطبيق.
3. تنفيذ migrations.
4. تثبيت حزم الجذر بواسطة `npm ci`.
5. تثبيت حزم الواجهة بواسطة `npm ci --prefix frontend`.
6. بناء واجهة الإنتاج.

## تشغيل بيئة التطوير

الأمر المعتمد:

```bash
composer dev
```

ويمكن تشغيل العقد نفسه مباشرة عبر:

```bash
npm run dev
```

يشغّل الأمر أربع خدمات متزامنة:

| الخدمة | العنوان أوالأمر |
|---|---|
| Backend | `http://127.0.0.1:8000` |
| Frontend | `http://127.0.0.1:3000` |
| Queue | `works-media,default` عبر Redis |
| Logs | Laravel Pail |

أوقف المجموعة باستخدام `Ctrl+C`. يتولى `concurrently` إنهاء العمليات التابعة.

## الاختبارات والتحقق

### Backend

```bash
composer test
```

أوتشغيل Laravel مباشرة:

```bash
php artisan test
```

### Frontend production build

```bash
npm run build
```

### فحوص التثبيت

```bash
composer validate --strict --no-check-publish
composer check-platform-reqs
npm ls --depth=0
npm --prefix frontend ls --depth=0
git diff --check
```

## التكامل المستمر

Workflow الموجود في [`.github/workflows/ci.yml`](.github/workflows/ci.yml) يعمل عند:

- Push إلى `main`.
- Pull Request يستهدف `main`.
- تشغيل يدوي بواسطة `workflow_dispatch`.

ويشغّل Jobين متوازيين:

1. Backend Tests باستخدام PHP `8.4` وPostgreSQL `18` وFFmpeg.
2. Frontend Build باستخدام Node.js `24` وNuxt.

نجاح CI لا يستبدل المراجعة البشرية، ولم تُفعّل ضمن هذا الخط الأساس قواعد Branch Protection أوDeployment.

## التوثيق المعتمد

ابدأ القراءة بهذا الترتيب:

1. [`PROJECT_MAP.md`](PROJECT_MAP.md) — المرجع المعماري والبنائي الأعلى.
2. [`docs/ym-sdd/README.md`](docs/ym-sdd/README.md) — قواعد نظام التوثيق والتنفيذ.
3. [`docs/ym-sdd/tasks/README.md`](docs/ym-sdd/tasks/README.md) — عقد المهام ووثائق الإغلاق.
4. المواصفة والمهمة المرتبطتان بالنطاق الجاري.
5. وثائق الإغلاق السابقة ذات الصلة.

وثائق تثبيت الأساس الحالية:

- [`YM-FOUNDATION-STABILIZATION-001A-CLOSURE`](docs/ym-sdd/tasks/YM-FOUNDATION-STABILIZATION-001A-CLOSURE.md)
- [`YM-FOUNDATION-STABILIZATION-001B-CLOSURE`](docs/ym-sdd/tasks/YM-FOUNDATION-STABILIZATION-001B-CLOSURE.md)

## قواعد المساهمة

- لا تنفيذ دون مهمة محددة النطاق.
- لا تستخدم `git add .`.
- لا تعدّل ملفات خارج النطاق المسموح.
- لا تنفذ Commit أوPush قبل نجاح الفحوص المطلوبة.
- لا تضف Secrets أوملفات بيئة.
- حافظ على `PROJECT_MAP.md` بوصفه المرجع الأعلى، وعلى `docs/ym-sdd` بوصفه سجل التنفيذ.
