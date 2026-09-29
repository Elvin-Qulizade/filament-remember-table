<?php

return [

    'button' => [
        'label' => 'طرق العرض',
        'modified' => ':name (معدّل)',
    ],

    'actions' => [
        'save' => 'حفظ كطريقة عرض جديدة',
        'save_submit' => 'حفظ',
        'update' => 'تحديث طريقة العرض الحالية',
        'manage' => 'إدارة طرق العرض',
        'manage_submit' => 'حفظ التغييرات',
        'reset' => 'إعادة ضبط الجدول',
    ],

    'modals' => [
        'save' => [
            'heading' => 'حفظ طريقة العرض',
            'description' => 'يحفظ الفلاتر والبحث والترتيب والتجميع والأعمدة الحالية.',
        ],
        'update' => [
            'heading' => 'تحديث ":name"؟',
            'description' => 'سيتم استبدال طريقة العرض بإعدادات الجدول الحالية.',
        ],
        'manage' => [
            'heading' => 'إدارة طرق العرض',
        ],
    ],

    'fields' => [
        'name' => 'الاسم',
        'is_default' => 'طريقة العرض الافتراضية',
        'is_public' => 'مشاركة مع الجميع',
        'is_public_help' => 'يمكن للمستخدمين الآخرين تطبيقها، لكن أنت فقط من يمكنه تعديلها.',
    ],

    'badges' => [
        'default' => 'افتراضي',
        'shared' => 'مشترك',
        'preset' => 'جاهز',
    ],

    'notifications' => [
        'saved' => 'تم حفظ ":name"',
        'updated' => 'تم تحديث ":name"',
        'applied' => 'يتم عرض ":name"',
        'managed' => 'تم تحديث طرق العرض',
        'limit_reached' => 'يمكنك حفظ :limit طريقة عرض كحد أقصى لهذا الجدول.',
    ],

];
