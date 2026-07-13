# YM-WORKS-PERMISSION-MATRIX-001 — Works Management Permissions

## Scope

يوثق هذا الملف عقد الصلاحيات الكامل المعتمد للتنفيذ القادم لقسم `/admin/works`. لا يعني هذا التوثيق أن الصلاحيات أو الصفحات منفذة حاليًا.

## Internal-only access rule

- `super-admin` مسموح له بجميع صلاحيات إدارة الأعمال.
- يخضع `admin` و`staff` وبقية الأدوار الداخلية للصلاحيات الدقيقة.
- يمنع `client` و`designer` من `/admin/works` وكل مساراته حتى عند منحهما صلاحيات بالخطأ.
- تخفي الواجهة أو تعطل العناصر غير المسموحة، لكن Backend هو الحارس النهائي عند التنفيذ.

## Permission groups

### Navigation and page access

```text
admin.works.access
admin.works.overview.view
admin.works.all.view
admin.works.review.view
admin.works.visibility.view
admin.works.reports.view
admin.works.taxonomy.view
admin.works.activity.view
admin.works.settings.view
```

### Read and detail permissions

```text
admin.works.list
admin.works.detail.view
admin.works.media.view
admin.works.metadata.view
admin.works.designer.view
admin.works.private_notes.view
```

### Create and update permissions

```text
admin.works.create
admin.works.update.basic
admin.works.update.media
admin.works.update.pricing
admin.works.update.delivery
admin.works.update.category
admin.works.update.tags
admin.works.update.designer
admin.works.update.private_notes
```

### Review workflow permissions

```text
admin.works.review.start
admin.works.review.approve
admin.works.review.request_changes
admin.works.review.reject
admin.works.review.publish_after_approval
admin.works.review.assign_reviewer
admin.works.review.reopen
```

### Visibility and promotion permissions

```text
admin.works.publish
admin.works.unpublish
admin.works.hide
admin.works.restore_visibility
admin.works.feature
admin.works.unfeature
admin.works.pin
admin.works.unpin
admin.works.visibility.order
```

### Reports and violations permissions

```text
admin.works.reports.list
admin.works.reports.detail.view
admin.works.reports.review
admin.works.reports.dismiss
admin.works.reports.request_changes
admin.works.reports.hide_work
admin.works.reports.restore_work
admin.works.reports.archive
```

### Taxonomy permissions

```text
admin.works.taxonomy.categories.view
admin.works.taxonomy.categories.create
admin.works.taxonomy.categories.update
admin.works.taxonomy.categories.disable
admin.works.taxonomy.tags.view
admin.works.taxonomy.tags.create
admin.works.taxonomy.tags.update
admin.works.taxonomy.tags.disable
admin.works.taxonomy.bulk_assign
admin.works.taxonomy.merge_tags
```

### Bulk action permissions

```text
admin.works.bulk.publish
admin.works.bulk.hide
admin.works.bulk.archive
admin.works.bulk.restore
admin.works.bulk.category_update
admin.works.bulk.tags_update
admin.works.bulk.assign_reviewer
```

### Activity and audit permissions

```text
admin.works.activity.list
admin.works.activity.detail.view
admin.works.audit.metadata.view
admin.works.audit.export_denied
```

`admin.works.audit.export_denied` يثبت أن Export غير متاح ضمن هذا العقد، ولا يمنح صلاحية تصدير.

### Settings permissions

```text
admin.works.settings.manage
admin.works.settings.workflow.manage
admin.works.settings.review_sla.manage
admin.works.settings.direct_publish_trust.manage
admin.works.settings.media_limits.manage
```

### Topbar search permissions

```text
admin.works.search
admin.works.search.private_metadata
admin.works.search.designer
admin.works.search.reports
```

## Page and Sidebar visibility

- لا يظهر رابط الأعمال لمن لا يملك أي صلاحية داخل Works.
- يعيد الدخول المباشر إلى `/admin/works` دون صلاحية استجابة `403`.
- إذا ملك المستخدم صلاحية قسم واحد فقط، تظهر إدارة الأعمال مع ذلك القسم وحده داخل التوسعة السياقية.
- تظهر الأقسام الفرعية في Sidebar وفق صلاحيات المستخدم، وليست روابط عامة دائمة.

## Button and field-level rules

- يظهر كل زر أو يتعطل بناءً على الصلاحية وحالة العمل.
- يحتاج زر النشر `admin.works.publish` وحالة مناسبة.
- يحتاج زر الرفض `admin.works.review.reject` وإدخال سبب.
- يحتاج زر التمييز `admin.works.feature`.
- تحتاج الحقول الحساسة لصلاحيات تحديث مستقلة: السعر `admin.works.update.pricing`، ومدة التسليم `admin.works.update.delivery`، والمصمم `admin.works.update.designer`، والملاحظات الداخلية `admin.works.update.private_notes`.

## Bulk actions

- لا تستنتج صلاحية الإجراء الجماعي من صلاحية الإجراء الفردي.
- تحتاج عمليات النشر والإخفاء والأرشفة والاستعادة وتحديث التصنيف والوسوم وتعيين المراجع إلى صلاحيات `admin.works.bulk.*` المستقلة المقابلة.

## Audit requirements

- تسجل كل عملية تغيير أو قرار أو إجراء جماعي في Audit.
- تسجل رفضيات الوصول المؤثرة عبر access denied flow.
- تحفظ metadata آمنة فقط، دون password أو token أو cookie أو raw payload أو بيانات شخصية غير ضرورية.
- تتبع أسماء الأحداث عقد `0.28`، ومنها `work.published` و`work.rejected` وبقية أحداث دورة العمل المعتمدة.

## Default role direction

| الدور | الاتجاه الافتراضي |
|-------|-------------------|
| `super-admin` | جميع الصلاحيات |
| `admin` | إدارة أعمال واسعة عدا الإعدادات الحساسة بحسب ربط الصلاحيات |
| `content-manager` | المراجعة والظهور والبلاغات والتصنيفات الأساسية بحسب الحاجة |
| `staff` | لا شيء افتراضيًا؛ يمنح ما يحتاجه صراحة |
| `client` / `designer` | ممنوعان من `/admin/works` |

## Hard delete boundary

- لا يعتمد hard delete زرًا إداريًا عاديًا داخل إدارة الأعمال.
- تعتمد دورة الإدارة على archive وrestore.
- أي حذف دائم تتطلبه الصيانة لاحقًا يكون خارج واجهة الإدارة اليومية، وبصلاحية عالية منفصلة، وخارج النطاق الحالي.

## Deferred implementation

- لا تنفيذ برمجي في هذه الخطوة.
- لا seeders في هذه الخطوة.
- لا tests في هذه الخطوة.
- يبدأ التنفيذ القادم بتسجيل الصلاحيات في registry/seeders، ثم اختبارات Backend، ثم UI.

## Latest baseline

Latest commit expected before this documentation step: `4ec6a62`.
