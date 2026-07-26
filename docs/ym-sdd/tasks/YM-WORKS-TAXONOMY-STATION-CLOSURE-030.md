# YM-WORKS-TAXONOMY-STATION-CLOSURE-030

## الحالة

مغلقة بتاريخ `2026-07-26`.

## السطح الإداري المغلق

```text
/admin/works/taxonomy
```

## النطاق المكتمل

- توحيد تبويبات النظرة العامة وكتالوج التصنيفات وكتالوج الوسوم.
- تطبيق Smart Tables بملخصات مختصرة وPopovers للتفاصيل.
- إضافة ترقيم متسلسل للجداول الثلاثة مع استمراره عبر Pagination.
- فرض Latin digits في العدادات والتواريخ والجداول والنوافذ.
- تحسين البحث والفلاتر والفرز وPagination الخادمية.
- تحسين Drawer تفاصيل تجميع التصنيف.
- الحفاظ على إنشاء وتعديل وتعطيل التصنيفات.
- الحفاظ على إنشاء وتعديل وتعطيل ودمج الوسوم.
- دعم Light وDark وRTL وLTR وDesktop وTablet وMobile.
- إزالة الأسماء التقنية والحقول الداخلية غير المناسبة من العرض المباشر.
- تثبيت القواعد البصرية العامة في:
  `docs/ym-sdd/standards/YM-ADMIN-VISUAL-RULES.md`.

## أدلة التحقق

```text
Taxonomy tests: 203 passed / 2059 assertions
Super Admin authorization: 8 passed / 238 assertions
git diff --check: passed
Frontend production build: Build complete (Client, Server, Nitro)
Manual visual and functional inspection: passed
```

## التحذيرات غير المانعة

- Sourcemap warning.
- `authStore` mixed static/dynamic import.
- Chunk size warning.

## حدود العقد الحالي

- لم تتغير عقود Backend أوAPI أوQuery parameters.
- لم تتغير قواعد Validation أوAudit أوالصلاحيات.
- لم تضف إجراءات حذف دائم أوBulk actions أوExport.
- لم تتغير دلالات Legacy taxonomy أوالمعرفات القديمة.
- يبقى Backend المرجع النهائي للصلاحيات والحالة والتحقق.

## القرار

محطة `/admin/works/taxonomy` مكتملة ومغلقة، وجاهزة للتثبيت والمزامنة.
