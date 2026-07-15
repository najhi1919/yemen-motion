# YM-WORKS-READONLY-MVP-DOCS-001 — Admin Works Read-only MVP Baseline

## الهدف

توثيق إغلاق مرحلة القراءة والتنظيم لقسم Admin Works بعد اكتمال عقود Backend الحقيقية وصفحات Frontend الثمانية، مع تثبيت حدود الأمان والبيانات والوظائف غير المنفذة.

## نطاق التعديل

هذه مهمة Documentation فقط، ويقتصر نطاقها على:

- تحديث `PROJECT_MAP.md` بإضافة baseline زمني جديد.
- إنشاء ملف SDD الحالي لتسجيل عقد التسليم.

لا تتضمن المهمة أي تعديل في Backend أو Frontend أو routes أو tests أو migrations أو seeders أو config أو ملفات package/composer.

## ملخص ما اكتمل

- اكتمل baseline قراءة وتنظيم داخلي لإدارة الأعمال.
- اكتملت النظرة العامة، وكل الأعمال، وطلبات المراجعة، والظهور والتمييز، والبلاغات والمخالفات، والتصنيفات والوسوم، وسجل الأعمال، وإعدادات وصلاحيات الأعمال.
- تعتمد جميع صفحات Frontend على APIs حقيقية، ولا تستخدم fake أو demo data.
- تغطي الواجهات حالات auth pending وloading وerror وempty وforbidden وsuccess وفق نمط كل صفحة.
- لا توجد action endpoints أو أزرار تنفيذية مدعومة ضمن هذه المرحلة.

## Backend APIs

```text
GET /api/admin/works/access
GET /api/admin/works/overview
GET /api/admin/works
GET /api/admin/works/{work}
GET /api/admin/works/review
GET /api/admin/works/visibility
GET /api/admin/works/reports
GET /api/admin/works/taxonomy
GET /api/admin/works/activity
GET /api/admin/works/settings
```

جميع هذه العقود للقراءة والتنظيم فقط. لا توجد endpoints من نوع POST أو PUT أو PATCH أو DELETE لإجراءات Admin Works في هذا baseline.

## Frontend pages

| الصفحة | المسار |
|--------|--------|
| النظرة العامة | `/admin/works` |
| كل الأعمال | `/admin/works/all` |
| طلبات المراجعة | `/admin/works/review` |
| الظهور والتمييز | `/admin/works/visibility` |
| البلاغات والمخالفات | `/admin/works/reports` |
| التصنيفات والوسوم | `/admin/works/taxonomy` |
| سجل الأعمال | `/admin/works/activity` |
| إعدادات وصلاحيات الأعمال | `/admin/works/settings` |

يظهر التنقل الفرعي عبر contextual Sidebar وفق عقد `/api/admin/works/access` وصلاحيات الحساب الحالي.

## قواعد التفويض

- `super-admin` مسموح له بالوصول الكامل إلى أسطح Admin Works المكتملة.
- يحتاج `admin` و`staff` إلى `admin.works.access` وإلى صلاحيات `admin.works.*` الدقيقة المطلوبة لكل صفحة أو قائمة أو تفصيل.
- يمنع `client` و`designer` من Admin Works دائمًا، حتى إذا مُنحا صلاحيات عرضية بالخطأ.
- يمنع أي دور غير داخلي من هذه العقود.
- Backend هو الحارس النهائي، وتنتظر صفحات Frontend اكتمال `authStore.isInitialized` ولا تطلب البيانات عند المنع المحلي.
- يحتوي permission registry الحالي على `78` صلاحية مسجلة ضمن `admin.works`، ولا يتضمن `admin.works.delete` أو `admin.works.force_delete`.

## حماية البيانات الحساسة

- لا تعرض الاستجابات `email`.
- لا تعرض `password` أو `token` أو `cookie`.
- لا تعرض `internal_notes` أو `rejection_reason` أو `change_request_notes` في عقود القوائم؛ لا تتاح إلا عبر detail API وبصلاحية خاصة.
- لا تعيد raw models أو raw config أو user rows أو work rows خامًا.
- لا تعيد metadata أو payload غير لازمة للعقد.
- تستخدم كل استجابة allowlist محددة للحقول والمعاملات المناسبة لها.

## القيود المقصودة في هذه المرحلة

### غياب action endpoints

لم تنفذ المرحلة أيًا من العمليات التالية:

- publish أو unpublish.
- hide أو restore visibility.
- feature أو unfeature.
- pin أو unpin.
- approve أو reject أو request changes.
- reports review أو dismiss أو archive.
- taxonomy create أو update أو disable أو merge أو bulk assign.
- settings mutation.
- hard delete.

وجود أسماء صلاحيات لهذه العمليات في permission registry يثبت عقد التفويض المستقبلي فقط، ولا يعني وجود endpoints منفذة.

### مصادر البيانات المؤقتة

- يعتمد Reports على `works.reports_count`، ولا يوجد جدول بلاغات أعمال مستقل.
- يعتمد Taxonomy على `works.category_id`، ولا يوجد جدول tags أو دعم علاقات وسوم حقيقية بعد.
- يعتمد Activity على lifecycle timestamps في `works`، ولا يقرأ `audit_events` ولا يملك جدول activity مستقلًا.
- تعتمد Settings على static defaults وregistered permissions، ولا يوجد persistent settings table.

## آخر baseline

```text
9f6688f feat: add works settings admin page
```

## نتائج التحقق

نتائج التحقق المعتمدة عند إغلاق baseline:

```text
Full test suite: 540 passed / 3615 assertions
Settings API: 14 passed / 135 assertions
WorksPermissionRegistryTest: 3 passed / 13 assertions
WorksAccessGateTest: 11 passed / 50 assertions
```

لم تُشغّل الاختبارات ضمن مهمة التوثيق الحالية؛ الأرقام السابقة تسجل نتائج baseline المعتمدة.

## القادم المقترح

ترتيب التنفيذ المقترح التالي:

```text
YM-WORKS-VISIBILITY-ACTIONS-API-001
YM-WORKS-VISIBILITY-ACTIONS-UI-001
```

بعد ذلك يحدد القرار اللاحق ترتيب Review Actions وReports Actions وTaxonomy schema أو actions. لا يعد أي من هذه الأعمال منفذًا ضمن مرحلة Read-only MVP الحالية.
