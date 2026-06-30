# YM-Lite SDD Current Handoff

## 1. Current Baseline

- المشروع الآن على branch `main`.
- آخر حالة مستقرة موثقة هي commit `c7dff54 fix: reduce staff mobile dashboard density`.
- `origin/main` متزامن مع `c7dff54`.
- تم اعتماد YM-Lite SDD كسير عمل خفيف وآمن.
- كل مراحل UI polish الأخيرة تم تنفيذها كخطوات صغيرة ومراجعتها وبناؤها قبل الاعتماد.

## 2. Project State

- Laravel backend في جذر المشروع.
- Nuxt frontend داخل `frontend/`.
- `/admin` هي القالب المرجعي للوحة التحكم.
- `/staff` موجودة وتمت مواءمة واجهتها بصريا مع نمط لوحة التحكم.
- placeholder routes موجودة لـ admin/staff.

## 3. Recent Stable UI Polish Commits

- `c7dff54 fix: reduce staff mobile dashboard density`
- `a7172a1 fix: improve admin mobile dashboard density`
- `aadbcea fix: improve mobile dashboard sidebar layout`
- `03fbb57 fix: polish staff dashboard visuals`
- `4f32adc fix: polish admin activity feed visuals`
- `0a41221 fix: polish admin controls card visuals`
- `9616ebf fix: polish admin chart card visuals`
- `7f54a54 fix: polish admin metric cards visuals`
- `a3faacf fix: polish admin hero card visuals`
- `0f37b3c fix: polish admin topbar visuals`
- `2818c99 docs: update YM-Lite SDD handoff status`
- `930f17d docs: add current YM-Lite SDD handoff`
- `06f77ca docs: add YM-Lite SDD workflow`

## 4. Completed Phases

1. TopBar polish ✅
2. Admin Hero polish ✅
3. Admin Metric Cards polish ✅
4. Admin Chart Card polish ✅
5. Admin Controls Card polish ✅
6. Admin Activity Feed polish ✅
7. Staff Dashboard polish ✅
8. Mobile Sidebar P0 ✅
9. Handoff update after dashboard polish ✅
10. P1-A Admin Mobile Density ✅
11. P1-B Staff Mobile Density ✅

## 5. Stable UI Files From The Polish Sequence

هذه الملفات كانت ضمن سلسلة التحسينات السابقة وأصبحت جزءا من الحالة المستقرة الحالية:

- `frontend/components/AppTopBar.vue`
- `frontend/pages/admin/index.vue`
- `frontend/components/dashboard/DashboardMetricCard.vue`
- `frontend/components/dashboard/DashboardSvgChart.vue`
- `frontend/components/dashboard/DashboardActivityFeed.vue`
- `frontend/pages/staff/index.vue`
- `frontend/layouts/admin.vue`
- `frontend/layouts/staff.vue`

## 6. Current State After `c7dff54`

- `/admin` و `/staff` أصبحا قابلين للاستخدام على mobile بعد إصلاح sidebar.
- عند `375px` لم يعد `main` مضغوطا إلى `87px`.
- sidebar أصبح drawer على mobile مع زر فتح وbackdrop.
- تم تقليل كثافة `/admin` و `/staff` على mobile/tablet ضمن مرحلتي P1-A و P1-B.
- Light Mode مستقر ولا توجد عودة للخلفية البيضاء الكاملة.
- Dark Mode مستقر.

## 7. Completed P1 Density Passes

### P1-A: Admin Mobile Density Pass ✅

تم تنفيذها في commit `a7172a1 fix: improve admin mobile dashboard density`.

شملت:

- تقليل كثافة `/admin` على mobile/tablet.
- إصلاح خروج chips/buttons داخل بطاقة عناصر العرض التفاعلية على tablet.
- إصلاح قوائم TopBar المنسدلة حتى تبقى داخل viewport بدون اقتطاع.
- إخفاء أسماء/أرقام الرسم البياني المتداخلة على mobile/tablet مع إبقائها على desktop.
- الحفاظ على الأعمدة والألوان والـ tooltip.

الملفات التي شملها commit:

- `frontend/pages/admin/index.vue`
- `frontend/components/AppTopBar.vue`
- `frontend/components/dashboard/DashboardSvgChart.vue`

### P1-B: Staff Mobile Density Pass ✅

تم تنفيذها في commit `c7dff54 fix: reduce staff mobile dashboard density`.

شملت:

- تقليل كثافة `/staff` على mobile/tablet.
- تحسين spacing/padding داخل Staff Hero.
- تحسين panels/task rows على الشاشات الصغيرة.
- الحفاظ على desktop تقريبا كما هو.
- عدم لمس admin أو المكونات المشتركة.

الملف الذي شمله commit:

- `frontend/pages/staff/index.vue`

## 8. Critical Design And Workflow Decisions

- عدم استخدام native `title` tooltip.
- عدم استخدام `data-tooltip` أو CSS pseudo tooltip مثل `content: attr(data-tooltip)`.
- التلميحات المقبولة هي Vue/Teleport/dynamic tooltip أو `role="tooltip"` حسب المكون.
- لا يتم استخدام `git add .`.
- لا يتم تعديل `.env` أو الأسرار.
- لا يتم تنفيذ install/update/migrate إلا بطلب صريح.
- كل مرحلة يجب أن تكون محدودة النطاق.
- أي تعديل UI يجب أن يحافظ على Light Mode و Dark Mode.
- لا يتم تعديل admin و staff معا إلا إذا كان السبب shell/layout مشتركا مثل إصلاح mobile sidebar.

## 9. Known Build Warnings

`npm run build` ينجح. التحذيرات التالية هي build warnings معروفة وقديمة/بنيوية وليست blockers حاليا:

- `nuxt:module-preload-polyfill` sourcemap warning.
- `authStore.ts` dynamic/static import warning.
- Some chunks are larger than 500 kB after minification.

## 10. Sensitive Areas

- Auth
- `authStore.ts`
- `useApiClient.ts`
- middleware
- roles/permissions
- routes
- migrations
- `.env`
- package/composer dependencies

## 11. Recommended Next Steps

المرحلة التالية المقترحة:

**Phase 2 Completion — Dashboard Core APIs & Real Data Integration**

- راجع `PROJECT_MAP.md` كمرجع أعلى لتفاصيل المرحلة 2.
- فحص Dashboard endpoints الحالية.
- تحديد الفجوة بين الموجود والمطلوب في المرحلة 2.
- إنشاء spec/task للربط الوظيفي.
- حماية Dashboard API بالصلاحيات.
- ربط Dashboard UI ببيانات فعلية تدريجيًا.

---

### مؤجلة (Postponed)

P1-C: Accessibility / Contrast / Keyboard Audit

- تم تأجيلها كتحسين UX لاحق.
- لا يتم فتح polish بصري عام جديد إلا بعد إكمال Dashboard Core إنتاجيًا.
- عند العودة إليها: Audit first, No code changes before scoped findings, Focus on contrast, focus-visible, keyboard navigation, semantic labels, and dropdown accessibility.

## 12. How A New Agent Should Start

- اقرأ `README.md`.
- اقرأ `00-project-context.md`.
- اقرأ `01-agent-rules.md`.
- اقرأ `02-workflow.md`.
- اقرأ `03-frontend-ui-standards.md`.
- اقرأ هذا الملف.
- ثم اقرأ الـ Task المطلوب فقط.
