# YM-Lite SDD Current Handoff

## 1. Current Baseline

- المشروع الآن على branch `main`.
- آخر حالة مستقرة موثقة هي commit `aadbcea fix: improve mobile dashboard sidebar layout`.
- تم اعتماد YM-Lite SDD كسير عمل خفيف وآمن.
- كل مراحل UI polish الأخيرة تم تنفيذها كخطوات صغيرة ومراجعتها وبناؤها قبل الاعتماد.

## 2. Project State

- Laravel backend في جذر المشروع.
- Nuxt frontend داخل `frontend/`.
- `/admin` هي القالب المرجعي للوحة التحكم.
- `/staff` موجودة وتمت مواءمة واجهتها بصريا مع نمط لوحة التحكم.
- placeholder routes موجودة لـ admin/staff.

## 3. Recent Stable UI Polish Commits

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

1. Admin TopBar polish
2. Admin Hero Card polish
3. Admin Metric Cards polish
4. Admin Chart Card polish
5. Admin Controls Card polish
6. Admin Activity Feed polish
7. Staff Dashboard visual consistency
8. Responsive P0 Mobile Sidebar/Main Layout fix
9. Current Handoff update

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

## 6. Current State After `aadbcea`

- `/admin` و `/staff` أصبحا قابلين للاستخدام على mobile بعد إصلاح sidebar.
- عند `375px` لم يعد `main` مضغوطا إلى `87px`.
- sidebar أصبح drawer على mobile مع زر فتح وbackdrop.
- Light Mode مستقر ولا توجد عودة للخلفية البيضاء الكاملة.
- Dark Mode مستقر.

## 7. Critical Design And Workflow Decisions

- عدم استخدام native `title` tooltip.
- عدم استخدام `data-tooltip` أو CSS pseudo tooltip مثل `content: attr(data-tooltip)`.
- التلميحات المقبولة هي Vue/Teleport/dynamic tooltip أو `role="tooltip"` حسب المكون.
- لا يتم استخدام `git add .`.
- لا يتم تعديل `.env` أو الأسرار.
- لا يتم تنفيذ install/update/migrate إلا بطلب صريح.
- كل مرحلة يجب أن تكون محدودة النطاق.
- أي تعديل UI يجب أن يحافظ على Light Mode و Dark Mode.
- لا يتم تعديل admin و staff معا إلا إذا كان السبب shell/layout مشتركا مثل إصلاح mobile sidebar.

## 8. Known Build Warnings

`npm run build` ينجح. التحذيرات التالية معروفة وقديمة/بنيوية وليست مانعا حاليا:

- `nuxt:module-preload-polyfill` sourcemap warning.
- `authStore.ts` dynamic/static import warning.
- Some chunks are larger than 500 kB after minification.

## 9. Sensitive Areas

- Auth
- `authStore.ts`
- `useApiClient.ts`
- middleware
- roles/permissions
- routes
- migrations
- `.env`
- package/composer dependencies

## 10. Recommended Next Steps

هذه الخطوات مقترحة لاحقا وليست منفذة بعد:

P1 Responsive Density Pass:

- تقليل كثافة Hero/TopBar على mobile.
- تحسين Chart labels على mobile.
- مراجعة ActivityFeed وMetricCard عند أقل من `430px`.
- فحص contrast رقمي WCAG.
- فحص tab order وkeyboard navigation.

## 11. How A New Agent Should Start

- اقرأ `README.md`.
- اقرأ `00-project-context.md`.
- اقرأ `01-agent-rules.md`.
- اقرأ `02-workflow.md`.
- اقرأ `03-frontend-ui-standards.md`.
- اقرأ هذا الملف.
- ثم اقرأ الـ Task المطلوب فقط.
