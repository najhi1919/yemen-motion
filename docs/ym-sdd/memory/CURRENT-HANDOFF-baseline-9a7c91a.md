# استئناف العمل الحالي

## 1. Current Baseline

`9a7c91a fix: polish topbar and dashboard hero visuals`

## 2. Project State

- Laravel backend في جذر المشروع.
- Nuxt frontend داخل `frontend/`.
- `/admin` هي القالب المرجعي.
- `/staff` موجودة.
- placeholder routes موجودة لـ admin/staff.

## 3. Completed Work

- Light Mode haze fixed.
- Sidebar collapsed logo fixed.
- Dashboard chart alignment/text distortion fixed.
- Sidebar tooltips fixed.
- Dashboard control tooltips fixed.
- Duplicate/native tooltips removed.
- TopBar and Hero visuals temporarily polished.
- Placeholder pages added.

## 4. Critical UI Rules

- ممنوع `title` / `data-tooltip` / `.has-tooltip::after` / `content: attr(data-tooltip)`.
- Tooltip standard هو Vue state + `getBoundingClientRect()` + `Teleport to body` + `aria-label`.
- ممنوع blur/filter/drop-shadow ثقيل في Light Mode.
- Dashboard controls يجب أن تستخدم `fit-content` / `max-width` / `overflow: visible`.
- لا تلمس AppSidebar/BackgroundWatermark/dashboard filters بدون Task صريح.

## 5. Sensitive Areas

- Auth
- `authStore.ts`
- `useApiClient.ts`
- middleware
- roles/permissions
- routes
- migrations
- `.env`
- package/composer dependencies

## 6. Current Stop Point

- نحن في مرحلة تأسيس YM-Lite SDD.
- الملفات staged بانتظار commit.
- بعد commit سنستأنف العمل عبر Spec/Task وليس prompt عام.

## 7. Next Recommended Step

- commit: `docs: add YM-Lite SDD workflow`
- ثم إنشاء Task جديد لأي تحسين قادم.

## 8. How a new agent should start

- اقرأ `README.md`
- اقرأ `00-project-context.md`
- اقرأ `01-agent-rules.md`
- اقرأ `02-workflow.md`
- اقرأ `03-frontend-ui-standards.md`
- اقرأ هذا الملف
- ثم اقرأ الـ Task المطلوب فقط
