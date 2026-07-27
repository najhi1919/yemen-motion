# YM-FOUNDATION-STABILIZATION-001A-CLOSURE

## الحالة

مكتملة ومغلقة بتاريخ `2026-07-27`.

## المهمة

تثبيت ملكية الحزم وتوحيد أوامر التشغيل والبناء في مشروع يمن موشن.

## النطاق المكتمل

- أصبح جذر المشروع مسؤولًا عن تنسيق التشغيل فقط.
- أصبح `frontend/` المالك الحصري لحزم واجهة المستخدم.
- أزيل تكرار حزم Nuxt وVue وTailwind وThree.js من الجذر.
- ثُبتت Nuxt على `4.4.8`.
- ثُبت `@nuxtjs/tailwindcss` على `6.14.0`.
- ثُبت Tailwind CSS على `3.4.1`.
- ثُبت Three.js على `0.184.0`.
- أزيل `@tailwindcss/postcss` غير المتوافق مع عقد Tailwind 3 الحالي.
- أصبح `composer dev` يفوض التشغيل إلى `npm run dev`.
- أصبح `composer setup` يثبت حزم الجذر والواجهة ثم يبني الواجهة.
- أزيل الحقل القديم `"main": "vite.config.js"` من `package.json`.

## عقد التشغيل

يشغل `npm run dev` أربع خدمات متزامنة:

```text
backend  → php artisan serve --host=127.0.0.1 --port=8000
frontend → Nuxt على 127.0.0.1:3000
queue    → php artisan queue:work redis --queue=works-media,default
logs     → php artisan pail --timeout=0
```

يحافظ عقد Queue على:

```text
--sleep=1
--tries=3
--backoff=5
--timeout=600
--max-time=3600
```

ويستخدم `concurrently` إعادة التشغيل المستمرة مع الإنهاء الآمن لبقية العمليات عند الفشل.

## الملفات المعدلة

```text
composer.json
package.json
package-lock.json
frontend/package.json
frontend/package-lock.json
tests/Feature/Admin/WorksAdminMediaApiTest.php
```

## أدلة التحقق

- `composer validate`: ناجح.
- `npm ci` في الجذر: ناجح.
- `npm ci --prefix frontend`: ناجح.
- بناء Nuxt الإنتاجي: ناجح.
- اختبارات Backend الكاملة: `1095` اختبارًا و`9372` تأكيدًا، جميعها ناجحة.
- تشغيل Backend وFrontend وQueue وPail: ناجح.
- الإيقاف المتزامن دون بقاء عمليات تابعة: ناجح.
- `git diff --check`: ناجح.
- نطاق الملفات الستة: مطابق.

## Commit التنفيذ

```text
50715902cb194ea68be4cbbeae917d36fc467aac
chore(foundation): stabilize scripts and dependency ownership
```

تم دفع Commit إلى `origin/main` والتحقق من تطابق الفرع المحلي والبعيد.

## التحذيرات غير المانعة

- تحذير Sourcemap.
- الاستيراد الثابت والديناميكي لـ`authStore`.
- تحذير حجم بعض Chunks.
- تحذيرات حزم انتقالية قديمة.

## حدود الإغلاق

- لا تغيير في عقود Backend أوAPI.
- لا تغيير في وظائف واجهة المستخدم.
- لا ترقية عامة للحزم خارج النطاق.
- لا يشمل الإغلاق إعداد CI أوتحديث README.

## القرار

أُغلق تثبيت ملكية الحزم وعقد التشغيل بنجاح، وأصبح المشروع جاهزًا لمحطة `YM-FOUNDATION-STABILIZATION-001B`.
