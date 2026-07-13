<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Yemen Motion Permission Registry
    |--------------------------------------------------------------------------
    |
    | This registry defines baseline system permissions.
    | Super Admin receives all registered permissions.
    | Other roles receive only their baseline permissions.
    |
    | Runtime UI-managed custom permissions may be added later without changing
    | this registry.
    |
    */

    'protected_roles' => [
        'super-admin',
        'admin',
    ],

    'roles' => [
        'super-admin',
        'admin',
        'staff',
        'client',
        'designer',
    ],

    'permissions' => [
        [
            'name' => 'admin.access.view',
            'group' => 'admin.access',
            'label_ar' => 'عرض مركز الصلاحيات',
        ],
        [
            'name' => 'admin.users.view',
            'group' => 'admin.users',
            'label_ar' => 'عرض المستخدمين',
        ],
        [
            'name' => 'admin.users.assign_roles',
            'group' => 'admin.users',
            'label_ar' => 'إسناد الأدوار للمستخدمين',
        ],
        [
            'name' => 'admin.users.assign_permissions',
            'group' => 'admin.users',
            'label_ar' => 'إسناد صلاحيات مباشرة للمستخدمين',
        ],
        [
            'name' => 'admin.roles.view',
            'group' => 'admin.roles',
            'label_ar' => 'عرض الأدوار',
        ],
        [
            'name' => 'admin.roles.create',
            'group' => 'admin.roles',
            'label_ar' => 'إنشاء دور',
        ],
        [
            'name' => 'admin.roles.update',
            'group' => 'admin.roles',
            'label_ar' => 'تعديل دور',
        ],
        [
            'name' => 'admin.roles.delete',
            'group' => 'admin.roles',
            'label_ar' => 'حذف دور',
        ],
        [
            'name' => 'admin.roles.sync_permissions',
            'group' => 'admin.roles',
            'label_ar' => 'ربط الصلاحيات بالأدوار',
        ],
        [
            'name' => 'admin.permissions.view',
            'group' => 'admin.permissions',
            'label_ar' => 'عرض الصلاحيات',
        ],
        [
            'name' => 'admin.permissions.create',
            'group' => 'admin.permissions',
            'label_ar' => 'إنشاء صلاحية',
        ],
        [
            'name' => 'admin.permissions.update',
            'group' => 'admin.permissions',
            'label_ar' => 'تعديل صلاحية',
        ],
        [
            'name' => 'admin.permissions.delete',
            'group' => 'admin.permissions',
            'label_ar' => 'حذف صلاحية',
        ],
        [
            'name' => 'admin.works.access',
            'group' => 'admin.works',
            'label_ar' => 'دخول إدارة الأعمال',
        ],
        [
            'name' => 'admin.works.overview.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض نظرة عامة على الأعمال',
        ],
        [
            'name' => 'admin.works.all.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض صفحة كل الأعمال',
        ],
        [
            'name' => 'admin.works.review.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض صفحة مراجعة الأعمال',
        ],
        [
            'name' => 'admin.works.visibility.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض صفحة ظهور الأعمال وتمييزها',
        ],
        [
            'name' => 'admin.works.reports.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض صفحة بلاغات الأعمال',
        ],
        [
            'name' => 'admin.works.taxonomy.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض صفحة تصنيفات الأعمال ووسومها',
        ],
        [
            'name' => 'admin.works.activity.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض صفحة سجل الأعمال',
        ],
        [
            'name' => 'admin.works.settings.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض صفحة إعدادات الأعمال',
        ],
        [
            'name' => 'admin.works.list',
            'group' => 'admin.works',
            'label_ar' => 'عرض قائمة الأعمال',
        ],
        [
            'name' => 'admin.works.detail.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض تفاصيل العمل',
        ],
        [
            'name' => 'admin.works.media.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض وسائط العمل',
        ],
        [
            'name' => 'admin.works.metadata.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض بيانات العمل الوصفية',
        ],
        [
            'name' => 'admin.works.designer.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض مصمم العمل',
        ],
        [
            'name' => 'admin.works.private_notes.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض الملاحظات الداخلية للعمل',
        ],
        [
            'name' => 'admin.works.create',
            'group' => 'admin.works',
            'label_ar' => 'إنشاء عمل إداريًا',
        ],
        [
            'name' => 'admin.works.update.basic',
            'group' => 'admin.works',
            'label_ar' => 'تعديل بيانات العمل الأساسية',
        ],
        [
            'name' => 'admin.works.update.media',
            'group' => 'admin.works',
            'label_ar' => 'تعديل وسائط العمل',
        ],
        [
            'name' => 'admin.works.update.pricing',
            'group' => 'admin.works',
            'label_ar' => 'تعديل سعر العمل',
        ],
        [
            'name' => 'admin.works.update.delivery',
            'group' => 'admin.works',
            'label_ar' => 'تعديل مدة تسليم العمل',
        ],
        [
            'name' => 'admin.works.update.category',
            'group' => 'admin.works',
            'label_ar' => 'تعديل تصنيف العمل',
        ],
        [
            'name' => 'admin.works.update.tags',
            'group' => 'admin.works',
            'label_ar' => 'تعديل وسوم العمل',
        ],
        [
            'name' => 'admin.works.update.designer',
            'group' => 'admin.works',
            'label_ar' => 'تعديل مصمم العمل',
        ],
        [
            'name' => 'admin.works.update.private_notes',
            'group' => 'admin.works',
            'label_ar' => 'تعديل الملاحظات الداخلية للعمل',
        ],
        [
            'name' => 'admin.works.review.start',
            'group' => 'admin.works',
            'label_ar' => 'بدء مراجعة العمل',
        ],
        [
            'name' => 'admin.works.review.approve',
            'group' => 'admin.works',
            'label_ar' => 'اعتماد العمل',
        ],
        [
            'name' => 'admin.works.review.request_changes',
            'group' => 'admin.works',
            'label_ar' => 'طلب تعديل العمل',
        ],
        [
            'name' => 'admin.works.review.reject',
            'group' => 'admin.works',
            'label_ar' => 'رفض العمل',
        ],
        [
            'name' => 'admin.works.review.publish_after_approval',
            'group' => 'admin.works',
            'label_ar' => 'نشر العمل بعد اعتماده',
        ],
        [
            'name' => 'admin.works.review.assign_reviewer',
            'group' => 'admin.works',
            'label_ar' => 'تعيين مراجع للعمل',
        ],
        [
            'name' => 'admin.works.review.reopen',
            'group' => 'admin.works',
            'label_ar' => 'إعادة فتح مراجعة العمل',
        ],
        [
            'name' => 'admin.works.publish',
            'group' => 'admin.works',
            'label_ar' => 'نشر العمل',
        ],
        [
            'name' => 'admin.works.unpublish',
            'group' => 'admin.works',
            'label_ar' => 'إلغاء نشر العمل',
        ],
        [
            'name' => 'admin.works.hide',
            'group' => 'admin.works',
            'label_ar' => 'إخفاء العمل',
        ],
        [
            'name' => 'admin.works.restore_visibility',
            'group' => 'admin.works',
            'label_ar' => 'استعادة ظهور العمل',
        ],
        [
            'name' => 'admin.works.feature',
            'group' => 'admin.works',
            'label_ar' => 'تمييز العمل',
        ],
        [
            'name' => 'admin.works.unfeature',
            'group' => 'admin.works',
            'label_ar' => 'إزالة تمييز العمل',
        ],
        [
            'name' => 'admin.works.pin',
            'group' => 'admin.works',
            'label_ar' => 'تثبيت العمل',
        ],
        [
            'name' => 'admin.works.unpin',
            'group' => 'admin.works',
            'label_ar' => 'إلغاء تثبيت العمل',
        ],
        [
            'name' => 'admin.works.visibility.order',
            'group' => 'admin.works',
            'label_ar' => 'ترتيب ظهور الأعمال',
        ],
        [
            'name' => 'admin.works.reports.list',
            'group' => 'admin.works',
            'label_ar' => 'عرض قائمة بلاغات الأعمال',
        ],
        [
            'name' => 'admin.works.reports.detail.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض تفاصيل بلاغ العمل',
        ],
        [
            'name' => 'admin.works.reports.review',
            'group' => 'admin.works',
            'label_ar' => 'مراجعة بلاغ العمل',
        ],
        [
            'name' => 'admin.works.reports.dismiss',
            'group' => 'admin.works',
            'label_ar' => 'تجاهل بلاغ العمل',
        ],
        [
            'name' => 'admin.works.reports.request_changes',
            'group' => 'admin.works',
            'label_ar' => 'طلب تعديل العمل من البلاغ',
        ],
        [
            'name' => 'admin.works.reports.hide_work',
            'group' => 'admin.works',
            'label_ar' => 'إخفاء العمل من البلاغ',
        ],
        [
            'name' => 'admin.works.reports.restore_work',
            'group' => 'admin.works',
            'label_ar' => 'استعادة العمل من البلاغ',
        ],
        [
            'name' => 'admin.works.reports.archive',
            'group' => 'admin.works',
            'label_ar' => 'أرشفة بلاغ العمل',
        ],
        [
            'name' => 'admin.works.taxonomy.categories.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض تصنيفات الأعمال',
        ],
        [
            'name' => 'admin.works.taxonomy.categories.create',
            'group' => 'admin.works',
            'label_ar' => 'إنشاء تصنيف أعمال',
        ],
        [
            'name' => 'admin.works.taxonomy.categories.update',
            'group' => 'admin.works',
            'label_ar' => 'تعديل تصنيف أعمال',
        ],
        [
            'name' => 'admin.works.taxonomy.categories.disable',
            'group' => 'admin.works',
            'label_ar' => 'تعطيل تصنيف أعمال',
        ],
        [
            'name' => 'admin.works.taxonomy.tags.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض وسوم الأعمال',
        ],
        [
            'name' => 'admin.works.taxonomy.tags.create',
            'group' => 'admin.works',
            'label_ar' => 'إنشاء وسم أعمال',
        ],
        [
            'name' => 'admin.works.taxonomy.tags.update',
            'group' => 'admin.works',
            'label_ar' => 'تعديل وسم أعمال',
        ],
        [
            'name' => 'admin.works.taxonomy.tags.disable',
            'group' => 'admin.works',
            'label_ar' => 'تعطيل وسم أعمال',
        ],
        [
            'name' => 'admin.works.taxonomy.bulk_assign',
            'group' => 'admin.works',
            'label_ar' => 'إسناد تصنيفات ووسوم للأعمال جماعيًا',
        ],
        [
            'name' => 'admin.works.taxonomy.merge_tags',
            'group' => 'admin.works',
            'label_ar' => 'دمج وسوم الأعمال',
        ],
        [
            'name' => 'admin.works.bulk.publish',
            'group' => 'admin.works',
            'label_ar' => 'نشر الأعمال جماعيًا',
        ],
        [
            'name' => 'admin.works.bulk.hide',
            'group' => 'admin.works',
            'label_ar' => 'إخفاء الأعمال جماعيًا',
        ],
        [
            'name' => 'admin.works.bulk.archive',
            'group' => 'admin.works',
            'label_ar' => 'أرشفة الأعمال جماعيًا',
        ],
        [
            'name' => 'admin.works.bulk.restore',
            'group' => 'admin.works',
            'label_ar' => 'استعادة الأعمال جماعيًا',
        ],
        [
            'name' => 'admin.works.bulk.category_update',
            'group' => 'admin.works',
            'label_ar' => 'تحديث تصنيف الأعمال جماعيًا',
        ],
        [
            'name' => 'admin.works.bulk.tags_update',
            'group' => 'admin.works',
            'label_ar' => 'تحديث وسوم الأعمال جماعيًا',
        ],
        [
            'name' => 'admin.works.bulk.assign_reviewer',
            'group' => 'admin.works',
            'label_ar' => 'تعيين مراجع للأعمال جماعيًا',
        ],
        [
            'name' => 'admin.works.activity.list',
            'group' => 'admin.works',
            'label_ar' => 'عرض سجل نشاط الأعمال',
        ],
        [
            'name' => 'admin.works.activity.detail.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض تفاصيل نشاط العمل',
        ],
        [
            'name' => 'admin.works.audit.metadata.view',
            'group' => 'admin.works',
            'label_ar' => 'عرض بيانات تدقيق الأعمال الوصفية',
        ],
        [
            'name' => 'admin.works.audit.export_denied',
            'group' => 'admin.works',
            'label_ar' => 'تثبيت منع تصدير تدقيق الأعمال',
        ],
        [
            'name' => 'admin.works.settings.manage',
            'group' => 'admin.works',
            'label_ar' => 'إدارة إعدادات الأعمال',
        ],
        [
            'name' => 'admin.works.settings.workflow.manage',
            'group' => 'admin.works',
            'label_ar' => 'إدارة سير عمل الأعمال',
        ],
        [
            'name' => 'admin.works.settings.review_sla.manage',
            'group' => 'admin.works',
            'label_ar' => 'إدارة مهلة مراجعة الأعمال',
        ],
        [
            'name' => 'admin.works.settings.direct_publish_trust.manage',
            'group' => 'admin.works',
            'label_ar' => 'إدارة ثقة النشر المباشر للأعمال',
        ],
        [
            'name' => 'admin.works.settings.media_limits.manage',
            'group' => 'admin.works',
            'label_ar' => 'إدارة حدود وسائط الأعمال',
        ],
        [
            'name' => 'admin.works.search',
            'group' => 'admin.works',
            'label_ar' => 'البحث في الأعمال',
        ],
        [
            'name' => 'admin.works.search.private_metadata',
            'group' => 'admin.works',
            'label_ar' => 'البحث في بيانات الأعمال الداخلية',
        ],
        [
            'name' => 'admin.works.search.designer',
            'group' => 'admin.works',
            'label_ar' => 'البحث عن الأعمال حسب المصمم',
        ],
        [
            'name' => 'admin.works.search.reports',
            'group' => 'admin.works',
            'label_ar' => 'البحث في بلاغات الأعمال',
        ],
        [
            'name' => 'dashboard.overview.view',
            'group' => 'dashboard',
            'label_ar' => 'عرض ملخص لوحة التحكم',
        ],
        [
            'name' => 'dashboard.stats.view',
            'group' => 'dashboard',
            'label_ar' => 'عرض إحصائيات لوحة التحكم',
        ],
        [
            'name' => 'dashboard.activity.view',
            'group' => 'dashboard',
            'label_ar' => 'عرض نشاط لوحة التحكم',
        ],
        [
            'name' => 'dashboard.chart.view',
            'group' => 'dashboard',
            'label_ar' => 'عرض رسوم لوحة التحكم',
        ],
        [
            'name' => 'platform.settings.view',
            'group' => 'platform.settings',
            'label_ar' => 'عرض إعدادات المنصة',
        ],
        [
            'name' => 'platform.settings.update',
            'group' => 'platform.settings',
            'label_ar' => 'تعديل إعدادات المنصة',
        ],
    ],

    'role_permissions' => [
        'admin' => [
            'admin.users.view',
            'admin.roles.view',
            'dashboard.overview.view',
            'dashboard.stats.view',
            'dashboard.activity.view',
            'dashboard.chart.view',
        ],

        'staff' => [
            'dashboard.overview.view',
        ],

        'client' => [],

        'designer' => [],
    ],
];
