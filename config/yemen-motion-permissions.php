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
